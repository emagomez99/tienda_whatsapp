<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Variante;
use Database\Seeders\PermisoSeeder;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * Lo que pasa al ir sacando cosas de un producto con variantes (prueba de punta a
 * punta en el navegador, oct-2026): quitar una propiedad, apagar una variante,
 * borrar la opción que alguien tenía en el carrito o en un pedido confirmado.
 */
class VariantesAlQuitarTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testvariantesquitar';
    const TENANT_DOMAIN = 'testvariantesquitar.test';

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
            $this->ids['ropa'] = Categoria::resolverCamino('Ropa', true)->id;
            $this->ids['remeras'] = Categoria::resolverCamino('Ropa > Remeras', true)->id;
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

    private function formulario(array $variantes, array $extra = []): array
    {
        return $extra + [
            'proveedor_id' => $this->ids['proveedor'], 'descripcion' => 'Buzo', 'precio' => 1000,
            'moneda_id' => $this->ids['moneda'], 'disponible' => 1, 'stock' => 0,
            'categorias' => [$this->ids['remeras']],
            'etiquetas' => [],
            'variantes_enviadas' => 1,
            'variantes' => $variantes,
        ];
    }

    /** Un buzo en S y M, Rojo y Azul (4 variantes, 1 de cada una). */
    private function crearBuzo(): Producto
    {
        $variantes = [];
        foreach (['S', 'M'] as $talle) {
            foreach (['Rojo', 'Azul'] as $color) {
                $variantes[] = ['valores' => ['Talle' => $talle, 'Color' => $color], 'stock' => 1, 'activa' => 1];
            }
        }
        $this->admin()->post($this->urlTenant('admin/productos'), $this->formulario($variantes))->assertSessionHasNoErrors();

        return $this->enTenant(function () { return Producto::where('descripcion', 'Buzo')->firstOrFail(); });
    }

    private function variantes(Producto $producto)
    {
        return $this->enTenant(function () use ($producto) {
            return Variante::where('producto_id', $producto->id)->with('valores')->orderBy('orden')->get()
                ->keyBy(function (Variante $v) { return $v->nombre(); });
        });
    }

    /** @test */
    public function quitar_una_propiedad_la_saca_tambien_de_las_caracteristicas_del_producto()
    {
        $producto = $this->crearBuzo();
        $v = $this->variantes($producto);

        // Sin Color: S y M, cada una sigue con su id y junta el stock de sus colores
        // (lo que hace el formulario). Un formulario viejo que todavía manda
        // "Color: Rojo, Azul" como característica no lo deja colgado.
        $this->put($this->urlTenant('admin/productos/' . $producto->id), $this->formulario([
            ['id' => $v['S · Rojo']->id, 'valores' => ['Talle' => 'S'], 'stock' => 2, 'activa' => 1],
            ['id' => $v['M · Rojo']->id, 'valores' => ['Talle' => 'M'], 'stock' => 2, 'activa' => 1],
        ], ['etiquetas' => [
            ['etiqueta_id' => $this->ids['color'], 'valor' => 'Rojo'],
            ['etiqueta_id' => $this->ids['color'], 'valor' => 'Azul'],
        ]]))->assertSessionHasNoErrors();

        $this->enTenant(function () use ($producto) {
            $this->assertSame(4, (int) Producto::find($producto->id)->stock);
            $this->assertFalse(Producto::conEtiqueta($this->ids['color'], 'Rojo')->whereKey($producto->id)->exists());
            $this->assertTrue(Producto::conEtiqueta($this->ids['talle'], 'M')->whereKey($producto->id)->exists());
        });

        // Y sin ninguna propiedad, tampoco queda "Talle: S, M" sin talles para elegir.
        $this->put($this->urlTenant('admin/productos/' . $producto->id), $this->formulario([]))->assertSessionHasNoErrors();
        $this->enTenant(function () use ($producto) {
            $this->assertSame(4, (int) Producto::find($producto->id)->stock);
            $this->assertSame([], Producto::find($producto->id)->valoresDeCaracteristicas());
        });
    }

    /** @test */
    public function una_propiedad_nueva_escrita_sin_tilde_usa_la_que_ya_existe()
    {
        $existente = $this->enTenant(function () {
            return Etiqueta::create(['nombre' => 'Presentación', 'visible_usuarios' => true])->id;
        });

        // "+ Nueva propiedad" → "presentacion": no se crea otra "Presentacion".
        $this->admin()->post($this->urlTenant('admin/productos'), $this->formulario([
            ['valores' => ['presentacion' => '250 ml'], 'stock' => 1, 'activa' => 1],
        ]))->assertSessionHasNoErrors();

        $this->enTenant(function () use ($existente) {
            $this->assertSame(1, Etiqueta::where('nombre', 'ilike', 'presentaci%')->count());
            $this->assertSame([$existente], Variante::first()->valores->pluck('pivot.etiqueta_id')->map(function ($id) { return (int) $id; })->all());
        });
    }

    /** @test */
    public function una_variante_que_no_se_vende_no_cuenta_como_valor_del_producto()
    {
        $this->admin()->post($this->urlTenant('admin/productos'), $this->formulario([
            ['valores' => ['Talle' => 'S'], 'stock' => 1, 'activa' => 1],
            ['valores' => ['Talle' => 'L'], 'stock' => 1, 'activa' => 0],
        ]))->assertSessionHasNoErrors();

        $this->enTenant(function () {
            $producto = Producto::where('descripcion', 'Buzo')->firstOrFail();
            $this->assertSame(['S'], $producto->valoresDeCaracteristicas()[$this->ids['talle']]);
            $this->assertFalse(Producto::conEtiqueta($this->ids['talle'], 'L')->whereKey($producto->id)->exists());
        });
    }

    /** @test */
    public function el_historial_dice_el_nombre_que_tenia_la_variante_y_en_el_orden_en_que_paso()
    {
        $producto = $this->crearBuzo();
        $v = $this->variantes($producto);

        // A "S · Rojo" se le quita el Color: pasa a ser "S", pero lo que se movió antes fue de "S · Rojo".
        $this->put($this->urlTenant('admin/productos/' . $producto->id), $this->formulario([
            ['id' => $v['S · Rojo']->id, 'valores' => ['Talle' => 'S'], 'stock' => 2, 'activa' => 1],
            ['id' => $v['M · Rojo']->id, 'valores' => ['Talle' => 'M'], 'stock' => 2, 'activa' => 1],
        ]))->assertSessionHasNoErrors();
        // Y después deja de tener variantes: el stock vuelve al producto.
        $this->put($this->urlTenant('admin/productos/' . $producto->id), $this->formulario([]))->assertSessionHasNoErrors();

        $html = $this->get($this->urlTenant('admin/productos/' . $producto->id . '/historial'))->assertOk()
            ->assertSee('Talle: S · Color: Rojo')
            ->assertSee('Cómo leer el historial')
            ->getContent();

        // Lo último que pasó va primero, aunque todo sea del mismo segundo.
        $this->assertLessThan(strpos($html, 'Se quitó la variante'), strpos($html, 'El stock de las variantes vuelve al producto'));
    }

    /** @test */
    public function el_carrito_avisa_si_la_opcion_elegida_ya_no_existe()
    {
        $producto = $this->crearBuzo();
        $v = $this->variantes($producto);
        auth()->logout();

        $this->withSession(['carrito' => [$producto->id . '-' . $v['M · Azul']->id => 1, $producto->id . '-' . $v['S · Rojo']->id => 1]]);
        $this->enTenant(function () use ($v) { Variante::destroy($v['M · Azul']->id); });

        $this->get($this->urlTenant('carrito'))->assertOk()
            ->assertSee('La opción de &quot;Buzo&quot; que elegiste ya no está a la venta', false);
        $this->assertCount(1, session('carrito'));
    }

    /** @test */
    public function cancelar_avisa_si_el_stock_no_tiene_adonde_volver()
    {
        $producto = $this->crearBuzo();
        $v = $this->variantes($producto);

        $pedido = $this->enTenant(function () use ($producto, $v) {
            $pedido = Pedido::create(['nombre' => 'Ana', 'apellido' => '', 'email' => '', 'celular' => '', 'direccion' => '', 'localidad' => '', 'provincia' => '', 'cp' => '', 'estado' => 'pendiente']);
            $pedido->agregarProducto($producto, 1, $v['M · Azul']);
            $pedido->agregarProducto($producto, 1, $v['S · Rojo']);
            return $pedido;
        });
        $this->post($this->urlTenant('admin/pedidos/' . $pedido->id . '/confirmar'))->assertSessionHas('success');

        // Al buzo se le quita "M · Azul" (se destildó en la edición).
        $this->put($this->urlTenant('admin/productos/' . $producto->id), $this->formulario([
            ['id' => $v['S · Rojo']->id, 'valores' => ['Talle' => 'S', 'Color' => 'Rojo'], 'stock' => 0, 'activa' => 1],
            ['id' => $v['S · Azul']->id, 'valores' => ['Talle' => 'S', 'Color' => 'Azul'], 'stock' => 1, 'activa' => 1],
            ['id' => $v['M · Rojo']->id, 'valores' => ['Talle' => 'M', 'Color' => 'Rojo'], 'stock' => 1, 'activa' => 1],
        ]))->assertSessionHasNoErrors();

        $this->post($this->urlTenant('admin/pedidos/' . $pedido->id . '/cancelar'))
            ->assertSessionHas('success')
            ->assertSessionHas('warning', function ($aviso) { return strpos($aviso, 'Buzo (M · Azul)') !== false; });

        // Lo de "S · Rojo" sí volvió.
        $this->enTenant(function () use ($v) {
            $this->assertSame(1, (int) Variante::find($v['S · Rojo']->id)->stock);
        });

        // Lo mismo si lo que se borró es el producto entero.
        $otro = $this->enTenant(function () {
            $gorra = Producto::create(['proveedor_id' => $this->ids['proveedor'], 'descripcion' => 'Gorra', 'precio' => 500,
                'moneda_id' => $this->ids['moneda'], 'disponible' => true, 'stock' => 3]);
            $pedido = Pedido::create(['nombre' => 'Beto', 'apellido' => '', 'email' => '', 'celular' => '', 'direccion' => '', 'localidad' => '', 'provincia' => '', 'cp' => '', 'estado' => 'pendiente']);
            $pedido->agregarProducto($gorra, 1);
            return ['pedido' => $pedido->id, 'gorra' => $gorra->id];
        });
        $this->post($this->urlTenant('admin/pedidos/' . $otro['pedido'] . '/confirmar'))->assertSessionHas('success');
        $this->delete($this->urlTenant('admin/productos/' . $otro['gorra']))->assertRedirect();
        // Y el panel lo muestra (antes el panel no mostraba los avisos amarillos).
        $this->followingRedirects()->post($this->urlTenant('admin/pedidos/' . $otro['pedido'] . '/cancelar'))
            ->assertOk()->assertSee('No se devolvió el stock de &quot;Gorra&quot;', false);
    }

    /** @test */
    public function la_categoria_no_muestra_destacados_que_ya_no_son_suyos()
    {
        $this->enTenant(function () {
            $remera = Producto::create(['proveedor_id' => $this->ids['proveedor'], 'descripcion' => 'Remera viajera', 'precio' => 1000,
                'moneda_id' => $this->ids['moneda'], 'disponible' => true, 'stock' => 1]);
            $remera->categorias()->attach($this->ids['remeras']);
            Categoria::find($this->ids['ropa'])->destacar([$remera->id]);
            // Se borra «Remeras»: la remera deja de estar en «Ropa».
            $remera->categorias()->detach();
        });

        $this->admin()->get($this->urlTenant('admin/categorias/' . $this->ids['ropa'] . '/edit'))->assertOk()
            ->assertDontSee('Remera viajera');
    }

    /** @test */
    public function los_avisos_de_borrar_dicen_que_pasa_en_palabras_simples()
    {
        $this->enTenant(function () {
            $p = Producto::create(['proveedor_id' => $this->ids['proveedor'], 'descripcion' => 'Gorra', 'precio' => 1000,
                'moneda_id' => $this->ids['moneda'], 'disponible' => true, 'stock' => 1]);
            $p->categorias()->attach($this->ids['remeras']);
        });

        $this->admin()->get($this->urlTenant('admin/categorias'))->assertOk()
            ->assertSee('Su producto no se borra')->assertDontSee('Sus 1 producto');
        $this->get($this->urlTenant('admin/productos'))->assertOk()
            ->assertSee('¿Eliminar «Gorra»?', false);
        // Una característica sin valores no dice "sus 0 valores… de 0 productos".
        $this->get($this->urlTenant('admin/etiquetas'))->assertOk()
            ->assertSee('No tiene valores: ningún producto la usa.')->assertDontSee('sus 0 valores');
    }
}
