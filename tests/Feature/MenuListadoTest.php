<?php

namespace Tests\Feature;

use App\Models\Etiqueta;
use App\Models\Menu;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * La lista de menús y el contador del formulario: cuántos productos muestra cada
 * menú (contados como la tienda), subir/bajar entre hermanos y las sugerencias de
 * valor acotadas al menú de arriba.
 */
class MenuListadoTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testmenulistado';
    const TENANT_DOMAIN = 'testmenulistado.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);
            $moneda    = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true]);
            $categoria = Etiqueta::create(['nombre' => 'Categoria', 'visible_usuarios' => true]);
            $marca     = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);

            $crear = function ($nombre, $cat, $mar, $stock = 5) use ($proveedor, $moneda, $categoria, $marca) {
                $p = Producto::create([
                    'proveedor_id' => $proveedor->id, 'descripcion' => $nombre, 'precio' => 100,
                    'moneda_id' => $moneda->id, 'disponible' => true, 'stock' => $stock,
                ]);
                $p->etiquetas()->attach([$categoria->id => ['valor' => $cat], $marca->id => ['valor' => $mar]]);
            };

            $crear('Notebook Asus 1', 'Notebook', 'Asus');
            $crear('Notebook Asus 2', 'Notebook', 'Asus', 0);
            $crear('Notebook HP', 'Notebook', 'HP');
            $crear('Celular Samsung', 'Celular', 'Samsung');

            $notebook = Menu::create(['nombre' => 'Notebook', 'slug' => 'notebook', 'tipo_enlace' => 'etiqueta',
                'enlace_id' => $categoria->id, 'enlace_valor' => 'Notebook', 'activo' => true, 'orden' => 0]);
            $asus = Menu::create(['nombre' => 'Asus', 'slug' => 'asus', 'tipo_enlace' => 'etiqueta',
                'enlace_id' => $marca->id, 'enlace_valor' => 'Asus', 'parent_id' => $notebook->id, 'activo' => true, 'orden' => 0]);
            $samsung = Menu::create(['nombre' => 'Samsung', 'slug' => 'samsung', 'tipo_enlace' => 'etiqueta',
                'enlace_id' => $marca->id, 'enlace_valor' => 'Samsung', 'parent_id' => $notebook->id, 'activo' => true, 'orden' => 0]);
            $agrupa = Menu::create(['nombre' => 'Marcas', 'slug' => 'marcas', 'tipo_enlace' => 'ninguno', 'activo' => true, 'orden' => 0]);

            $this->ids = compact('notebook', 'asus', 'samsung', 'agrupa') + ['marca' => $marca->id];
            foreach (['notebook', 'asus', 'samsung', 'agrupa'] as $clave) {
                $this->ids[$clave] = $this->ids[$clave]->id;
            }
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function comoAdmin()
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                ['name' => 'Admin', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]
            );
        });

        return $this->actingAs($admin);
    }

    /** @test */
    public function la_lista_cuenta_los_productos_como_la_tienda()
    {
        $respuesta = $this->comoAdmin()->get($this->urlTenant('admin/menus'))->assertOk();

        $conteo = $respuesta->viewData('productosPorMenu');

        $this->assertSame(3, $conteo[$this->ids['notebook']]);
        // Asus dentro de Notebook: las dos notebooks Asus, no todo lo de Asus.
        $this->assertSame(2, $conteo[$this->ids['asus']]);
        // Samsung dentro de Notebook: no hay notebooks Samsung.
        $this->assertSame(0, $conteo[$this->ids['samsung']]);
        // Los que sólo agrupan no llevan número.
        $this->assertArrayNotHasKey($this->ids['agrupa'], $conteo);

        $respuesta->assertSee('0 productos')->assertSee('2 productos');
    }

    /** @test */
    public function el_formulario_cuenta_antes_de_guardar_con_lo_heredado()
    {
        $contar = function (array $params) {
            return $this->comoAdmin()
                ->getJson($this->urlTenant('admin/menus/contar-productos?' . http_build_query($params)))
                ->assertOk()
                ->json('productos');
        };

        $base = ['tipo_enlace' => 'etiqueta', 'enlace_id' => $this->ids['marca'], 'enlace_valor' => 'Asus'];

        $this->assertSame(2, $contar($base + ['parent_id' => $this->ids['notebook']]));
        $this->assertSame(1, $contar($base + ['parent_id' => $this->ids['notebook'], 'filtro_stock' => 'con_stock']));
        $this->assertSame(0, $contar(['enlace_valor' => 'Samsung', 'parent_id' => $this->ids['notebook']] + $base));
        $this->assertNull($contar(['tipo_enlace' => 'etiqueta']));
        $this->assertNull($contar(['tipo_enlace' => 'ninguno']));
    }

    /** @test */
    public function las_sugerencias_de_valor_se_acotan_al_menu_de_arriba()
    {
        $this->comoAdmin()
            ->getJson($this->urlTenant('admin/menus/etiqueta/' . $this->ids['marca'] . '/valores?parent_id=' . $this->ids['notebook']))
            ->assertOk()
            ->assertExactJson([
                ['valor' => 'Asus', 'detalle' => '2 productos'],
                ['valor' => 'HP', 'detalle' => '1 producto'],
            ]);
    }

    /**
     * Asus y Samsung tienen la misma posición (0): bajar Asus tiene que funcionar
     * igual, renumerando a los hermanos.
     *
     * @test
     */
    public function subir_y_bajar_cambia_el_orden_entre_hermanos()
    {
        $this->comoAdmin()
            ->post($this->urlTenant('admin/menus/' . $this->ids['asus'] . '/mover'), ['direccion' => 'abajo'])
            ->assertRedirect();

        $orden = function () {
            return $this->enTenant(function () {
                return Menu::find($this->ids['notebook'])->children()->pluck('nombre')->all();
            });
        };

        $this->assertSame(['Samsung', 'Asus'], $orden());

        $this->comoAdmin()
            ->post($this->urlTenant('admin/menus/' . $this->ids['asus'] . '/mover'), ['direccion' => 'arriba']);

        $this->assertSame(['Asus', 'Samsung'], $orden());
    }
}
