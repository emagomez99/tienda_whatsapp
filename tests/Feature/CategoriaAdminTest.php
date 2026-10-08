<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Moneda;
use App\Models\Perfil;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** Admin › Categorías: alta, edición, reglas del árbol, borrar, ocultar y permisos. */
class CategoriaAdminTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testcategoriaadmin';
    const TENANT_DOMAIN = 'testcategoriaadmin.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            (new PermisoSeeder())->run();

            $remeras = Categoria::resolverCamino('Ropa > Remeras', true);
            $proveedor = Proveedor::create(['nombre' => 'Proveedor']);
            $moneda = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true]);
            $producto = Producto::create(['proveedor_id' => $proveedor->id, 'descripcion' => 'Remera', 'precio' => 1,
                'moneda_id' => $moneda->id, 'disponible' => true, 'stock' => 1]);
            $producto->categorias()->attach($remeras->id);

            $this->ids = ['ropa' => $remeras->parent_id, 'remeras' => $remeras->id, 'producto' => $producto->id];
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function como(string $perfil = 'Administrador')
    {
        $usuario = $this->enTenant(function () use ($perfil) {
            return User::create([
                'name' => $perfil, 'email' => uniqid() . '@' . self::TENANT_DOMAIN, 'password' => bcrypt('secreto'),
                'is_admin' => true, 'activo' => true, 'perfil_id' => Perfil::where('nombre', $perfil)->value('id'),
            ]);
        });

        return $this->actingAs($usuario);
    }

    /** @test */
    public function el_arbol_muestra_las_categorias_con_productos_contando_las_subcategorias()
    {
        $respuesta = $this->como()->get($this->urlTenant('admin/categorias'))->assertOk();

        $this->assertSame(1, $respuesta->viewData('productosPorCategoria')[$this->ids['ropa']]);
        $respuesta->assertSee('Remeras')->assertSee('id="categoria-tree"', false);
    }

    /** @test */
    public function crear_una_subcategoria_desde_el_mas()
    {
        $this->como()->get($this->urlTenant('admin/categorias/create?parent_id=' . $this->ids['ropa']))
            ->assertOk()
            ->assertSee('<option value="' . $this->ids['ropa'] . '" selected>', false);

        $this->como()->post($this->urlTenant('admin/categorias'), [
            'nombre' => 'Pantalones', 'parent_id' => $this->ids['ropa'], 'visible' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->enTenant(function () {
            $this->assertSame('Ropa > Pantalones', Categoria::where('nombre', 'Pantalones')->first()->camino());
        });
    }

    /** @test */
    public function no_deja_dos_hermanas_con_el_mismo_nombre()
    {
        $this->como()->post($this->urlTenant('admin/categorias'), [
            'nombre' => 'remeras', 'parent_id' => $this->ids['ropa'],
        ])->assertSessionHasErrors('nombre');
    }

    /** @test */
    public function no_deja_meter_una_categoria_dentro_de_su_subcategoria()
    {
        $this->como()->put($this->urlTenant('admin/categorias/' . $this->ids['ropa']), [
            'nombre' => 'Ropa', 'parent_id' => $this->ids['remeras'],
        ])->assertSessionHasErrors('parent_id');
    }

    /** @test */
    public function eliminar_no_borra_los_productos_y_no_se_puede_con_subcategorias()
    {
        $this->como()->delete($this->urlTenant('admin/categorias/' . $this->ids['ropa']))->assertSessionHas('error');

        $this->como()->delete($this->urlTenant('admin/categorias/' . $this->ids['remeras']))->assertSessionHas('success');

        $this->enTenant(function () {
            $this->assertNull(Categoria::find($this->ids['remeras']));
            $this->assertNotNull(Producto::find($this->ids['producto']));
        });
    }

    /** @test */
    public function ocultar_la_madre_marca_a_las_hijas_como_ocultas_por_ella()
    {
        $this->como()->patch($this->urlTenant('admin/categorias/' . $this->ids['ropa'] . '/visibilidad'))->assertRedirect();

        $this->como()->get($this->urlTenant('admin/categorias'))->assertSee('Oculta por «Ropa»');
    }

    /** @test */
    public function arrastrar_guarda_y_respeta_los_tres_niveles()
    {
        $nieto = $this->enTenant(function () {
            return Categoria::resolverCamino('Ropa > Remeras > Manga larga', true)->id;
        });

        // "Ropa" (con dos niveles adentro) dentro de otra raíz quedaría en el cuarto nivel.
        $otra = $this->enTenant(function () { return Categoria::create(['nombre' => 'Ofertas'])->id; });
        $this->como()->postJson($this->urlTenant('admin/categorias/reordenar'), ['items' => [
            ['id' => $this->ids['ropa'], 'parent_id' => $otra, 'orden' => 0],
        ]])->assertStatus(422);

        $this->como()->postJson($this->urlTenant('admin/categorias/reordenar'), ['items' => [
            ['id' => $nieto, 'parent_id' => $this->ids['ropa'], 'orden' => 0],
        ]])->assertOk();

        $this->enTenant(function () use ($nieto) {
            $this->assertSame('Ropa > Manga larga', Categoria::find($nieto)->camino());
        });
    }

    /** @test */
    public function el_operador_ve_pero_no_edita()
    {
        // El botón no está (la ayuda sí nombra "Crear categoría": se busca el enlace).
        $this->como('Operador')->get($this->urlTenant('admin/categorias'))->assertOk()
            ->assertSee('Cómo funcionan las categorías')
            ->assertDontSee('href="' . $this->urlTenant('admin/categorias/create') . '"', false);
        // PermisoMiddleware redirige (no responde 403): es la convención del panel.
        $this->como('Operador')->get($this->urlTenant('admin/categorias/create'))->assertRedirect();
    }
}
