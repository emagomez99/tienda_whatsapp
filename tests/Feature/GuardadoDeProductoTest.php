<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\GuardadoDeProducto;
use App\Support\DatosDeProducto;
use Illuminate\Validation\ValidationException;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * Las reglas de guardado de un producto, sin pasar por el formulario: así las va a
 * usar la importación desde Excel.
 */
class GuardadoDeProductoTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testguardado';
    const TENANT_DOMAIN = 'testguardado.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $moneda    = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true]);
            $proveedor = Proveedor::create(['nombre' => 'Proveedor']);
            $marca     = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);
            $color     = Etiqueta::create(['nombre' => 'Color', 'visible_usuarios' => true]);
            // Las reglas las pone la categoría (Etapa 2 del plan), no el proveedor.
            $ropa = Categoria::resolverCamino('Ropa', true);
            $ropa->caracteristicas()->sync([$marca->id => ['obligatoria' => true, 'orden' => 0], $color->id => ['obligatoria' => false, 'orden' => 1]]);

            $this->ids = ['moneda' => $moneda->id, 'proveedor' => $proveedor->id, 'marca' => $marca->id, 'color' => $color->id, 'ropa' => $ropa->id];
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function datos(array $extra = []): DatosDeProducto
    {
        return new DatosDeProducto($extra + [
            'proveedor_id'      => $this->ids['proveedor'],
            'descripcion'       => 'Remera básica',
            'precio'            => 1000,
            'moneda_id'         => $this->ids['moneda'],
            'modo_precio_venta' => Producto::MODO_PRECIO_MANUAL,
            'disponible'        => true,
            'por_encargue'      => false,
        ]);
    }

    private function servicio(): GuardadoDeProducto
    {
        return app(GuardadoDeProducto::class);
    }

    /** @test */
    public function crea_el_producto_con_el_stock_como_movimiento()
    {
        $this->enTenant(function () {
            $producto = $this->servicio()->crear(
                $this->datos()->conStockInicial(7)->conEtiquetas([$this->ids['marca'] => 'Nike']),
                null
            );

            $this->assertSame(7, (int) $producto->fresh()->stock);
            $this->assertSame(1, $producto->movimientos()->count());
            $this->assertSame('Nike', $producto->etiquetas()->first()->pivot->valor);
        });
    }

    /** @test */
    public function sin_una_obligatoria_no_guarda_nada()
    {
        $this->enTenant(function () {
            try {
                $this->servicio()->crear($this->datos()->conStockInicial(3)->conCategorias([$this->ids['ropa']])->conEtiquetas([$this->ids['color'] => 'Rojo']));
                $this->fail('Tenía que rechazarlo: falta Marca.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Marca', $e->errors()['etiquetas'][0]);
            }

            $this->assertSame(0, Producto::count());
        });
    }

    /**
     * Una planilla que no trae las columnas de etiquetas no las borra, y la regla
     * de obligatorias se controla contra las que el producto ya tiene.
     *
     * @test
     */
    public function actualizar_sin_etiquetas_no_las_toca()
    {
        $this->enTenant(function () {
            $producto = $this->servicio()->crear(
                $this->datos()->conCategorias([$this->ids['ropa']])
                    ->conEtiquetas([$this->ids['marca'] => 'Nike', $this->ids['color'] => 'Rojo'])
                    ->conEspecificaciones([['clave' => 'Peso', 'valor' => '200 g']])
            );

            // Sin categorías ni etiquetas en los datos: controla con las que ya tiene, y pasa.
            $this->servicio()->actualizar($producto, $this->datos(['descripcion' => 'Remera premium']));

            $producto->refresh();
            $this->assertSame('Remera premium', $producto->descripcion);
            $this->assertSame(2, $producto->etiquetas()->count());
            $this->assertSame(1, $producto->especificaciones()->count());
        });
    }

    /** @test */
    public function actualizar_con_listas_vacias_las_reemplaza()
    {
        $this->enTenant(function () {
            $producto = $this->servicio()->crear(
                $this->datos()->conEtiquetas([$this->ids['marca'] => 'Nike', $this->ids['color'] => 'Rojo'])
                    ->conEspecificaciones([['clave' => 'Peso', 'valor' => '200 g']])
            );

            $this->servicio()->actualizar(
                $producto,
                $this->datos()->conEtiquetas([$this->ids['marca'] => 'Nike'])->conEspecificaciones([])
            );

            $this->assertSame(1, $producto->etiquetas()->count());
            $this->assertSame(0, $producto->especificaciones()->count());
        });
    }

    /** @test */
    public function limpia_el_html_de_la_descripcion_y_no_deja_fijar_un_precio_derivado()
    {
        $this->enTenant(function () {
            $producto = $this->servicio()->crear($this->datos([
                'detalle'           => '<p>Hola</p><script>alert(1)</script>',
                'modo_precio_venta' => Producto::MODO_PRECIO_MARGEN,
                'moneda_compra_id'  => $this->ids['moneda'],
                'precio_compra'     => 100,
                'margen_ganancia'   => 50,
                'precio'            => 1,   // armado a mano: se ignora
            ])->conEtiquetas([$this->ids['marca'] => 'Nike']));

            $this->assertStringNotContainsString('<script>', $producto->detalle);
            $this->assertEquals(150, $producto->fresh()->precio);
        });
    }

    /** @test */
    public function sin_categorias_en_los_datos_valen_las_que_ya_tiene()
    {
        $this->enTenant(function () {
            $producto = $this->servicio()->crear(
                $this->datos()->conCategorias([$this->ids['ropa']])->conEtiquetas([$this->ids['marca'] => 'Nike'])
            );

            $this->expectException(ValidationException::class);
            // Sigue en Ropa: sacarle la Marca no se puede.
            $this->servicio()->actualizar($producto, $this->datos()->conEtiquetas([]));
        });
    }

    /** @test */
    public function una_global_obligatoria_se_pide_aunque_no_tenga_categoria()
    {
        $this->enTenant(function () {
            Etiqueta::find($this->ids['color'])->update(['global' => true]);
            Categoria::find($this->ids['ropa'])->caracteristicas()->updateExistingPivot($this->ids['color'], ['obligatoria' => true]);

            // Global y opcional: sin categoría no se exige.
            $this->servicio()->crear($this->datos());
            $this->assertSame(1, Producto::count());

            // Global y obligatoria: se exige aunque no tenga categoría.
            Etiqueta::find($this->ids['marca'])->update(['global' => true, 'obligatoria_global' => true]);
            try {
                $this->servicio()->crear($this->datos());
                $this->fail('Tenía que pedir Marca.');
            } catch (ValidationException $e) {
                $this->assertSame('Falta una característica obligatoria: Marca.', $e->errors()['etiquetas'][0]);
            }

            // En Ropa, Color pasa a obligatoria (manda la categoría, más cercana que la global).
            try {
                $this->servicio()->crear($this->datos()->conCategorias([$this->ids['ropa']])->conEtiquetas([$this->ids['marca'] => 'Nike']));
                $this->fail('Tenía que pedir Color.');
            } catch (ValidationException $e) {
                $this->assertSame('Falta una característica obligatoria: Color.', $e->errors()['etiquetas'][0]);
            }
        });
    }
}
