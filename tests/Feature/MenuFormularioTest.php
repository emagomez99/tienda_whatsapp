<?php

namespace Tests\Feature;

use App\Models\Etiqueta;
use App\Models\Menu;
use App\Models\Proveedor;
use App\Models\User;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * El formulario de menú, compartido por alta y edición: que se vea, que guarde lo
 * mismo que antes y que avise qué filtros hereda un submenú.
 */
class MenuFormularioTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testmenuform';
    const TENANT_DOMAIN = 'testmenuform.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Hercules']);
            $categoria = Etiqueta::create(['nombre' => 'Categoria', 'visible_usuarios' => true]);
            $marca     = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);

            $notebook = Menu::create([
                'nombre' => 'Notebook', 'slug' => 'notebook', 'tipo_enlace' => 'etiqueta',
                'enlace_id' => $categoria->id, 'enlace_valor' => 'Notebook', 'activo' => true,
            ]);

            $this->ids = [
                'proveedor' => $proveedor->id,
                'categoria' => $categoria->id,
                'marca'     => $marca->id,
                'notebook'  => $notebook->id,
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
                ['name' => 'Admin de prueba', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]
            );
        });

        return $this->actingAs($admin);
    }

    /** @test */
    public function el_alta_muestra_los_tres_bloques_y_lo_que_hereda_cada_padre()
    {
        $this->comoAdmin()
            ->get($this->urlTenant('admin/menus/create'))
            ->assertStatus(200)
            ->assertSee('Qué es y dónde va')
            ->assertSee('Qué productos muestra')
            ->assertSee('Filtros para el cliente')
            ->assertSee('"' . $this->ids['notebook'] . '":["Categoria: Notebook"]', false);
    }

    /** @test */
    public function la_edicion_muestra_los_filtros_guardados()
    {
        $id = $this->enTenant(function () {
            return Menu::create([
                'nombre' => 'Kits', 'slug' => 'kits', 'tipo_enlace' => 'proveedor',
                'enlace_id' => $this->ids['proveedor'], 'activo' => true,
                'filtros_etiquetas' => [(string) $this->ids['marca']],
                'filtros_config' => [(string) $this->ids['marca'] => false],
                'filtros_requeridos' => true,
            ])->id;
        });

        $this->comoAdmin()
            ->get($this->urlTenant('admin/menus/' . $id . '/edit'))
            ->assertStatus(200)
            ->assertSee('name="filtros_etiquetas[]" value="' . $this->ids['marca'] . '"', false)
            ->assertSee('Guardar cambios');
    }

    /** @test */
    public function guarda_un_submenu_por_etiqueta_con_filtros()
    {
        $this->comoAdmin()
            ->post($this->urlTenant('admin/menus'), [
                'nombre'             => 'Asus',
                'parent_id'          => $this->ids['notebook'],
                'tipo_enlace'        => 'etiqueta',
                'enlace_id'          => $this->ids['marca'],
                'enlace_valor'       => 'Asus',
                'orden'              => 0,
                'activo'             => 1,
                'filtro_stock'       => 'con_stock',
                'filtros_etiquetas'  => [$this->ids['categoria']],
                'filtros_todos'      => [$this->ids['categoria'] => 1],
                'filtros_requeridos' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->enTenant(function () {
            $menu = Menu::where('nombre', 'Asus')->firstOrFail();

            $this->assertSame($this->ids['notebook'], (int) $menu->parent_id);
            $this->assertSame('Asus', $menu->enlace_valor);
            $this->assertSame('asus', $menu->slug);
            $this->assertSame('con_stock', $menu->filtro_stock);
            $this->assertFalse($menu->filtros_requeridos);
            $this->assertSame([(string) $this->ids['categoria'] => true], $menu->filtros_config);
            // Un submenú de "Asus" heredaría los dos filtros, de la raíz hacia abajo.
            $this->assertSame(['Categoria: Notebook', 'Marca: Asus'], $menu->filtrosParaSubmenus());
        });
    }
}
