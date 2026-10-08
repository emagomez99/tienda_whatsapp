<?php

namespace Tests\Feature;

use App\Models\Perfil;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** "Perfil activo": apagado, nadie con ese perfil entra al panel (antes no tenía efecto). */
class PerfilActivoTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testperfilactivo';
    const TENANT_DOMAIN = 'testperfilactivo.test';

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

    private function usuario(string $perfil, string $email): User
    {
        return $this->enTenant(function () use ($perfil, $email) {
            return User::create([
                'name' => $perfil, 'email' => $email, 'password' => bcrypt('secreto'),
                'is_admin' => true, 'activo' => true, 'perfil_id' => Perfil::where('nombre', $perfil)->value('id'),
            ]);
        });
    }

    private function datosPerfil(Perfil $perfil, array $extra = []): array
    {
        return $extra + ['nombre' => $perfil->nombre, 'descripcion' => $perfil->descripcion,
                         'es_superadmin' => $perfil->es_superadmin ? 1 : 0, 'permisos' => $perfil->permisos->pluck('id')->all()];
    }

    /** @test */
    public function con_el_perfil_apagado_no_entra_ni_sigue_adentro()
    {
        $operador = $this->usuario('Operador', 'operador@' . self::TENANT_DOMAIN);

        // Con la sesión abierta, entra.
        $this->actingAs($operador)->get($this->urlTenant('admin/productos'))->assertOk();

        $this->enTenant(function () {
            Perfil::where('nombre', 'Operador')->update(['activo' => false]);
        });

        // La sesión abierta se corta… (cada pedido vuelve a leer el usuario, como en la vida real)
        $operador = $this->enTenant(function () use ($operador) { return User::find($operador->id); });
        $this->actingAs($operador)->get($this->urlTenant('admin/productos'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Tu perfil está desactivado');

        // …y no puede volver a entrar (con el mismo mensaje que datos incorrectos).
        auth()->logout();
        $this->post($this->urlTenant('login'), ['email' => 'operador@' . self::TENANT_DOMAIN, 'password' => 'secreto'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** @test */
    public function nadie_apaga_su_propio_perfil_ni_el_de_superadmin()
    {
        // Un encargado que puede editar perfiles, y el dueño (administrador sin perfil: puede todo).
        [$encargado, $duenio] = $this->enTenant(function () {
            $perfil = Perfil::create(['nombre' => 'Encargado', 'activo' => true]);
            $perfil->permisos()->attach(\App\Models\Permiso::whereIn('nombre', ['perfiles.ver', 'perfiles.gestionar'])->pluck('id'));
            $encargado = User::create(['name' => 'Encargado', 'email' => 'encargado@' . self::TENANT_DOMAIN, 'password' => bcrypt('x'),
                'is_admin' => true, 'activo' => true, 'perfil_id' => $perfil->id]);
            $duenio = User::create(['name' => 'Dueño', 'email' => 'duenio@' . self::TENANT_DOMAIN, 'password' => bcrypt('x'),
                'is_admin' => true, 'activo' => true]);
            return [$encargado, $duenio];
        });
        $perfil = function ($nombre) {
            return $this->enTenant(function () use ($nombre) { return Perfil::with('permisos')->where('nombre', $nombre)->first(); });
        };

        $this->actingAs($encargado)->put($this->urlTenant('admin/perfiles/' . $perfil('Encargado')->id), $this->datosPerfil($perfil('Encargado')))
            ->assertSessionHasErrors(['activo' => 'No podés desactivar tu propio perfil: te quedarías sin poder entrar.']);
        $super = $this->enTenant(function () { return Perfil::with('permisos')->where('es_superadmin', true)->first(); });
        $this->actingAs($duenio)->put($this->urlTenant('admin/perfiles/' . $super->id), $this->datosPerfil($super))
            ->assertSessionHasErrors(['activo' => 'El perfil que administra todo no se puede desactivar.']);

        $this->enTenant(function () use ($super) {
            $this->assertTrue((bool) Perfil::where('nombre', 'Encargado')->value('activo'));
            $this->assertTrue((bool) Perfil::find($super->id)->activo);
        });

        // Otro perfil sí se puede apagar.
        $this->actingAs($duenio)->put($this->urlTenant('admin/perfiles/' . $perfil('Operador')->id), $this->datosPerfil($perfil('Operador')))
            ->assertSessionHasNoErrors();
        $this->enTenant(function () {
            $this->assertFalse((bool) Perfil::where('nombre', 'Operador')->value('activo'));
        });
    }
}
