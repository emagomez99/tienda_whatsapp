<?php

namespace Tests\Feature;

use App\Models\Perfil;
use App\Models\User;
use App\Support\TemasDeAyuda;
use Database\Seeders\PermisoSeeder;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** Menú lateral › Ayuda: todos los temas, cada uno con su permiso. */
class AyudaTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testayuda';
    const TENANT_DOMAIN = 'testayuda.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
        $this->enTenant(function () {
            (new PermisoSeeder())->run();
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function como(?string $perfil)
    {
        $usuario = $this->enTenant(function () use ($perfil) {
            return User::create([
                'name' => $perfil ?: 'Dueño', 'email' => uniqid() . '@' . self::TENANT_DOMAIN, 'password' => bcrypt('secreto'),
                'is_admin' => true, 'activo' => true, 'perfil_id' => $perfil ? Perfil::where('nombre', $perfil)->value('id') : null,
            ]);
        });

        return $this->actingAs($usuario);
    }

    /** @test */
    public function el_menu_tiene_ayuda_y_ya_no_repite_ver_tienda_ni_cerrar_sesion()
    {
        $html = $this->como(null)->get($this->urlTenant('admin'))->assertOk()->getContent();

        $pie = substr($html, strpos($html, 'panel-menu-pie'), 600);
        $this->assertStringContainsString('/admin/ayuda', $pie);
        $this->assertStringNotContainsString('Cerrar sesión', $pie);
        $this->assertStringNotContainsString('Ver mi tienda', $pie);
        // Siguen arriba, en la barra.
        $this->assertSame(1, substr_count($html, 'Cerrar sesión'));
        $this->assertStringContainsString('Ver tienda', $html);
    }

    /** @test */
    public function el_dueno_ve_todos_los_temas_y_cada_uno_abre()
    {
        $this->como(null)->get($this->urlTenant('admin/ayuda'))->assertOk()
            ->assertSee('Empezá por acá')
            ->assertSee('Talles, colores y variantes')
            ->assertSee('Preguntas frecuentes')
            ->assertSee('¿Categoría o característica?');

        $dueno = $this->enTenant(function () { return User::latest('id')->first(); });
        foreach (array_keys(TemasDeAyuda::para($dueno)) as $slug) {
            $this->get($this->urlTenant('admin/ayuda/' . $slug))->assertOk();
        }
        $this->get($this->urlTenant('admin/ayuda/categorias'))->assertSee('estante-y-dato', false)->assertSee('Ir a Categorías');
        $this->get($this->urlTenant('admin/ayuda/no-existe'))->assertNotFound();

        // Cada "Cómo funciona" lleva a la Ayuda completa.
        $this->get($this->urlTenant('admin/categorias'))->assertSee('Ver toda la ayuda');
    }

    /** @test */
    public function cada_uno_ve_los_temas_de_lo_que_puede_usar()
    {
        $this->como('Operador')->get($this->urlTenant('admin/ayuda'))->assertOk()
            ->assertSee('Productos')
            ->assertDontSee('Usuarios y perfiles');

        $this->get($this->urlTenant('admin/ayuda/usuarios'))->assertNotFound();
        $this->get($this->urlTenant('admin/ayuda/primeros-pasos'))->assertOk();
    }
}
