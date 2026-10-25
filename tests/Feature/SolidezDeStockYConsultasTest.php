<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PedidoController;
use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Moneda;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Variante;
use App\Services\VariantesDeProducto;
use App\Support\OrdenDeProductos;
use Database\Seeders\PermisoSeeder;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * Revisión de base de datos y solidez (oct-2026): que las pantallas no hagan una
 * consulta por producto, y que el stock no se descuente ni se devuelva dos veces ni
 * quede a medias cuando dos cosas pasan a la vez.
 */
class SolidezDeStockYConsultasTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testsolidez';
    const TENANT_DOMAIN = 'testsolidez.test';

    /** @var array */
    private $ids = [];

    /** @var int consultas desde el último contar() */
    private $consultas = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
        $this->enTenant(function () {
            (new PermisoSeeder())->run();
            $this->ids['proveedor'] = Proveedor::create(['nombre' => 'Textil'])->id;
            $this->ids['moneda'] = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id;
            $this->ids['remeras'] = Categoria::resolverCamino('Remeras', true)->id;
        });

        DB::listen(function () { $this->consultas++; });
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

    /** Un producto suelto (sin variantes), en Remeras. */
    private function producto(string $nombre, int $stock = 10): Producto
    {
        $producto = Producto::create([
            'proveedor_id' => $this->ids['proveedor'], 'descripcion' => $nombre, 'precio' => 1000,
            'moneda_id' => $this->ids['moneda'], 'disponible' => true, 'stock' => $stock,
        ]);
        $producto->categorias()->sync([$this->ids['remeras']]);

        return $producto;
    }

    /** Un producto con talles S (más barato) y M. */
    private function conTalles(string $nombre, int $stockS = 2, int $stockM = 3): Producto
    {
        $producto = $this->producto($nombre, 0);
        (new VariantesDeProducto())->sincronizar($producto, [
            ['valores' => ['Talle' => 'S'], 'stock' => $stockS, 'precio' => 900],
            ['valores' => ['Talle' => 'M'], 'stock' => $stockM],
        ]);

        return $producto->fresh();
    }

    private function nuevoPedido(): Pedido
    {
        return Pedido::create(['nombre' => 'Ana', 'apellido' => '', 'email' => '', 'celular' => '', 'direccion' => '', 'localidad' => '', 'provincia' => '', 'cp' => '', 'estado' => 'pendiente']);
    }

    /** Las consultas que hace $pedido, con la caché de ajustes ya cargada (la primera vez lee de la base). */
    private function consultasDe(callable $pedido): int
    {
        $pedido();
        $this->consultas = 0;
        $pedido();

        return $this->consultas;
    }

    /** @test */
    public function la_grilla_no_hace_una_consulta_por_producto_con_variantes()
    {
        $this->enTenant(function () {
            $this->conTalles('Remera A');
            $this->conTalles('Remera B');
        });
        $dos = $this->consultasDe(function () {
            $this->get($this->urlTenant('categoria/remeras'))->assertOk()->assertSee('Desde $900,00');
        });

        $this->enTenant(function () {
            foreach (['C', 'D', 'E', 'F'] as $letra) {
                $this->conTalles('Remera ' . $letra);
            }
        });
        $seis = $this->consultasDe(function () {
            $this->get($this->urlTenant('categoria/remeras'))->assertOk()->assertSee('Elegir opciones');
        });

        $this->assertSame($dos, $seis, 'Cada producto con variantes sumaba consultas a la grilla.');

        // Fuera de la grilla, el producto suelto sigue sabiendo su resumen.
        $this->enTenant(function () {
            $remera = Producto::where('descripcion', 'Remera A')->first();
            $this->assertSame(2, $remera->variantes_activas);
            $this->assertSame('Desde $900,00', $remera->precioParaMostrar());
            $this->assertSame(0, Producto::where('descripcion', 'Suelto')->firstOrNew([])->variantes_activas);
        });
    }

    /** @test */
    public function el_carrito_no_pregunta_por_variantes_producto_por_producto()
    {
        $ids = $this->enTenant(function () {
            $ids = [];
            foreach (range(1, 6) as $n) {
                $ids[] = $this->producto('Gorra ' . $n)->id;
            }

            return $ids;
        });

        $carritoDe = function (array $ids) {
            return array_fill_keys(array_map('strval', $ids), 1);
        };

        $dos = $this->consultasDe(function () use ($ids, $carritoDe) {
            $this->withSession(['carrito' => $carritoDe(array_slice($ids, 0, 2))])->get($this->urlTenant('carrito'))->assertOk();
        });
        $seis = $this->consultasDe(function () use ($ids, $carritoDe) {
            $this->withSession(['carrito' => $carritoDe($ids)])->get($this->urlTenant('carrito'))->assertOk();
        });

        $this->assertSame($dos, $seis);
    }

    /** @test */
    public function un_ajuste_vacio_tambien_queda_en_cache()
    {
        $this->enTenant(function () {
            Configuracion::establecer('logo', null);
            Configuracion::obtener('logo');

            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->assertNull(Configuracion::obtener('logo'));
            $this->assertSame('x', Configuracion::obtener('no_existe_todavia', 'x'));
            $this->assertSame('x', Configuracion::obtener('no_existe_todavia', 'x'));
            // logo: de la caché; no_existe_todavia: una sola vez.
            $this->assertCount(1, DB::getQueryLog());
            DB::disableQueryLog();

            // Un ajuste que no existe toma el valor por defecto de quien pregunta.
            $this->assertSame('y', Configuracion::obtener('no_existe_todavia', 'y'));
        });
    }

    /** @test */
    public function ordenar_por_precio_pasa_las_monedas_a_la_base_y_manual_respeta_los_destacados()
    {
        $this->enTenant(function () {
            $dolar = Moneda::create(['nombre' => 'Dólar', 'codigo' => 'USD', 'simbolo' => 'US$', 'cotizacion' => 1000]);
            $barato = $this->producto('Barato');                       // $1.000
            $caro = $this->producto('Caro en dólares');                // US$ 5 = $5.000
            $caro->update(['precio' => 5, 'moneda_id' => $dolar->id]);
            $medio = $this->producto('Medio');
            $medio->update(['precio' => 3000]);

            $categoria = Categoria::find($this->ids['remeras']);
            $categoria->update(['orden_productos' => OrdenDeProductos::PRECIO_MENOR]);
            $nombres = function () use ($categoria) {
                $q = Producto::enCategoria($categoria);
                OrdenDeProductos::deCategoria($categoria->fresh())->aplicar($q);

                return $q->pluck('descripcion')->all();
            };
            $this->assertSame(['Barato', 'Medio', 'Caro en dólares'], $nombres());

            $categoria->update(['orden_productos' => OrdenDeProductos::MANUAL]);
            DB::table('categoria_destacados')->insert([
                ['categoria_id' => $categoria->id, 'producto_id' => $medio->id, 'posicion' => 1],
                ['categoria_id' => $categoria->id, 'producto_id' => $caro->id, 'posicion' => 2],
            ]);
            $this->assertSame(['Medio', 'Caro en dólares', 'Barato'], $nombres());
        });
    }

    /** @test */
    public function confirmar_o_cancelar_dos_veces_no_mueve_el_stock_dos_veces()
    {
        [$pedidoId, $productoId] = $this->enTenant(function () {
            $producto = $this->producto('Gorra', 10);
            $pedido = $this->nuevoPedido();
            $pedido->agregarProducto($producto, 3);

            return [$pedido->id, $producto->id];
        });

        // Lo que ve el segundo clic: el pedido como estaba antes de que el primero lo confirme.
        $viejo = $this->enTenant(function () use ($pedidoId) { return Pedido::find($pedidoId); });

        $this->admin()->post($this->urlTenant('admin/pedidos/' . $pedidoId . '/confirmar'))->assertSessionHas('success');
        $this->enTenant(function () use ($viejo) {
            app(PedidoController::class)->confirmar($viejo);
        });
        $this->assertSame(7, $this->enTenant(function () use ($productoId) { return (int) Producto::find($productoId)->stock; }));

        $viejo = $this->enTenant(function () use ($pedidoId) { return Pedido::find($pedidoId); });
        $this->post($this->urlTenant('admin/pedidos/' . $pedidoId . '/cancelar'))->assertSessionHas('success');
        $this->enTenant(function () use ($viejo) {
            app(PedidoController::class)->cancelar($viejo);
        });
        $this->assertSame(10, $this->enTenant(function () use ($productoId) { return (int) Producto::find($productoId)->stock; }));
    }

    /** @test */
    public function si_otra_venta_se_llevo_el_stock_el_pedido_no_queda_a_medias()
    {
        $this->enTenant(function () {
            $producto = $this->producto('Gorra', 5);
            $pedido = $this->nuevoPedido();
            $pedido->update(['estado' => 'confirmado']);

            // La pantalla se abrió con 5; mientras tanto se vendieron todas.
            Producto::whereKey($producto->id)->update(['stock' => 0]);
            $resultado = $pedido->agregarProducto($producto, 3);

            $this->assertFalse($resultado['ok']);
            $this->assertStringContainsString('stock cambió', $resultado['error']);
            $this->assertSame(0, $pedido->items()->count(), 'Quedaba el ítem sin su movimiento de stock.');
            $this->assertSame(0, (int) Producto::find($producto->id)->stock);
        });
    }

    /** @test */
    public function un_item_de_otro_pedido_no_se_toca_desde_la_url()
    {
        [$unoId, $itemDeOtroId] = $this->enTenant(function () {
            $producto = $this->producto('Gorra', 10);
            $uno = $this->nuevoPedido();
            $otro = $this->nuevoPedido();
            $otro->agregarProducto($producto, 2);

            return [$uno->id, $otro->items()->first()->id];
        });

        $this->admin()->delete($this->urlTenant('admin/pedidos/' . $unoId . '/productos/' . $itemDeOtroId))->assertNotFound();
        $this->put($this->urlTenant('admin/pedidos/' . $unoId . '/productos/' . $itemDeOtroId), ['cantidad' => 5])->assertNotFound();

        $this->assertSame(2, $this->enTenant(function () use ($itemDeOtroId) { return (int) DB::table('pedido_productos')->where('id', $itemDeOtroId)->value('cantidad'); }));
    }

    /** @test */
    public function no_se_borra_una_caracteristica_ni_un_valor_que_usan_variantes()
    {
        [$talle, $valorS] = $this->enTenant(function () {
            $this->conTalles('Remera');
            $talle = Etiqueta::porNombre('Talle');

            return [$talle, EtiquetaValor::where('etiqueta_id', $talle->id)->where('valor', 'S')->first()];
        });

        $this->admin()->delete($this->urlTenant('admin/etiquetas/' . $talle->id))->assertSessionHas('error');

        // Aunque el producto ya no lo tenga como característica, la variante lo usa.
        $this->enTenant(function () use ($valorS) { DB::table('producto_etiqueta')->where('etiqueta_valor_id', $valorS->id)->delete(); });
        $this->delete($this->urlTenant('admin/etiqueta-valores/' . $valorS->id))->assertRedirect()->assertSessionHas('error');

        $this->enTenant(function () use ($talle) {
            $this->assertTrue(Etiqueta::whereKey($talle->id)->exists());
            $this->assertSame(['S', 'M'], Variante::orderBy('orden')->get()->map->nombre()->all());

            // Y la base tampoco lo deja, por si algo la borra por otro lado.
            $this->expectException(\Illuminate\Database\QueryException::class);
            DB::table('etiquetas')->where('id', $talle->id)->delete();
        });
    }

    /** @test */
    public function unir_dos_valores_no_deja_dos_variantes_iguales()
    {
        [$producto, $mediano, $m] = $this->enTenant(function () {
            $producto = $this->producto('Remera', 0);
            (new VariantesDeProducto())->sincronizar($producto, [
                ['valores' => ['Talle' => 'Mediano'], 'stock' => 1],
                ['valores' => ['Talle' => 'M'], 'stock' => 2],
            ]);
            $talle = Etiqueta::porNombre('Talle');

            return [
                $producto,
                EtiquetaValor::where('etiqueta_id', $talle->id)->where('valor', 'Mediano')->first(),
                EtiquetaValor::where('etiqueta_id', $talle->id)->where('valor', 'M')->first(),
            ];
        });

        $this->admin()->post($this->urlTenant('admin/etiqueta-valores/' . $mediano->id . '/fusionar'), ['destino_id' => $m->id])
            ->assertSessionHas('error');

        $this->enTenant(function () use ($producto, $mediano) {
            $this->assertTrue(EtiquetaValor::whereKey($mediano->id)->exists());
            $this->assertSame(['Mediano', 'M'], Variante::where('producto_id', $producto->id)->orderBy('orden')->get()->map->nombre()->all());
        });
    }

    /** @test */
    public function un_dominio_sin_tienda_no_llena_el_log()
    {
        $this->assertFalse(app(ExceptionHandler::class)->shouldReport(new TenantCouldNotBeIdentifiedOnDomainException('nada.test')));
        $this->get('http://dominio-que-no-es-de-nadie.test/')->assertNotFound()
            ->assertSee('No encontramos esta tienda')->assertDontSee('Tienda MC');

        // Con "www." delante (y la tienda cargada sin él): a la dirección que sí existe.
        $this->get('http://www.' . self::TENANT_DOMAIN . '/carrito?x=1')
            ->assertRedirect('http://' . self::TENANT_DOMAIN . '/carrito?x=1')->assertStatus(301);

        // Una página que no existe, en un dominio sin tienda: lo mismo.
        $this->get('http://dominio-que-no-es-de-nadie.test/no/existe')->assertNotFound()
            ->assertSee('No encontramos esta tienda');
        $this->get('http://www.' . self::TENANT_DOMAIN . '/no/existe')->assertStatus(301);
        // Y en una tienda sí es "Página no encontrada", con su encabezado.
        $this->get($this->urlTenant('no/existe'))->assertNotFound()->assertSee('Página no encontrada');
    }
}
