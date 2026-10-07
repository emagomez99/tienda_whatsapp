<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Menu;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** Menús de tipo "Los de una categoría": qué muestran, el formulario y lo que bloquean. */
class MenuCategoriaTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testmenucategoria';
    const TENANT_DOMAIN = 'testmenucategoria.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor'])->id;
            $moneda = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id;
            $marca = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);

            $remeras = Categoria::resolverCamino('Ropa > Remeras', true);
            $pantalones = Categoria::resolverCamino('Ropa > Pantalones', true);

            $crear = function ($descripcion, Categoria $categoria, $valorMarca) use ($proveedor, $moneda, $marca) {
                $p = Producto::create([
                    'proveedor_id' => $proveedor, 'descripcion' => $descripcion, 'precio' => 1, 'moneda_id' => $moneda,
                    'disponible' => true, 'stock' => 3,
                ]);
                $p->categorias()->attach($categoria->id);
                $p->etiquetas()->attach($marca->id, ['valor' => $valorMarca]);
            };
            $crear('Remera Nike', $remeras, 'Nike');
            $crear('Remera Adidas', $remeras, 'Adidas');
            $crear('Pantalón Nike', $pantalones, 'Nike');
            Producto::create([
                'proveedor_id' => $proveedor, 'descripcion' => 'Pelota suelta', 'precio' => 1, 'moneda_id' => $moneda,
                'disponible' => true, 'stock' => 3,
            ]);

            $this->ids = [
                'ropa'    => Categoria::resolverCamino('Ropa')->id,
                'remeras' => $remeras->id,
                'marca'   => $marca->id,
            ];
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

    private function datosMenu(array $extra = []): array
    {
        return $extra + ['nombre' => 'Ropa', 'tipo_enlace' => 'categoria', 'enlace_id' => $this->ids['ropa'],
                         'activo' => 1, 'orden' => 0, 'filtro_stock' => 'todos'];
    }

    /** @test */
    public function muestra_la_categoria_con_sus_subcategorias_y_suma_lo_de_arriba()
    {
        $this->comoAdmin()->post($this->urlTenant('admin/menus'), $this->datosMenu())->assertSessionHasNoErrors();

        $this->enTenant(function () {
            $ropa = Menu::where('nombre', 'Ropa')->firstOrFail();
            $this->assertSame('Categoría: Ropa', $ropa->descripcionFiltro());
            $this->assertSame(3, Producto::delMenu($ropa)->count());

            // "Nike" dentro de "Ropa": las prendas Nike, no todo lo de Nike.
            $nike = Menu::create(['nombre' => 'Nike', 'tipo_enlace' => 'etiqueta', 'enlace_id' => $this->ids['marca'],
                'enlace_valor' => 'Nike', 'parent_id' => $ropa->id, 'activo' => true, 'orden' => 0]);
            $this->assertSame(2, Producto::delMenu($nike)->count());
        });

        $this->get($this->urlTenant('catalogo/ropa'))
            ->assertOk()
            ->assertSee('Remera Nike')
            ->assertSee('Pantalón Nike')
            ->assertDontSee('Pelota suelta');
    }

    /** @test */
    public function el_formulario_ofrece_las_categorias_y_cuenta_antes_de_guardar()
    {
        $this->comoAdmin()->get($this->urlTenant('admin/menus/create'))
            ->assertOk()
            ->assertSee('Los de una categoría')
            ->assertSee('id="categoria_select"', false);

        $this->comoAdmin()
            ->getJson($this->urlTenant('admin/menus/contar-productos?tipo_enlace=categoria&enlace_id=' . $this->ids['remeras']))
            ->assertJson(['productos' => 2]);

        // Sin elegir la categoría todavía no hay número.
        $this->comoAdmin()
            ->getJson($this->urlTenant('admin/menus/contar-productos?tipo_enlace=categoria'))
            ->assertJson(['productos' => null]);
    }

    /** @test */
    public function sin_categoria_o_con_una_que_no_existe_no_se_guarda()
    {
        $this->comoAdmin()->post($this->urlTenant('admin/menus'), $this->datosMenu(['enlace_id' => null]))
            ->assertSessionHasErrors(['enlace_id' => 'Elegí la categoría.']);

        $this->comoAdmin()->post($this->urlTenant('admin/menus'), $this->datosMenu(['enlace_id' => 999999]))
            ->assertSessionHasErrors('enlace_id');

        $this->enTenant(function () {
            $this->assertSame(0, Menu::count());
        });
    }

    /** @test */
    public function no_se_puede_eliminar_una_categoria_que_usa_un_menu()
    {
        $this->comoAdmin()->post($this->urlTenant('admin/menus'), $this->datosMenu([
            'nombre' => 'Remeras', 'enlace_id' => $this->ids['remeras'],
        ]));

        $this->comoAdmin()->delete($this->urlTenant('admin/categorias/' . $this->ids['remeras']))
            ->assertSessionHas('error', '«Remeras» la usa el menú «Remeras»: primero cambialo o eliminalo.');

        $this->enTenant(function () {
            $this->assertNotNull(Categoria::find($this->ids['remeras']));
        });
    }
}
