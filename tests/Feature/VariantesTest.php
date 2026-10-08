<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Pedido;
use App\Models\PedidoProducto;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Models\Variante;
use Database\Seeders\PermisoSeeder;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** Etapa 4: variantes con stock propio (Talle M, Color Rojo), del panel al pedido. */
class VariantesTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testvariantes';
    const TENANT_DOMAIN = 'testvariantes.test';

    /** @var array */
    private $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
        $this->enTenant(function () {
            (new PermisoSeeder())->run();
            $this->ids['proveedor'] = Proveedor::create(['nombre' => 'Textil'])->id;
            $this->ids['moneda'] = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id;
            $this->ids['talle'] = Etiqueta::create(['nombre' => 'Talle', 'visible_usuarios' => true, 'varios_valores' => true])->id;
            $this->ids['color'] = Etiqueta::create(['nombre' => 'Color', 'visible_usuarios' => true, 'varios_valores' => true])->id;
            $this->ids['marca'] = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true])->id;
            $this->ids['remeras'] = Categoria::resolverCamino('Remeras', true)->id;
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function admin()
    {
        $usuario = $this->enTenant(function () {
            return User::firstOrCreate(['email' => 'duenio@' . self::TENANT_DOMAIN], ['name' => 'Dueño', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]);
        });

        return $this->actingAs($usuario);
    }

    /** Lo que manda el formulario de producto, con talles S y M y estas variantes. */
    private function formulario(array $variantes, array $extra = []): array
    {
        return $extra + [
            'proveedor_id' => $this->ids['proveedor'], 'descripcion' => 'Remera lisa', 'precio' => 1000,
            'moneda_id' => $this->ids['moneda'], 'disponible' => 1, 'stock' => 0,
            'categorias' => [$this->ids['remeras']],
            'etiquetas' => [
                ['etiqueta_id' => $this->ids['talle'], 'valor' => 'S'],
                ['etiqueta_id' => $this->ids['talle'], 'valor' => 'M'],
            ],
            'variantes_enviadas' => 1,
            'usa_variantes' => $variantes ? 1 : 0,
            'variantes' => $variantes,
        ];
    }

    private function variante(string $talle, int $stock, array $extra = []): array
    {
        return $extra + ['valores' => [$this->ids['talle'] => $talle], 'stock' => $stock, 'activa' => 1];
    }

    private function crearRemera(): Producto
    {
        $this->admin()->post($this->urlTenant('admin/productos'), $this->formulario([
            $this->variante('S', 2),
            $this->variante('M', 3, ['precio' => 1200, 'codigo' => 'REM-M']),
        ]))->assertSessionHasNoErrors()->assertRedirect();

        return $this->enTenant(function () { return Producto::where('descripcion', 'Remera lisa')->firstOrFail(); });
    }

    private function varianteDe(Producto $producto, string $talle): Variante
    {
        return $this->enTenant(function () use ($producto, $talle) {
            return Variante::where('producto_id', $producto->id)->get()->first(function (Variante $v) use ($talle) {
                return $v->nombre() === $talle;
            });
        });
    }

    /** @test */
    public function el_alta_crea_las_variantes_con_su_stock_y_el_producto_suma()
    {
        $producto = $this->crearRemera();

        $this->enTenant(function () use ($producto) {
            $this->assertSame(5, (int) $producto->stock);
            $this->assertSame(['S', 'M'], $producto->variantes()->get()->map->nombre()->all());
            $this->assertSame(2, StockMovimiento::where('producto_id', $producto->id)->whereNotNull('variante_id')->count());
            $this->assertSame(0, StockMovimiento::where('producto_id', $producto->id)->whereNull('variante_id')->count());
            $this->assertSame('1200.00', Variante::where('codigo', 'REM-M')->value('precio'));

            // El producto "tiene" esos talles: aparece al filtrar por M.
            $this->assertTrue(Producto::conEtiqueta($this->ids['talle'], 'M')->whereKey($producto->id)->exists());
        });

        // La edición muestra la sección prendida, con sus filas.
        $this->get($this->urlTenant('admin/productos/' . $producto->id . '/edit'))->assertOk()
            ->assertSee('id="usa_variantes" name="usa_variantes" value="1" checked', false)
            ->assertSee('"codigo":"REM-M"', false);
    }

    /** @test */
    public function editar_ajusta_el_stock_con_movimientos_y_una_apagada_no_suma()
    {
        $producto = $this->crearRemera();
        $s = $this->varianteDe($producto, 'S');
        $m = $this->varianteDe($producto, 'M');

        $this->admin()->put($this->urlTenant('admin/productos/' . $producto->id), $this->formulario([
            $this->variante('S', 2, ['id' => $s->id, 'activa' => 0]),
            $this->variante('M', 7, ['id' => $m->id]),
        ]))->assertSessionHasNoErrors();

        $this->enTenant(function () use ($producto, $m) {
            $this->assertSame(7, (int) Producto::find($producto->id)->stock);   // S apagada: no suma
            $mov = StockMovimiento::where('variante_id', $m->id)->latest('id')->first();
            $this->assertSame(4, $mov->variacion);
            $this->assertSame(7, $mov->stock_resultante);
            $this->assertSame(StockMovimiento::TIPO_AJUSTE_MANUAL, $mov->tipo);
        });
    }

    /** @test */
    public function el_stock_que_tenia_el_producto_pasa_a_las_variantes_y_al_apagarlas_se_van()
    {
        $producto = $this->enTenant(function () {
            $p = Producto::create(['proveedor_id' => $this->ids['proveedor'], 'descripcion' => 'Remera lisa', 'precio' => 1000,
                'moneda_id' => $this->ids['moneda'], 'disponible' => true, 'stock' => 0]);
            $p->registrarMovimiento(10, StockMovimiento::TIPO_AJUSTE_INICIAL);
            return $p;
        });

        $this->admin()->put($this->urlTenant('admin/productos/' . $producto->id), $this->formulario([
            $this->variante('S', 4), $this->variante('M', 6),
        ]))->assertSessionHasNoErrors();

        $this->enTenant(function () use ($producto) {
            $this->assertSame(10, (int) Producto::find($producto->id)->stock);
            $this->assertSame(-10, StockMovimiento::where('producto_id', $producto->id)->whereNull('variante_id')->latest('id')->value('variacion'));
        });

        // Apagar "El cliente elige": se borran las variantes y su stock vuelve al producto.
        $this->put($this->urlTenant('admin/productos/' . $producto->id), $this->formulario([]))->assertSessionHasNoErrors();

        $this->enTenant(function () use ($producto) {
            $this->assertSame(0, Variante::where('producto_id', $producto->id)->count());
            $this->assertSame(10, (int) Producto::find($producto->id)->stock);
            $this->assertSame(10, StockMovimiento::where('producto_id', $producto->id)->latest('id')->value('variacion'));
            // Y vuelve a moverse como un producto común.
            Producto::find($producto->id)->registrarMovimiento(3, StockMovimiento::TIPO_AJUSTE_MANUAL);
        });

        // El historial sigue diciendo de qué talle fue cada movimiento, aunque ya no existan.
        $this->get($this->urlTenant('admin/productos/' . $producto->id . '/historial'))->assertOk()
            ->assertSee('Talle: S')->assertSee('Talle: M')
            ->assertSee('data-bs-target="#modal-ajuste-stock"', false);   // sin variantes, se ajusta de nuevo
    }

    /** @test */
    public function con_precios_distintos_la_tienda_dice_desde_y_el_historial_no_ofrece_ajustar_el_total()
    {
        $producto = $this->crearRemera();   // S al precio del producto (1.000), M a 1.200

        $this->get($this->urlTenant('categoria/remeras'))->assertSee('Desde $1.000,00');
        $this->get($this->urlTenant('producto/' . $producto->slugUrl() . '/' . $producto->id))->assertSee('Desde $1.000,00');

        $this->admin()->get($this->urlTenant('admin/productos/' . $producto->id . '/historial'))->assertOk()
            ->assertSee('Talle: M')
            ->assertDontSee('data-bs-target="#modal-ajuste-stock"', false);
    }

    /** @test */
    public function reglas_de_las_variantes()
    {
        $this->crearRemera();

        // Por una característica de un solo valor no se elige.
        $this->admin()->post($this->urlTenant('admin/productos'), $this->formulario(
            [['valores' => [$this->ids['marca'] => 'Topper'], 'stock' => 1]],
            ['descripcion' => 'Otra']
        ))->assertSessionHasErrors('variantes');

        // La misma combinación dos veces.
        $this->post($this->urlTenant('admin/productos'), $this->formulario(
            [$this->variante('S', 1), $this->variante('s', 1)], ['descripcion' => 'Otra']
        ))->assertSessionHasErrors('variantes');

        // Un código que ya usa otra variante.
        $this->post($this->urlTenant('admin/productos'), $this->formulario(
            [$this->variante('S', 1, ['codigo' => 'rem-m'])], ['descripcion' => 'Otra']
        ))->assertSessionHasErrors('variantes');

        // Ni el código de un producto puede ser el de una variante.
        $this->post($this->urlTenant('admin/productos'), $this->formulario([], ['descripcion' => 'Otra', 'id_proveedor' => 'REM-M']))
            ->assertSessionHasErrors('id_proveedor');

        $this->assertSame(1, $this->enTenant(function () { return Producto::count(); }));
    }

    /** @test */
    public function en_la_tienda_se_elige_la_variante_y_el_pedido_descuenta_su_stock()
    {
        $producto = $this->crearRemera();
        $m = $this->varianteDe($producto, 'M');
        auth()->logout();

        // La ficha tiene los botones; la grilla manda a elegir.
        $this->get($this->urlTenant('producto/' . $producto->slugUrl() . '/' . $producto->id))->assertOk()
            ->assertSee('opcion-variante', false)->assertSee('name="variante_id"', false);
        $this->get($this->urlTenant('categoria/remeras'))->assertSee('Elegir opciones');

        $agregar = $this->urlTenant('carrito/agregar/' . $producto->id);
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        // Sin elegir, no.
        $this->postJson($agregar, ['cantidad' => 1], $ajax)->assertJson(['success' => false]);

        // M tiene 3: pedir 5 se ajusta.
        $this->postJson($agregar, ['cantidad' => 5, 'variante_id' => $m->id], $ajax)->assertJson(['success' => true, 'warning' => true]);
        $this->assertSame([$producto->id . '-' . $m->id => 3], session('carrito'));

        $this->get($this->urlTenant('carrito'))->assertOk()->assertSee('REM-M')->assertSee('3.600,00');

        $this->post($this->urlTenant('carrito/enviar'), [
            'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => 'ana@ejemplo.com', 'celular' => '1155555555',
            'direccion' => 'Calle 1', 'localidad' => 'CABA', 'provincia' => 'BA', 'cp' => '1000',
        ])->assertRedirect();

        $item = $this->enTenant(function () { return PedidoProducto::latest('id')->first(); });
        $this->assertSame($m->id, (int) $item->variante_id);
        $this->assertSame('Remera lisa (M)', $item->descripcion);
        $this->assertSame('1200.00', $item->precio_unitario);

        // Confirmar descuenta de M; cancelar lo devuelve a M.
        $this->admin()->post($this->urlTenant('admin/pedidos/' . $item->pedido_id . '/confirmar'))->assertRedirect();
        $this->enTenant(function () use ($m, $producto) {
            $this->assertSame(0, (int) Variante::find($m->id)->stock);
            $this->assertSame(2, (int) Producto::find($producto->id)->stock);
        });

        $this->post($this->urlTenant('admin/pedidos/' . $item->pedido_id . '/cancelar'))->assertRedirect();
        $this->enTenant(function () use ($m, $producto) {
            $this->assertSame(3, (int) Variante::find($m->id)->stock);
            $this->assertSame(5, (int) Producto::find($producto->id)->stock);
        });
    }

    /** @test */
    public function en_el_panel_cada_variante_se_busca_y_se_agrega_al_pedido()
    {
        $producto = $this->crearRemera();
        $s = $this->varianteDe($producto, 'S');

        $resultados = $this->admin()->getJson($this->urlTenant('admin/productos/buscar?q=remera'))->assertOk()->json();
        $this->assertSame(['Remera lisa (S)', 'Remera lisa (M)'], array_column($resultados, 'descripcion'));

        $pedido = $this->enTenant(function () { return Pedido::create(['nombre' => 'Ana', 'apellido' => '', 'email' => '', 'celular' => '', 'direccion' => '', 'localidad' => '', 'provincia' => '', 'cp' => '', 'estado' => 'pendiente']); });
        $url = $this->urlTenant('admin/pedidos/' . $pedido->id . '/productos');

        // Sin decir cuál, no se agrega.
        $this->post($url, ['producto_id' => $producto->id, 'cantidad' => 1])->assertSessionHas('error');
        $this->post($url, ['producto_id' => $producto->id, 'variante_id' => $s->id, 'cantidad' => 2])->assertSessionHas('success');
        // S tiene 2: un tercero no entra.
        $this->post($url, ['producto_id' => $producto->id, 'variante_id' => $s->id, 'cantidad' => 1])->assertSessionHas('error');

        $this->enTenant(function () use ($pedido, $s) {
            $item = $pedido->items()->first();
            $this->assertSame($s->id, (int) $item->variante_id);
            $this->assertSame(2, $item->cantidad);
        });
    }

    /** @test */
    public function el_stock_de_un_producto_con_variantes_no_se_mueve_entero()
    {
        $producto = $this->crearRemera();

        $this->admin()->post($this->urlTenant('admin/productos/' . $producto->id . '/ajustar-stock'), [
            'tipo' => 'ingreso', 'cantidad' => 5, 'descripcion' => 'Compra',
        ])->assertSessionHas('error');

        $this->enTenant(function () use ($producto) {
            $this->assertSame(5, (int) Producto::find($producto->id)->stock);
            $this->expectException(\LogicException::class);
            Producto::find($producto->id)->registrarMovimiento(1, StockMovimiento::TIPO_AJUSTE_MANUAL);
        });
    }
}
