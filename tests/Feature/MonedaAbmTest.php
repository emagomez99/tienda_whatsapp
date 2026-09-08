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
        $admin = $this->enTenant(function () {
            return User::create([
                'name'     => 'Admin de prueba',
                'email'    => 'admin@' . self::TENANT_DOMAIN,
                'password' => bcrypt('secreto'),
                'is_admin' => true,
                'activo'   => true,
            ]);
        });

        return $this->actingAs($admin);
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
            ->assertSee('Referencia', false);

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
        $this->assertSame('1650.500000', (string) $euro->cotizacion);
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
            $this->assertSame('1750.000000', (string) $dolar->cotizacion);
        });
    }

    // ─── Invariantes de la moneda base ───────────────────────────────────────

    /** @test */
    public function solo_hay_una_moneda_base_a_la_vez()
    {
        $this->comoAdmin()->put($this->urlTenant('admin/monedas/' . $this->dolar->id), [
            'nombre'     => 'Dólar',
            'codigo'     => 'USD',
            'simbolo'    => 'U$S',
            'cotizacion' => 1500,
            'activa'     => 1,
            'es_base'    => 1,
        ])->assertRedirect(route('admin.monedas.index'));

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

            $this->assertSame('1.000000', (string) $dolar->cotizacion);
            $this->assertTrue($dolar->activa, 'La base no puede quedar desactivada.');
            $this->assertFalse($this->peso->fresh()->es_base);
        });
    }

    /** @test */
    public function no_se_puede_quitar_la_marca_de_base()
    {
        $this->comoAdmin()->put($this->urlTenant('admin/monedas/' . $this->peso->id), [
            'nombre'     => 'Peso',
            'codigo'     => 'ARS',
            'simbolo'    => '$',
            'cotizacion' => 1,
            'activa'     => 1,
        ])->assertSessionHas('error');

        $this->enTenant(function () {
            $this->assertTrue($this->peso->fresh()->es_base);
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
        $this->assertStringContainsString('moneda de referencia', session('error'));

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
            $this->assertSame('1.000000', (string) $base->cotizacion);

            // Las demás arrancan en 1: la cotización real la carga el administrador.
            $this->assertSame('1.000000', (string) Moneda::firstWhere('codigo', 'USD')->cotizacion);
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

            $this->assertSame('1740.000000', (string) $euro->cotizacion);
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
            $this->assertSame('1600.000000', (string) $this->dolar->fresh()->cotizacion);
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
            $this->assertSame('1500.000000', (string) $this->dolar->fresh()->cotizacion);
        });
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
            ->assertSee('Tu moneda de referencia no se cotiza')
            // El input sigue en el DOM (la validación lo exige) pero oculto.
            ->assertSee('id="bloque-cotizacion" style="display:none;"', false);

        $admin->get($this->urlTenant('admin/monedas'))
            ->assertStatus(200)
            ->assertSee('Unidad de referencia');
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
            $this->assertSame('1.000000', (string) $peso->cotizacion);
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

    // ─── Moneda por defecto ──────────────────────────────────────────────────
    //
    // Es una marca distinta de la base y se comprueba aparte: la base dice contra qué
    // se miden las cotizaciones, la preseleccionada sólo dice con cuál viene abierto
    // el formulario. Una tienda que cotiza en pesos y vende en dólares necesita que
    // sean dos monedas distintas, y ese es el caso que se blinda acá.

    /** @test */
    public function la_base_y_la_preseleccionada_pueden_ser_monedas_distintas()
    {
        $this->comoAdmin()->put($this->urlTenant('admin/monedas/' . $this->dolar->id), [
            'nombre'     => 'Dólar',
            'codigo'     => 'USD',
            'simbolo'    => 'U$S',
            'cotizacion' => 1500,
            'activa'     => 1,
            'es_default' => 1,
        ])->assertRedirect(route('admin.monedas.index'));

        $this->enTenant(function () {
            $this->assertSame('ARS', Moneda::base()->codigo);
            $this->assertSame('USD', Moneda::porDefecto()->codigo);
            $this->assertFalse(Moneda::base()->es_default);
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
            'es_default' => 1,
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
