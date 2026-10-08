<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Support\Carbon;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** Etapa 4: cada categoría elige en qué orden se ven sus productos; Marca en tiendas nuevas. */
class OrdenDeProductosTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testordenproductos';
    const TENANT_DOMAIN = 'testordenproductos.test';

    /** @var array */
    private $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
        $this->enTenant(function () {
            (new PermisoSeeder())->run();
            $proveedor = Proveedor::create(['nombre' => 'Distribuidora'])->id;
            $pesos = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true, 'cotizacion' => 1])->id;
            $dolares = Moneda::create(['nombre' => 'Dólar', 'codigo' => 'USD', 'simbolo' => 'U$S', 'cotizacion' => 1000])->id;

            $bebidas = Categoria::resolverCamino('Bebidas', true);
            $gaseosas = Categoria::resolverCamino('Bebidas > Gaseosas', true);
            $limpieza = Categoria::resolverCamino('Limpieza', true);

            $crear = function ($nombre, $precio, $moneda, Categoria $categoria, $diasAtras) use ($proveedor) {
                $p = Producto::create(['proveedor_id' => $proveedor, 'descripcion' => $nombre, 'precio' => $precio,
                    'moneda_id' => $moneda, 'disponible' => true, 'stock' => 5]);
                $p->forceFill(['created_at' => Carbon::now()->subDays($diasAtras)])->save();
                $p->categorias()->attach($categoria->id);

                return $p->id;
            };

            $this->ids = [
                'bebidas'  => $bebidas->id,
                'agua'     => $crear('Agua mineral', 500, $pesos, $bebidas, 3),
                'coca'     => $crear('Coca-Cola 1,5 L', 2000, $pesos, $gaseosas, 1),
                // 2,5 dólares = 2.500 pesos: la más cara, aunque el número sea el más chico.
                'sprite'   => $crear('Sprite importada', 2.5, $dolares, $gaseosas, 2),
                'lavandina'=> $crear('Lavandina', 300, $pesos, $limpieza, 0),
            ];
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function ordenar(string $criterio): void
    {
        $this->enTenant(function () use ($criterio) {
            Categoria::find($this->ids['bebidas'])->update(['orden_productos' => $criterio]);
        });
    }

    private function admin()
    {
        $usuario = $this->enTenant(function () {
            return User::create(['name' => 'Dueño', 'email' => 'duenio@' . self::TENANT_DOMAIN, 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]);
        });

        return $this->actingAs($usuario);
    }

    /** @test */
    public function por_defecto_van_por_nombre_y_cada_categoria_elige_su_orden()
    {
        $pagina = $this->urlTenant('categoria/bebidas');

        $this->get($pagina)->assertOk()->assertSeeInOrder(['Agua mineral', 'Coca-Cola 1,5 L', 'Sprite importada']);

        $this->ordenar('nuevos');
        $this->get($pagina)->assertSeeInOrder(['Coca-Cola 1,5 L', 'Sprite importada', 'Agua mineral']);

        // El precio se compara en pesos: 2,5 dólares son 2.500 pesos.
        $this->ordenar('precio_menor');
        $this->get($pagina)->assertSeeInOrder(['Agua mineral', 'Coca-Cola 1,5 L', 'Sprite importada']);

        $this->ordenar('precio_mayor');
        $this->get($pagina)->assertSeeInOrder(['Sprite importada', 'Coca-Cola 1,5 L', 'Agua mineral']);

        // Los filtros por AJAX respetan el mismo orden.
        $html = $this->getJson($this->urlTenant('productos/ajax?categoria_id=' . $this->ids['bebidas']))->assertOk()->json('html');
        $this->assertLessThan(strpos($html, 'Agua mineral'), strpos($html, 'Sprite importada'));
    }

    /** @test */
    public function a_mano_los_destacados_van_primero_y_el_resto_por_nombre()
    {
        $this->admin()->put($this->urlTenant('admin/categorias/' . $this->ids['bebidas']), [
            'nombre' => 'Bebidas', 'parent_id' => '', 'visible' => 1,
            'orden_productos' => 'manual', 'destacados_enviados' => 1,
            // La lavandina no es de Bebidas: no se puede destacar acá.
            'destacados' => [$this->ids['sprite'], $this->ids['lavandina']],
        ])->assertRedirect();

        $this->enTenant(function () {
            $categoria = Categoria::find($this->ids['bebidas']);
            $this->assertSame('manual', $categoria->orden_productos);
            $this->assertSame([$this->ids['sprite']], $categoria->destacados()->pluck('productos.id')->all());
        });

        $this->get($this->urlTenant('categoria/bebidas'))->assertSeeInOrder(['Sprite importada', 'Agua mineral', 'Coca-Cola 1,5 L']);

        // La pantalla de la categoría muestra la lista para seguir editándola.
        $this->get($this->urlTenant('admin/categorias/' . $this->ids['bebidas'] . '/edit'))->assertOk()
            ->assertSee('Orden de los productos en la tienda')
            ->assertSee('name="destacados[]" value="' . $this->ids['sprite'] . '"', false);

        // En su subcategoría, los destacados de Bebidas no cuentan.
        $this->get($this->urlTenant('categoria/gaseosas'))->assertSeeInOrder(['Coca-Cola 1,5 L', 'Sprite importada']);
    }

    /** @test */
    public function el_buscador_de_destacados_ofrece_solo_productos_de_la_categoria()
    {
        $url = $this->urlTenant('admin/categorias/' . $this->ids['bebidas'] . '/productos-para-destacar');

        $nombres = collect($this->admin()->getJson($url . '?q=a')->assertOk()->json())->pluck('descripcion')->all();
        $this->assertContains('Coca-Cola 1,5 L', $nombres);   // de la subcategoría
        $this->assertNotContains('Lavandina', $nombres);

        $this->assertSame(['Sprite importada'], collect($this->getJson($url . '?q=sprite')->json())->pluck('descripcion')->all());
    }

    /** @test */
    public function una_tienda_nueva_arranca_con_marca_para_todos_los_productos()
    {
        $this->enTenant(function () {
            Moneda::query()->delete();
            (new TenantDatabaseSeeder())->run();

            $marca = Etiqueta::where('nombre', 'Marca')->first();
            $this->assertNotNull($marca);
            $this->assertTrue($marca->global);
            $this->assertFalse($marca->obligatoria_global);
            $this->assertTrue($marca->visible_usuarios);
        });
    }
}
