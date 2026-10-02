<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Moneda;
use App\Models\Perfil;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Support\PrecioVenta;
use Database\Seeders\PermisoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * ABM de monedas y, sobre todo, sus reglas de negocio: la base es única y vale 1, y
 * una moneda que ya está en uso no se puede eliminar ni desactivar.
 *
 * Las reglas se verifican por HTTP y también sobre el modelo, porque tienen que valer
 * igual para el formulario, para el seeder y para cualquier import.
 */
class MonedaAbmTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testmonedas';
    const TENANT_DOMAIN = 'testmonedas.test';

    /** @var \App\Models\Moneda */
    protected $peso;

    /** @var \App\Models\Moneda */
    protected $dolar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $this->peso  = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true]);
            $this->dolar = Moneda::create(['nombre' => 'Dólar', 'codigo' => 'USD', 'simbolo' => 'U$S', 'cotizacion' => 1500]);
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    protected function comoAdmin()
    {
        // firstOrCreate y no create: hay tests que piden el admin más de una vez y el
        // email es único.
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                [
                    'name'     => 'Admin de prueba',
                    'password' => bcrypt('secreto'),
                    'is_admin' => true,
                    'activo'   => true,
                ]
            );
        });

        return $this->actingAs($admin);
    }

    /**
     * Cambia en qué moneda cobra la tienda, por el camino real: la pregunta vive en
     * Ajustes, no en el ABM de monedas. El resto del payload son los campos que ese
     * formulario exige y que no tienen nada que ver con monedas.
     */
    protected function cambiarMonedaDeLaTienda(Moneda $moneda)
    {
        return $this->comoAdmin()->put($this->urlTenant('admin/configuraciones'), [
            'moneda_tienda'               => $moneda->id,
            'nombre_tienda'               => 'Tienda de prueba',
            'mostrar_precios'             => 'true',
            'mostrar_productos_sin_stock' => 'true',
            'mostrar_nombre_tienda'       => 'true',
            'mostrar_proveedor'           => 'false',
            'color_primario'              => '#0d6efd',
            'posicion_menu'               => 'superior',
            'pedir_direccion_envio'       => 'true',
            'robots_index'                => 'true',
            'ubicacion_activa'            => 'false',
        ]);
    }

    /** Elige la moneda que viene preseleccionada al cargar un producto. null = ninguna. */
    protected function elegirMonedaFavorita(Moneda $moneda = null)
    {
        return $this->comoAdmin()->put($this->urlTenant('admin/configuraciones'), [
            'moneda_favorita'             => $moneda ? $moneda->id : '',
            'nombre_tienda'               => 'Tienda de prueba',
            'mostrar_precios'             => 'true',
            'mostrar_productos_sin_stock' => 'true',
            'mostrar_nombre_tienda'       => 'true',
            'mostrar_proveedor'           => 'false',
            'color_primario'              => '#0d6efd',
            'posicion_menu'               => 'superior',
            'pedir_direccion_envio'       => 'true',
            'robots_index'                => 'true',
            'ubicacion_activa'            => 'false',
        ]);
    }

    /** Producto que usa la moneda indicada como moneda de venta. */
    protected function crearProductoQueUsa(Moneda $moneda)
    {
        return $this->enTenant(function () use ($moneda) {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);

            return Producto::create([
                'proveedor_id' => $proveedor->id,
                'descripcion'  => 'Producto que usa ' . $moneda->codigo,
                'precio'       => 100,
                'moneda_id'    => $moneda->id,
                'stock'        => 1,
            ]);
        });
    }

    // ─── Alta y edición ──────────────────────────────────────────────────────

    /** @test */
    public function las_pantallas_del_abm_responden()
    {
        $admin = $this->comoAdmin();

        $admin->get($this->urlTenant('admin/monedas'))
            ->assertStatus(200)
            ->assertSee('Dólar', false)
            ->assertSee('Tu moneda', false);

        $admin->get($this->urlTenant('admin/monedas/create'))->assertStatus(200);
        $admin->get($this->urlTenant('admin/monedas/' . $this->dolar->id . '/edit'))->assertStatus(200);
    }

    /** @test */
    public function crea_una_moneda()
    {
        $this->comoAdmin()->post($this->urlTenant('admin/monedas'), [
            'nombre'     => 'Euro',
            'codigo'     => 'EUR',
            'simbolo'    => '€',
            'cotizacion' => 1650.5,
            'activa'     => 1,
        ])->assertRedirect(route('admin.monedas.index'));

        $euro = $this->enTenant(function () {
            return Moneda::firstWhere('codigo', 'EUR');
        });

        $this->assertNotNull($euro);
        $this->assertSame(1650.5, (float) $euro->cotizacion);
        $this->assertFalse($euro->es_base);
        $this->assertTrue($euro->activa);
    }

    /** @test */
    public function el_alta_valida_los_datos()
    {
        $this->comoAdmin()->post($this->urlTenant('admin/monedas'), [
            'nombre'     => 'Peso',   // repetido
            'codigo'     => 'PESOS',  // más de 3 letras
            'simbolo'    => '$',
            'cotizacion' => 0,        // tiene que ser mayor a cero
        ])->assertSessionHasErrors(['nombre', 'codigo', 'cotizacion']);
    }

    /** @test */
    public function edita_una_moneda()
    {
        $this->comoAdmin()->put($this->urlTenant('admin/monedas/' . $this->dolar->id), [
            'nombre'     => 'Dólar Estadounidense',
            'codigo'     => 'USD',
            'simbolo'    => 'U$D',
            'cotizacion' => 1750,
            'activa'     => 1,
        ])->assertRedirect(route('admin.monedas.index'));

        $this->enTenant(function () {
            $dolar = $this->dolar->fresh();

            $this->assertSame('Dólar Estadounidense', $dolar->nombre);
            $this->assertSame('U$D', $dolar->simbolo);
            $this->assertSame(1750.0, (float) $dolar->cotizacion);
        });
    }

    // ─── Invariantes de la moneda base ───────────────────────────────────────

    /** @test */
    public function solo_hay_una_moneda_base_a_la_vez()
    {
        $this->cambiarMonedaDeLaTienda($this->dolar)
            ->assertRedirect(route('admin.configuraciones.index'));

        $this->enTenant(function () {
            $this->assertTrue($this->dolar->fresh()->es_base);
            $this->assertFalse($this->peso->fresh()->es_base);
            $this->assertSame(1, Moneda::where('es_base', true)->count());
            $this->assertSame($this->dolar->id, Moneda::base()->id);
        });
    }

    /**
     * La invariante vale también fuera del formulario: marcar la base desde el modelo
     * degrada a la anterior y deja la cotización en 1.
     *
     * @test
     */
    public function la_base_siempre_queda_en_uno_aunque_le_manden_otra_cotizacion()
    {
        $this->enTenant(function () {
            $this->dolar->update(['es_base' => true, 'cotizacion' => 1500, 'activa' => false]);

            $dolar = $this->dolar->fresh();

            $this->assertSame(1.0, (float) $dolar->cotizacion);
            $this->assertTrue($dolar->activa, 'La base no puede quedar desactivada.');
            $this->assertFalse($this->peso->fresh()->es_base);
        });
    }

    /**
     * El ABM ya no manda es_base -- la pregunta vive en Ajustes --, así que editar la
     * moneda de la tienda no puede degradarla sin querer. Sin esto, un `boolean()`
     * sobre un campo ausente dejaría la tienda sin moneda al renombrarla.
     *
     * @test
     */
    public function editar_la_moneda_de_la_tienda_no_le_quita_la_marca()
    {
        $this->comoAdmin()->put($this->urlTenant('admin/monedas/' . $this->peso->id), [
            'nombre'     => 'Peso Argentino',
            'codigo'     => 'ARS',
            'simbolo'    => '$',
            'cotizacion' => 1,
            'activa'     => 1,
        ])->assertRedirect(route('admin.monedas.index'));

        $this->enTenant(function () {
            $peso = $this->peso->fresh();

            $this->assertSame('Peso Argentino', $peso->nombre);
            $this->assertTrue($peso->es_base, 'Renombrarla no puede dejar la tienda sin moneda.');
            $this->assertSame(1, Moneda::where('es_base', true)->count());
        });
    }

    // ─── Monedas en uso ──────────────────────────────────────────────────────

    /** @test */
    public function elimina_una_moneda_que_no_esta_en_uso()
    {
        $this->comoAdmin()->delete($this->urlTenant('admin/monedas/' . $this->dolar->id))
            ->assertRedirect(route('admin.monedas.index'))
            ->assertSessionHas('success');

        $this->enTenant(function () {
            $this->assertNull(Moneda::find($this->dolar->id));
        });
    }

    /** @test */
    public function no_se_puede_eliminar_una_moneda_en_uso()
    {
        $this->crearProductoQueUsa($this->dolar);

        $respuesta = $this->comoAdmin()->delete($this->urlTenant('admin/monedas/' . $this->dolar->id));

        $respuesta->assertRedirect(route('admin.monedas.index'));
        $this->assertStringContainsString('está en uso', session('error'));

        $this->enTenant(function () {
            $this->assertNotNull(Moneda::find($this->dolar->id));
        });
    }

    /** @test */
    public function no_se_puede_eliminar_la_moneda_base()
    {
        $respuesta = $this->comoAdmin()->delete($this->urlTenant('admin/monedas/' . $this->peso->id));

        $respuesta->assertRedirect(route('admin.monedas.index'));
        $this->assertStringContainsString('moneda de tu tienda', session('error'));

        $this->enTenant(function () {
            $this->assertNotNull(Moneda::find($this->peso->id));
        });
    }

    /** @test */
    public function no_se_puede_desactivar_una_moneda_en_uso()
    {
        $this->crearProductoQueUsa($this->dolar);

        $this->comoAdmin()->put($this->urlTenant('admin/monedas/' . $this->dolar->id), [
            'nombre'     => 'Dólar',
            'codigo'     => 'USD',
            'simbolo'    => 'U$S',
            'cotizacion' => 1500,
            // sin 'activa': el switch apagado no manda nada
        ])->assertSessionHas('error');

        $this->enTenant(function () {
            $this->assertTrue($this->dolar->fresh()->activa);
        });
    }

    // ─── Migración de datos existentes ───────────────────────────────────────

    /**
     * En un tenant que ya tenía monedas cargadas, la migración tiene que elegir una
     * base sola: la moneda por defecto de la tienda si está configurada, el peso si
     * no. Se simula ese tenant deshaciendo la migración y volviéndola a correr.
     *
     * @test
     */
    public function la_migracion_elige_una_moneda_base_en_los_tenants_existentes()
    {
        $migracion = require base_path('database/migrations/tenant/2026_08_30_000001_add_cotizacion_to_monedas_table.php');

        $this->enTenant(function () use ($migracion) {
            // Sin moneda por defecto configurada: gana el peso por su código.
            $migracion->down();
            $migracion->up();

            $base = Moneda::base();
            $this->assertSame('ARS', $base->codigo);
            $this->assertSame(1.0, (float) $base->cotizacion);

            // Las demás arrancan en 1: la cotización real la carga el administrador.
            $this->assertSame(1.0, (float) Moneda::firstWhere('codigo', 'USD')->cotizacion);
            $this->assertSame(1, Moneda::where('es_base', true)->count());
        });

        $this->enTenant(function () use ($migracion) {
            Configuracion::establecer('moneda_default', (string) $this->dolar->id);

            $migracion->down();
            $migracion->up();

            $this->assertSame('USD', Moneda::base()->codigo);
        });
    }

    // ─── Cotización cargada contra cualquier moneda ──────────────────────────
    //
    // El formulario deja escribir "1 EUR equivale a 1,16 USD" porque es como se lee
    // una cotización, pero por debajo se guarda un solo número por moneda (contra la
    // base). Eso es lo que hace imposible cargar un juego de pares contradictorio.

    /** @test */
    public function la_cotizacion_se_puede_cargar_contra_una_moneda_que_no_es_la_base()
    {
        // 1 EUR = 1,16 USD, y 1 USD = 1500 ARS -> 1 EUR = 1740 ARS.
        $this->comoAdmin()->post($this->urlTenant('admin/monedas'), [
            'nombre'                   => 'Euro',
            'codigo'                   => 'EUR',
            'simbolo'                  => '€',
            'cotizacion'               => 1.16,
            'cotizacion_referencia_id' => $this->dolar->id,
            'activa'                   => 1,
        ])->assertRedirect(route('admin.monedas.index'));

        $this->enTenant(function () {
            $euro = Moneda::firstWhere('codigo', 'EUR');

            $this->assertSame(1740.0, (float) $euro->cotizacion);
        });
    }

    /**
     * El punto de guardar un solo número por moneda: los pares se derivan y son
     * coherentes en las dos direcciones, sin que nadie los cargue a mano.
     *
     * @test
     */
    public function los_pares_derivados_coinciden_en_las_dos_direcciones()
    {
        $this->enTenant(function () {
            $euro = Moneda::create(['nombre' => 'Euro', 'codigo' => 'EUR', 'simbolo' => '€', 'cotizacion' => 1736]);
            $peso = $this->peso->fresh();

            // Los cuatro números tal como se leen en la realidad.
            $this->assertSame(1500.0, PrecioVenta::factor($this->dolar->cotizacion, $peso->cotizacion));
            $this->assertSame(1736.0, PrecioVenta::factor($euro->cotizacion, $peso->cotizacion));
            $this->assertSame(0.864055, PrecioVenta::factor($this->dolar->cotizacion, $euro->cotizacion));
            $this->assertSame(1.157333, PrecioVenta::factor($euro->cotizacion, $this->dolar->cotizacion));

            // Ida y vuelta: el producto de los dos sentidos tiene que dar 1.
            $ida    = PrecioVenta::factor($this->dolar->cotizacion, $euro->cotizacion);
            $vuelta = PrecioVenta::factor($euro->cotizacion, $this->dolar->cotizacion);
            $this->assertEqualsWithDelta(1.0, $ida * $vuelta, 0.000001);
        });
    }

    /** @test */
    public function sin_moneda_de_referencia_la_cotizacion_se_entiende_contra_la_base()
    {
        $this->comoAdmin()->put($this->urlTenant('admin/monedas/' . $this->dolar->id), [
            'nombre'     => 'Dólar',
            'codigo'     => 'USD',
            'simbolo'    => 'U$S',
            'cotizacion' => 1600,
            'activa'     => 1,
        ])->assertRedirect(route('admin.monedas.index'));

        $this->enTenant(function () {
            $this->assertSame(1600.0, (float) $this->dolar->fresh()->cotizacion);
        });
    }

    /** @test */
    public function una_moneda_no_se_puede_cotizar_contra_si_misma()
    {
        $this->comoAdmin()->put($this->urlTenant('admin/monedas/' . $this->dolar->id), [
            'nombre'                   => 'Dólar',
            'codigo'                   => 'USD',
            'simbolo'                  => 'U$S',
            'cotizacion'               => 1,
            'cotizacion_referencia_id' => $this->dolar->id,
            'activa'                   => 1,
        ])->assertSessionHasErrors('cotizacion_referencia_id');

        $this->enTenant(function () {
            $this->assertSame(1500.0, (float) $this->dolar->fresh()->cotizacion);
        });
    }

    /**
     * Las marcas se ven en la tabla, pero el listado ya no explica el concepto: en qué
     * moneda cobra la tienda se configura en Ajustes. Lo único que queda es el aviso
     * del estado roto, y sólo cuando efectivamente lo está.
     *
     * @test
     */
    public function el_listado_no_explica_la_moneda_de_la_tienda()
    {
        $this->comoAdmin()
            ->get($this->urlTenant('admin/monedas'))
            ->assertStatus(200)
            ->assertSee('Tu moneda', false)
            ->assertDontSee('Ponés tus precios en', false)
            ->assertDontSee('Falta definir en qué moneda cobrás', false);
    }

    /** @test */
    public function sin_moneda_de_la_tienda_el_listado_avisa()
    {
        $this->enTenant(function () {
            // Estado roto: sin moneda de la tienda no hay contra qué cotizar.
            Moneda::query()->update(['es_base' => false]);
        });

        $this->comoAdmin()
            ->get($this->urlTenant('admin/monedas'))
            ->assertStatus(200)
            ->assertSee('Falta definir en qué moneda cobrás', false);
    }

    /**
     * Venta y compra son dos datos distintos y van en columnas propias: un proveedor
     * que cobra en dólares con precios de venta en pesos es el caso normal del modo
     * margen, y apilados en una celda había que adivinar cuál era cuál.
     *
     * @test
     */
    public function el_listado_separa_los_productos_de_venta_de_los_de_compra()
    {
        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);

            // Se compra en dólares y se vende en pesos: cada moneda cuenta en una
            // columna distinta.
            foreach (range(1, 3) as $i) {
                Producto::create([
                    'proveedor_id'     => $proveedor->id,
                    'descripcion'      => 'Producto ' . $i,
                    'precio'           => 100,
                    'moneda_id'        => $this->peso->id,
                    'precio_compra'    => 10,
                    'moneda_compra_id' => $this->dolar->id,
                    'stock'            => 1,
                ]);
            }
        });

        $respuesta = $this->comoAdmin()
            ->get($this->urlTenant('admin/monedas'))
            ->assertStatus(200)
            ->assertSee('Prod. venta')
            ->assertSee('Prod. compra');

        $monedas = $respuesta->viewData('monedas')->keyBy('codigo');

        $this->assertSame(3, $monedas['ARS']->productos_count);
        $this->assertSame(0, $monedas['ARS']->productos_de_compra_count);
        $this->assertSame(0, $monedas['USD']->productos_count);
        $this->assertSame(3, $monedas['USD']->productos_de_compra_count);
    }

    /** @test */
    public function el_listado_muestra_la_tabla_de_equivalencias()
    {
        $this->comoAdmin()
            ->get($this->urlTenant('admin/monedas'))
            ->assertStatus(200)
            ->assertSee('Equivalencias')
            ->assertSee('1.500', false);
    }

    /**
     * La base es la unidad de medida, no algo que se cotice: el formulario no le
     * ofrece el campo y el listado no le inventa un "1" que nadie cargó.
     *
     * @test
     */
    public function la_moneda_base_no_muestra_cotizacion_propia()
    {
        $admin = $this->comoAdmin();

        $admin->get($this->urlTenant('admin/monedas/' . $this->peso->id . '/edit'))
            ->assertStatus(200)
            ->assertSee('Es la moneda en la que cobrás: no se cotiza', false)
            // El campo no se dibuja: sólo va el valor 1 oculto, que la validación exige.
            ->assertSee('<input type="hidden" name="cotizacion" value="1">', false)
            ->assertDontSee('id="cotizacion"', false);

        $admin->get($this->urlTenant('admin/monedas'))
            ->assertStatus(200)
            ->assertSee('Es tu moneda');
    }

    /** @test */
    public function la_moneda_base_se_sigue_guardando_con_cotizacion_uno()
    {
        $this->comoAdmin()->put($this->urlTenant('admin/monedas/' . $this->peso->id), [
            'nombre'     => 'Peso',
            'codigo'     => 'ARS',
            'simbolo'    => '$',
            'cotizacion' => 1,
            'activa'     => 1,
            'es_base'    => 1,
        ])->assertRedirect(route('admin.monedas.index'));

        $this->enTenant(function () {
            $peso = $this->peso->fresh();

            $this->assertTrue($peso->es_base);
            $this->assertSame(1.0, (float) $peso->cotizacion);
        });
    }

    // ─── Aviso de recálculo ──────────────────────────────────────────────────

    /**
     * El aviso sólo tiene sentido si hay algo que recalcular, y el número que muestra
     * tiene que salir de la misma definición que usa el UPDATE. Si contar y recalcular
     * se separan, el usuario ve "se recalculan 300" y el flash le contesta otra cosa.
     *
     * @test
     */
    public function el_formulario_avisa_cuantos_productos_se_van_a_recalcular()
    {
        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);

            foreach ([10, 20, 30] as $costo) {
                Producto::create([
                    'proveedor_id'      => $proveedor->id,
                    'descripcion'       => 'Por margen ' . $costo,
                    'moneda_id'         => $this->peso->id,
                    'moneda_compra_id'  => $this->dolar->id,
                    'precio_compra'     => $costo,
                    'margen_ganancia'   => 25,
                    'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
                    'stock'             => 1,
                ]);
            }

            $this->assertSame(3, Producto::contarEnMargenPorMoneda($this->dolar->id));
        });

        $this->comoAdmin()
            ->get($this->urlTenant('admin/monedas/' . $this->dolar->id . '/edit'))
            ->assertStatus(200)
            ->assertSee('Actualizando precios de los productos')
            ->assertSee('Se están recalculando 3 productos');
    }

    /** @test */
    public function sin_productos_que_recalcular_no_se_muestra_el_aviso()
    {
        $this->comoAdmin()
            ->get($this->urlTenant('admin/monedas/' . $this->dolar->id . '/edit'))
            ->assertStatus(200)
            ->assertSee('var AFECTADOS = 0', false);
    }

    /**
     * El conteo previo y el recálculo tienen que hablar del mismo universo: lo que el
     * formulario anuncia es lo que el flash termina informando.
     *
     * @test
     */
    public function el_conteo_previo_coincide_con_lo_que_recalcula_el_update()
    {
        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);

            foreach ([10, 20, 30, 40] as $costo) {
                Producto::create([
                    'proveedor_id'      => $proveedor->id,
                    'descripcion'       => 'Por margen ' . $costo,
                    'moneda_id'         => $this->peso->id,
                    'moneda_compra_id'  => $this->dolar->id,
                    'precio_compra'     => $costo,
                    'margen_ganancia'   => 25,
                    'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
                    'stock'             => 1,
                ]);
            }

            $anunciados = Producto::contarEnMargenPorMoneda($this->dolar->id);

            $this->dolar->update(['cotizacion' => 1800]);
            $recalculados = Producto::recalcularPreciosEnMargen($this->dolar->id);

            $this->assertSame($anunciados, $recalculados);
        });
    }

    // ─── Cambio de la moneda de la tienda ────────────────────────────────────
    //
    // Cambiar cuál es la moneda de la tienda es un cambio de UNIDAD DE MEDIDA, no de
    // valor: que los precios pasen a expresarse contra el dólar no hace que nada valga
    // distinto. Un producto de 65.625 pesos sigue costando eso. Si el precio se mueve,
    // el sistema perdió plata del cliente en una operación de configuración.

    /** Producto por margen: 35 USD de costo, se vende en pesos con 25% de ganancia. */
    protected function crearProductoPorMargen()
    {
        return $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);

            // El precio derivado lo calcula ajustarPrecioSegunModo(), no el create():
            // hay que pasar por él igual que hace el controlador.
            $producto = new Producto([
                'proveedor_id'      => $proveedor->id,
                'descripcion'       => 'Producto por margen',
                'precio_compra'     => 35,
                'moneda_compra_id'  => $this->dolar->id,
                'moneda_id'         => $this->peso->id,
                'margen_ganancia'   => 25,
                'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
                'stock'             => 1,
            ]);

            $producto->ajustarPrecioSegunModo();
            $producto->save();

            return $producto;
        });
    }

    /** @test */
    public function cambiar_la_moneda_de_la_tienda_no_mueve_el_precio_de_los_productos()
    {
        $producto = $this->crearProductoPorMargen();

        // 35 USD * 1500 = 52.500 ARS, + 25% = 65.625.
        $this->enTenant(function () use ($producto) {
            $this->assertSame('65625.00', (string) $producto->fresh()->precio);
        });

        // La tienda pasa a cobrar en dólares.
        $this->cambiarMonedaDeLaTienda($this->dolar)
            ->assertRedirect(route('admin.configuraciones.index'));

        $this->enTenant(function () use ($producto) {
            // El producto se sigue vendiendo en pesos y sigue costando 35 dólares:
            // nada de lo que el usuario cargó cambió, así que el precio tampoco.
            $this->assertSame('65625.00', (string) $producto->fresh()->precio);
        });
    }

    /** @test */
    public function cambiar_la_moneda_de_la_tienda_reexpresa_las_cotizaciones()
    {
        $this->enTenant(function () {
            Moneda::create(['nombre' => 'Euro', 'codigo' => 'EUR', 'simbolo' => '€', 'cotizacion' => 1736]);
        });

        $this->cambiarMonedaDeLaTienda($this->dolar)
            ->assertRedirect(route('admin.configuraciones.index'));

        $this->enTenant(function () {
            $usd = Moneda::firstWhere('codigo', 'USD');
            $ars = Moneda::firstWhere('codigo', 'ARS');
            $eur = Moneda::firstWhere('codigo', 'EUR');

            $this->assertTrue($usd->es_base);
            $this->assertFalse($ars->es_base);

            // Todo se reexpresa dividiendo por la cotización vieja del dólar.
            $this->assertSame(1.0, (float) $usd->cotizacion);
            $this->assertEqualsWithDelta(1 / 1500, (float) $ars->cotizacion, 0.000001);
            $this->assertEqualsWithDelta(1736 / 1500, (float) $eur->cotizacion, 0.000001);

            // Y las equivalencias reales quedan intactas: 1 USD sigue siendo 1500 ARS.
            $this->assertEqualsWithDelta(1500, PrecioVenta::factor($usd->cotizacion, $ars->cotizacion), 0.5);
            $this->assertEqualsWithDelta(1736, PrecioVenta::factor($eur->cotizacion, $ars->cotizacion), 0.5);
        });
    }

    // ─── Moneda por defecto ──────────────────────────────────────────────────
    //
    // Es una marca distinta de la base y se comprueba aparte: la base dice contra qué
    // se miden las cotizaciones, la preseleccionada sólo dice con cuál viene abierto
    // el formulario. Una tienda que cotiza en pesos y vende en dólares necesita que
    // sean dos monedas distintas, y ese es el caso que se blinda acá.

    /** @test */
    public function la_base_y_la_preseleccionada_pueden_ser_monedas_distintas()
    {
        $this->elegirMonedaFavorita($this->dolar)
            ->assertRedirect(route('admin.configuraciones.index'));

        $this->enTenant(function () {
            $this->assertSame('ARS', Moneda::base()->codigo);
            $this->assertSame('USD', Moneda::porDefecto()->codigo);
            $this->assertFalse(Moneda::base()->es_default);
        });
    }

    /** @test */
    public function se_puede_dejar_la_tienda_sin_moneda_favorita()
    {
        $this->elegirMonedaFavorita($this->dolar);

        $this->enTenant(function () {
            $this->assertNotNull(Moneda::porDefecto());
        });

        // A diferencia de la moneda de la tienda, ésta es opcional: sin favorita el
        // formulario de producto simplemente abre sin moneda elegida.
        $this->elegirMonedaFavorita(null);

        $this->enTenant(function () {
            $this->assertNull(Moneda::porDefecto());
            $this->assertSame(0, Moneda::where('es_default', true)->count());
        });
    }

    /** @test */
    public function solo_hay_una_moneda_por_defecto_a_la_vez()
    {
        $this->enTenant(function () {
            $this->peso->update(['es_default' => true]);
            $this->dolar->update(['es_default' => true]);

            $this->assertSame(1, Moneda::where('es_default', true)->count());
            $this->assertSame('USD', Moneda::porDefecto()->codigo);
            $this->assertFalse($this->peso->fresh()->es_default);
        });
    }

    /**
     * El formulario de producto sólo ofrece monedas activas: una preseleccionada
     * inactiva dejaría el campo vacío sin que nada explique por qué.
     *
     * @test
     */
    public function la_moneda_por_defecto_no_puede_quedar_inactiva()
    {
        $this->enTenant(function () {
            $this->dolar->update(['es_default' => true, 'activa' => false]);

            $this->assertTrue($this->dolar->fresh()->activa);
        });
    }

    /** @test */
    public function no_se_puede_desactivar_la_moneda_por_defecto_desde_el_abm()
    {
        $this->enTenant(function () {
            $this->dolar->update(['es_default' => true]);
        });

        $this->comoAdmin()->put($this->urlTenant('admin/monedas/' . $this->dolar->id), [
            'nombre'     => 'Dólar',
            'codigo'     => 'USD',
            'simbolo'    => 'U$S',
            'cotizacion' => 1500,
        ])->assertSessionHas('error');

        $this->enTenant(function () {
            $this->assertTrue($this->dolar->fresh()->activa);
        });
    }

    /** @test */
    public function la_moneda_por_defecto_viene_preseleccionada_al_cargar_un_producto()
    {
        $this->enTenant(function () {
            $this->dolar->update(['es_default' => true]);
        });

        $this->comoAdmin()
            ->get($this->urlTenant('admin/productos/create'))
            ->assertStatus(200)
            ->assertSee('value="' . $this->dolar->id . '" selected', false);
    }

    /**
     * La preferencia vivía en `configuraciones.moneda_default`. La migración la
     * traslada a la moneda y borra la clave vieja: si quedaran las dos, la próxima
     * edición de Ajustes reviviría un valor que ya nadie lee.
     *
     * @test
     */
    public function la_migracion_traslada_la_moneda_por_defecto_de_configuracion()
    {
        $this->enTenant(function () {
            $migracion = require base_path('database/migrations/tenant/2026_08_30_000005_move_moneda_default_to_monedas.php');

            // Estado del tenant viejo: la marca no existe y la preferencia es un id
            // suelto guardado en la tabla de configuraciones.
            $migracion->down();
            Configuracion::establecer('moneda_default', (string) $this->dolar->id);

            $migracion->up();

            $this->assertSame('USD', Moneda::porDefecto()->codigo);
            $this->assertNull(DB::table('configuraciones')->where('clave', 'moneda_default')->value('valor'));
        });
    }

    /** @test */
    public function sin_preferencia_previa_la_migracion_elige_el_peso()
    {
        $this->enTenant(function () {
            $migracion = require base_path('database/migrations/tenant/2026_08_30_000005_move_moneda_default_to_monedas.php');

            $migracion->down();
            DB::table('configuraciones')->where('clave', 'moneda_default')->delete();

            $migracion->up();

            $this->assertSame('ARS', Moneda::porDefecto()->codigo);
        });
    }

    // ─── Permisos ────────────────────────────────────────────────────────────

    /**
     * Los permisos del ABM tienen que aparecer también en los tenants que ya existían
     * antes de esta funcionalidad, y quedar asignados a los perfiles que administran
     * la tienda. Se simula ese estado: se siembra el catálogo, se borran los permisos
     * de monedas -- que es como estaba el tenant viejo -- y se corre la migración.
     *
     * @test
     */
    public function la_migracion_da_de_alta_los_permisos_en_los_tenants_existentes()
    {
        $this->enTenant(function () {
            (new PermisoSeeder())->run();

            $ids = Permiso::where('nombre', 'ilike', 'monedas.%')->pluck('id');
            DB::table('perfil_permiso')->whereIn('permiso_id', $ids)->delete();
            Permiso::whereIn('id', $ids)->delete();

            $migracion = require base_path('database/migrations/tenant/2026_08_30_000004_add_permisos_monedas.php');
            $migracion->up();

            $permisos = Permiso::where('nombre', 'ilike', 'monedas.%')->pluck('id', 'nombre');

            $this->assertCount(4, $permisos);

            foreach (['Superadministrador', 'Administrador'] as $nombre) {
                $perfil = Perfil::where('nombre', $nombre)->first();

                foreach ($permisos as $slug => $id) {
                    $this->assertTrue($perfil->tiene($slug), $nombre . ' debería tener ' . $slug);
                }
            }

            // Correrla de nuevo no duplica nada.
            $migracion->up();
            $this->assertSame(4, Permiso::where('nombre', 'ilike', 'monedas.%')->count());
        });
    }

    /**
     * Una moneda que sólo se usa como moneda de COMPRA también está en uso: si se
     * borrara, los productos que la declaran quedarían sin cómo calcular su precio.
     *
     * @test
     */
    public function la_moneda_de_compra_tambien_cuenta_como_uso()
    {
        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);

            Producto::create([
                'proveedor_id'      => $proveedor->id,
                'descripcion'       => 'Comprado en dólares',
                'precio'            => 1000,
                'moneda_id'         => $this->peso->id,
                'moneda_compra_id'  => $this->dolar->id,
                'precio_compra'     => 10,
                'margen_ganancia'   => 0,
                'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
                'stock'             => 1,
            ]);

            $this->assertContains('productos que se compran en esta moneda', $this->dolar->usos());
        });

        $this->comoAdmin()->delete($this->urlTenant('admin/monedas/' . $this->dolar->id));

        $this->enTenant(function () {
            $this->assertNotNull(Moneda::find($this->dolar->id));
        });
    }
}
