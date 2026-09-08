<?php

namespace Tests\Feature;

use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Support\PrecioVenta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * El precio de venta: cómo se calcula, dónde queda guardado y qué lo hace cambiar.
 *
 * La pieza que este test cuida más de cerca es que la cuenta de PHP
 * (App\Support\PrecioVenta, la que corre al guardar un producto) y el UPDATE masivo
 * de Postgres (el que corre al cambiar una cotización) devuelvan EXACTAMENTE el mismo
 * número. Si se separan, el precio de un producto cambia solo por haber tocado una
 * cotización que ni siquiera lo afectaba, y eso es imposible de explicarle a nadie.
 */
class PrecioProductoTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testprecios';
    const TENANT_DOMAIN = 'testprecios.test';

    /** @var array */
    protected $monedas;

    /** @var \App\Models\Proveedor */
    protected $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $this->proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);

            $this->monedas = [
                'ARS' => Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true]),
                'USD' => Moneda::create(['nombre' => 'Dólar', 'codigo' => 'USD', 'simbolo' => 'U$S', 'cotizacion' => 1500]),
                'EUR' => Moneda::create(['nombre' => 'Euro', 'codigo' => 'EUR', 'simbolo' => '€', 'cotizacion' => 1650]),
            ];
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    /** Usuario administrador del tenant, para las rutas de /admin. */
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

    /** Crea un producto ya con su precio de venta al día, dentro del tenant. */
    protected function crearProducto(array $atributos)
    {
        return $this->enTenant(function () use ($atributos) {
            $producto = new Producto(array_merge([
                'proveedor_id'      => $this->proveedor->id,
                'precio'            => 0,
                'stock'             => 1,
                'disponible'        => true,
                'modo_precio_venta' => Producto::MODO_PRECIO_MANUAL,
            ], $atributos));

            $producto->ajustarPrecioSegunModo();
            $producto->save();

            return $producto;
        });
    }

    protected function datosDeProducto(array $extra = [])
    {
        return array_merge([
            'proveedor_id'      => $this->proveedor->id,
            'descripcion'       => 'Producto de prueba',
            'stock'             => 5,
            'disponible'        => 1,
            'autogenerar_slug'  => 1,
            'modo_precio_venta' => Producto::MODO_PRECIO_MANUAL,
        ], $extra);
    }

    // ─── El acuerdo entre PHP y Postgres ─────────────────────────────────────

    /**
     * El corazón del diseño: la fórmula está escrita una sola vez (PrecioVenta) y el
     * recálculo masivo la reconstruye en SQL. Este test recorre un abanico de valores
     * -- incluidos los que caen justo sobre el medio centavo, que es donde el
     * redondeo binario de PHP y el decimal de Postgres se pelean -- y exige igualdad
     * exacta, no "parecido".
     *
     * @test
     */
    public function el_update_masivo_de_postgres_da_el_mismo_numero_que_el_calculo_en_php()
    {
        $costos   = [0.01, 1.00, 1.15, 2.02, 3.00, 9.99, 10.01, 10.10, 33.33, 100.00, 1234.56, 99999.99];
        $margenes = [0, 0.5, 5, 7.25, 25, 33.33, 40, 50, 100, 150.75];
        $pares    = [['ARS', 'ARS'], ['USD', 'ARS'], ['ARS', 'USD'], ['USD', 'EUR'], ['EUR', 'USD']];

        // Barrido con semilla fija: cubre combinaciones que a mano no se me habrían
        // ocurrido, y al ser la misma semilla siempre no puede fallar un día sí y
        // otro no. Si alguna vez rompe, el valor está en el mensaje del assert.
        mt_srand(20260830);
        for ($i = 0; $i < 24; $i++) {
            $costos[]   = round(mt_rand(1, 5000000) / 100, 2);
            $margenes[] = round(mt_rand(0, 30000) / 100, 2);
        }

        $esperados = $this->enTenant(function () use ($costos, $margenes, $pares) {
            $filas     = [];
            $esperados = [];
            $id        = 0;

            foreach ($pares as $par) {
                foreach ($costos as $costo) {
                    foreach ($margenes as $margen) {
                        $compra = $this->monedas[$par[0]];
                        $venta  = $this->monedas[$par[1]];

                        $filas[] = [
                            'proveedor_id'      => $this->proveedor->id,
                            'public_id'         => (string) Str::uuid(),
                            'descripcion'       => 'Combinación ' . (++$id),
                            'precio'            => 0,
                            'moneda_id'         => $venta->id,
                            'moneda_compra_id'  => $compra->id,
                            'precio_compra'     => $costo,
                            'margen_ganancia'   => $margen,
                            'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
                            'stock'             => 1,
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ];

                        $esperados['Combinación ' . $id] = PrecioVenta::calcular(
                            $costo,
                            $compra->cotizacion,
                            $venta->cotizacion,
                            $margen
                        )->monto;
                    }
                }
            }

            foreach (array_chunk($filas, 200) as $lote) {
                DB::table('productos')->insert($lote);
            }

            Producto::recalcularPreciosEnMargen();

            return $esperados;
        });

        $guardados = $this->enTenant(function () {
            return Producto::pluck('precio', 'descripcion')->all();
        });

        $this->assertCount(count($esperados), $guardados);

        foreach ($esperados as $descripcion => $esperado) {
            $this->assertSame(
                number_format($esperado, 2, '.', ''),
                (string) $guardados[$descripcion],
                'El UPDATE de Postgres y PrecioVenta difieren en "' . $descripcion . '".'
            );
        }
    }

    // ─── Alta y edición ──────────────────────────────────────────────────────

    /** @test */
    public function el_formulario_de_producto_muestra_el_bloque_de_precio()
    {
        $producto = $this->crearProducto([
            'descripcion'       => 'Con margen',
            'moneda_id'         => $this->monedas['ARS']->id,
            'moneda_compra_id'  => $this->monedas['USD']->id,
            'precio_compra'     => 10,
            'margen_ganancia'   => 40,
            'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
        ]);

        $admin = $this->comoAdmin();

        $admin->get($this->urlTenant('admin/productos/create'))
            ->assertStatus(200)
            ->assertSee('name="precio_compra"', false)
            ->assertSee('name="modo_precio_venta"', false)
            ->assertSee('name="margen_ganancia"', false);

        $admin->get($this->urlTenant('admin/productos/' . $producto->id . '/edit'))
            ->assertStatus(200)
            ->assertSee('value="' . Producto::MODO_PRECIO_MARGEN . '" checked', false);
    }

    /** @test */
    public function crear_un_producto_por_margen_persiste_el_precio_calculado()
    {
        $this->comoAdmin()->post($this->urlTenant('admin/productos'), $this->datosDeProducto([
            'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
            'moneda_id'         => $this->monedas['ARS']->id,
            'moneda_compra_id'  => $this->monedas['USD']->id,
            'precio_compra'     => 10,
            'margen_ganancia'   => 40,
            // Precio inventado por el navegador: el servidor tiene que ignorarlo.
            'precio'            => 1,
        ]))->assertRedirect();

        $producto = $this->enTenant(function () {
            return Producto::firstWhere('descripcion', 'Producto de prueba');
        });

        // 10 USD * 1500 = 15.000 ARS, + 40% = 21.000.
        $this->assertSame('21000.00', (string) $producto->precio);
        $this->assertSame(Producto::MODO_PRECIO_MARGEN, $producto->modo_precio_venta);
        $this->assertSame($this->monedas['ARS']->id, $producto->moneda_id);
    }

    /** @test */
    public function crear_un_producto_a_mano_deja_el_precio_tal_cual_se_cargo()
    {
        $this->comoAdmin()->post($this->urlTenant('admin/productos'), $this->datosDeProducto([
            'modo_precio_venta' => Producto::MODO_PRECIO_MANUAL,
            'moneda_id'         => $this->monedas['ARS']->id,
            'precio'            => 1234.56,
        ]))->assertRedirect();

        $producto = $this->enTenant(function () {
            return Producto::firstWhere('descripcion', 'Producto de prueba');
        });

        $this->assertSame('1234.56', (string) $producto->precio);
        $this->assertSame(Producto::MODO_PRECIO_MANUAL, $producto->modo_precio_venta);
        $this->assertNull($producto->precio_compra);
    }

    /**
     * Una request que no conoce los modos -- un formulario viejo cacheado, un import
     * por HTTP -- tiene que seguir cargando el producto como siempre.
     *
     * @test
     */
    public function una_request_sin_modo_carga_el_producto_a_mano()
    {
        $datos = $this->datosDeProducto([
            'moneda_id' => $this->monedas['ARS']->id,
            'precio'    => 500,
        ]);
        unset($datos['modo_precio_venta']);

        $this->comoAdmin()->post($this->urlTenant('admin/productos'), $datos)->assertRedirect();

        $producto = $this->enTenant(function () {
            return Producto::firstWhere('descripcion', 'Producto de prueba');
        });

        $this->assertNotNull($producto);
        $this->assertSame('500.00', (string) $producto->precio);
        $this->assertSame(Producto::MODO_PRECIO_MANUAL, $producto->modo_precio_venta);
    }

    /** @test */
    public function el_modo_margen_exige_los_datos_de_la_compra()
    {
        $this->comoAdmin()->post($this->urlTenant('admin/productos'), $this->datosDeProducto([
            'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
            'moneda_id'         => $this->monedas['ARS']->id,
        ]))->assertSessionHasErrors(['moneda_compra_id', 'precio_compra', 'margen_ganancia']);

        $this->enTenant(function () {
            $this->assertSame(0, Producto::count());
        });
    }

    /** @test */
    public function el_precio_con_moneda_de_venta_exige_elegir_la_moneda()
    {
        $this->comoAdmin()->post($this->urlTenant('admin/productos'), $this->datosDeProducto([
            'precio' => 999,
        ]))->assertSessionHasErrors('moneda_id');
    }

    /** @test */
    public function editar_un_producto_por_margen_recalcula_su_precio()
    {
        $producto = $this->crearProducto([
            'descripcion'       => 'Para editar',
            'moneda_id'         => $this->monedas['ARS']->id,
            'moneda_compra_id'  => $this->monedas['USD']->id,
            'precio_compra'     => 10,
            'margen_ganancia'   => 40,
            'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
        ]);

        $this->comoAdmin()->put($this->urlTenant('admin/productos/' . $producto->id), $this->datosDeProducto([
            'descripcion'       => 'Para editar',
            'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
            'moneda_id'         => $this->monedas['ARS']->id,
            'moneda_compra_id'  => $this->monedas['USD']->id,
            'precio_compra'     => 10,
            'margen_ganancia'   => 100,
        ]))->assertRedirect();

        $this->enTenant(function () use ($producto) {
            $this->assertSame('30000.00', (string) $producto->fresh()->precio);
        });
    }

    // ─── Cambio de cotización ────────────────────────────────────────────────

    /** @test */
    public function cambiar_la_cotizacion_recalcula_los_de_margen_y_no_toca_los_manuales()
    {
        $porMargen = $this->crearProducto([
            'descripcion'       => 'Por margen',
            'moneda_id'         => $this->monedas['ARS']->id,
            'moneda_compra_id'  => $this->monedas['USD']->id,
            'precio_compra'     => 10,
            'margen_ganancia'   => 40,
            'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
        ]);

        $fijo = $this->crearProducto([
            'descripcion' => 'Precio fijo',
            'moneda_id'   => $this->monedas['USD']->id,
            'precio'      => 777.77,
        ]);

        $this->assertSame('21000.00', (string) $porMargen->precio);

        $this->comoAdmin()->put($this->urlTenant('admin/monedas/' . $this->monedas['USD']->id), [
            'nombre'     => 'Dólar',
            'codigo'     => 'USD',
            'simbolo'    => 'U$S',
            'cotizacion' => 2000,
            'activa'     => 1,
        ])->assertRedirect(route('admin.monedas.index'));

        $this->enTenant(function () use ($porMargen, $fijo) {
            // 10 USD * 2000 = 20.000 ARS, + 40% = 28.000.
            $this->assertSame('28000.00', (string) $porMargen->fresh()->precio);
            $this->assertSame('777.77', (string) $fijo->fresh()->precio);
        });
    }

    /** @test */
    public function el_recalculo_informa_cuantos_productos_cambiaron()
    {
        foreach ([10, 20, 30] as $costo) {
            $this->crearProducto([
                'descripcion'       => 'Producto de ' . $costo,
                'moneda_id'         => $this->monedas['ARS']->id,
                'moneda_compra_id'  => $this->monedas['USD']->id,
                'precio_compra'     => $costo,
                'margen_ganancia'   => 0,
                'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
            ]);
        }

        $this->enTenant(function () {
            // Ya están al día: no hay nada que reescribir.
            $this->assertSame(0, Producto::recalcularPreciosEnMargen());

            $this->monedas['USD']->update(['cotizacion' => 1600]);

            $this->assertSame(3, Producto::recalcularPreciosEnMargen());
            $this->assertSame(0, Producto::recalcularPreciosEnMargen());
        });
    }

    /** @test */
    public function el_recalculo_se_puede_acotar_a_una_moneda()
    {
        // Los dos nacen con el precio en cero para que se vea cuál se reescribió.
        $enDolares = $this->enTenant(function () {
            return Producto::create([
                'proveedor_id'      => $this->proveedor->id,
                'descripcion'       => 'Comprado en dólares',
                'precio'            => 0,
                'moneda_id'         => $this->monedas['ARS']->id,
                'moneda_compra_id'  => $this->monedas['USD']->id,
                'precio_compra'     => 10,
                'margen_ganancia'   => 0,
                'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
                'stock'             => 1,
            ]);
        });

        $enEuros = $this->enTenant(function () {
            return Producto::create([
                'proveedor_id'      => $this->proveedor->id,
                'descripcion'       => 'Comprado en euros',
                'precio'            => 0,
                'moneda_id'         => $this->monedas['ARS']->id,
                'moneda_compra_id'  => $this->monedas['EUR']->id,
                'precio_compra'     => 10,
                'margen_ganancia'   => 0,
                'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
                'stock'             => 1,
            ]);
        });

        $this->enTenant(function () use ($enDolares, $enEuros) {
            $this->assertSame(1, Producto::recalcularPreciosEnMargen($this->monedas['EUR']->id));

            $this->assertSame('0.00', (string) $enDolares->fresh()->precio);
            $this->assertSame('16500.00', (string) $enEuros->fresh()->precio);
        });
    }

    // ─── Retrocompatibilidad y datos internos ────────────────────────────────

    /**
     * Un producto cargado con el esquema viejo -- precio y moneda de venta, sin nada
     * de compra -- tiene que seguir leyéndose y mostrándose igual, y quedar fuera de
     * cualquier recálculo.
     *
     * @test
     */
    public function un_producto_del_esquema_viejo_se_sigue_leyendo_igual()
    {
        $producto = $this->enTenant(function () {
            $id = DB::table('productos')->insertGetId([
                'proveedor_id' => $this->proveedor->id,
                'public_id'    => (string) Str::uuid(),
                'descripcion'  => 'Turron con Mani',
                'slug'         => 'turron-con-mani',
                'precio'       => 1500,
                'moneda_id'    => $this->monedas['ARS']->id,
                'stock'        => 10,
                'disponible'   => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            return Producto::find($id);
        });

        // El default de la columna lo deja en modo manual, sin datos de compra.
        $this->assertSame(Producto::MODO_PRECIO_MANUAL, $producto->modo_precio_venta);
        $this->assertNull($producto->precio_compra);
        $this->assertNull($producto->moneda_compra_id);

        $this->enTenant(function () use ($producto) {
            $this->assertSame('$1,500.00', $producto->precio_con_moneda);

            // Sin datos de compra, el recálculo masivo ni lo mira.
            $this->assertSame(0, Producto::recalcularPreciosEnMargen());
        });

        $this->get($this->urlTenant('producto/turron-con-mani/' . $producto->id))
            ->assertStatus(200)
            ->assertSee('$1,500.00', false);

        $this->enTenant(function () use ($producto) {
            $this->assertSame('1500.00', (string) $producto->fresh()->precio);
        });
    }

    /**
     * Cuánto cuesta el producto y cuánto se le gana no sale de la administración.
     *
     * @test
     */
    public function la_ficha_publica_no_expone_el_precio_de_compra_ni_el_margen()
    {
        $producto = $this->crearProducto([
            'descripcion'       => 'Producto con costo',
            'moneda_id'         => $this->monedas['ARS']->id,
            'moneda_compra_id'  => $this->monedas['USD']->id,
            'precio_compra'     => 1234.56,
            'margen_ganancia'   => 77,
            'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
        ]);

        // 1.234,56 USD * 1500 = 1.851.840 ARS, + 77% = 3.277.756,80.
        $this->assertSame('3277756.80', (string) $producto->precio);

        $serializado = $this->enTenant(function () use ($producto) {
            return $producto->toArray();
        });

        $this->assertArrayNotHasKey('precio_compra', $serializado);
        $this->assertArrayNotHasKey('margen_ganancia', $serializado);
        $this->assertArrayNotHasKey('moneda_compra_id', $serializado);
        $this->assertArrayNotHasKey('modo_precio_venta', $serializado);
        $this->assertArrayHasKey('precio', $serializado);

        $html = $this->get($this->urlTenant('producto/' . $producto->slugUrl() . '/' . $producto->id))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringNotContainsString('1234.56', $html);
        $this->assertStringNotContainsString('1,234.56', $html);
        $this->assertStringNotContainsString('precio_compra', $html);
        $this->assertStringNotContainsString('margen_ganancia', $html);

        // Y el precio de venta, que sí es público, está.
        $this->assertStringContainsString('3,277,756.80', $html);
    }
}
