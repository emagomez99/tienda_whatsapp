<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Perfil;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * Lo que encontró la revisión del panel: quién puede darle qué perfil a quién, los
 * pedidos de "todas las fechas" al pasar de página, direcciones mal escritas y
 * mensajes en castellano.
 */
class PanelSolidezTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testpanelsolidez';
    const TENANT_DOMAIN = 'testpanelsolidez.test';

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

    private function usuario(?string $perfil, array $datos = []): User
    {
        return $this->enTenant(function () use ($perfil, $datos) {
            return User::create(array_merge([
                'name' => $perfil ?: 'Dueño', 'email' => uniqid() . '@' . self::TENANT_DOMAIN, 'password' => bcrypt('secreto'),
                'is_admin' => true, 'activo' => true, 'perfil_id' => $perfil ? Perfil::where('nombre', $perfil)->value('id') : null,
            ], $datos));
        });
    }

    private function idDePerfil(string $nombre): int
    {
        return $this->enTenant(function () use ($nombre) {
            return Perfil::where('nombre', $nombre)->value('id');
        });
    }

    /** @test */
    public function quien_no_puede_todo_no_reparte_perfiles_que_pueden_todo()
    {
        $administrador = $this->usuario('Administrador');
        $otro = $this->usuario('Operador');
        $super = $this->idDePerfil('Superadministrador');
        $operador = $this->idDePerfil('Operador');

        // No se lo ofrece...
        $this->actingAs($administrador)->get($this->urlTenant('admin/usuarios/create'))->assertOk()
            ->assertDontSee('Superadministrador')->assertDontSee('Sin perfil: puede hacer todo');

        // ...ni lo acepta si se lo mandan igual, ni "sin perfil" (que es poder todo).
        $datos = ['name' => $otro->name, 'email' => $otro->email, 'activo' => '1'];
        $this->actingAs($administrador)->put($this->urlTenant('admin/usuarios/' . $otro->id), $datos + ['perfil_id' => $super])
            ->assertSessionHasErrors('perfil_id');
        $this->actingAs($administrador)->put($this->urlTenant('admin/usuarios/' . $otro->id), $datos + ['perfil_id' => 'sin'])
            ->assertSessionHasErrors('perfil_id');
        $this->assertSame($operador, $this->enTenant(function () use ($otro) { return $otro->fresh()->perfil_id; }));

        // A un usuario que puede todo no lo toca.
        $duenio = $this->usuario(null);
        $this->actingAs($administrador)->get($this->urlTenant('admin/usuarios/' . $duenio->id . '/edit'))->assertForbidden();

        // Quien puede todo sí elige cualquiera.
        $this->actingAs($duenio)->put($this->urlTenant('admin/usuarios/' . $otro->id), $datos + ['perfil_id' => $super])
            ->assertSessionHasNoErrors();
        $this->assertSame($super, $this->enTenant(function () use ($otro) { return $otro->fresh()->perfil_id; }));
        // "Sin perfil" se elige a propósito; sin elegir nada, no se guarda.
        $this->actingAs($duenio)->put($this->urlTenant('admin/usuarios/' . $otro->id), $datos)->assertSessionHasErrors('perfil_id');
        $this->actingAs($duenio)->put($this->urlTenant('admin/usuarios/' . $otro->id), $datos + ['perfil_id' => 'sin'])->assertSessionHasNoErrors();
        $this->assertNull($this->enTenant(function () use ($otro) { return $otro->fresh()->perfil_id; }));
    }

    /** @test */
    public function en_el_usuario_propio_no_se_cambia_el_perfil_ni_se_queda_afuera()
    {
        $administrador = $this->usuario('Administrador');
        $perfil = $administrador->perfil_id;

        // Manda "inactivo" (el interruptor apagado no viaja) y otro perfil: no cambian.
        $this->actingAs($administrador)->put($this->urlTenant('admin/usuarios/' . $administrador->id), [
            'name' => 'Nombre nuevo', 'email' => $administrador->email, 'perfil_id' => $this->idDePerfil('Superadministrador'),
        ])->assertSessionHasNoErrors();

        $despues = $this->enTenant(function () use ($administrador) { return $administrador->fresh(); });
        $this->assertSame('Nombre nuevo', $despues->name);
        $this->assertTrue((bool) $despues->activo);
        $this->assertTrue((bool) $despues->is_admin);
        $this->assertSame($perfil, $despues->perfil_id);
    }

    /** @test */
    public function un_usuario_nuevo_puede_entrar_al_panel_y_los_errores_salen_en_castellano()
    {
        $duenio = $this->usuario(null);

        $this->actingAs($duenio)->post($this->urlTenant('admin/usuarios'), [
            'name' => 'Vendedora', 'email' => 'vendedora@' . self::TENANT_DOMAIN, 'password' => 'secreto123',
            'password_confirmation' => 'secreto123', 'activo' => '1', 'perfil_id' => $this->idDePerfil('Operador'),
        ])->assertSessionHasNoErrors();
        $this->assertTrue((bool) $this->enTenant(function () {
            return User::where('email', 'vendedora@' . self::TENANT_DOMAIN)->value('is_admin');
        }));

        $respuesta = $this->actingAs($duenio)->post($this->urlTenant('admin/usuarios'), [
            'name' => '', 'email' => $duenio->email, 'password' => 'corta', 'password_confirmation' => 'otra',
        ]);
        $errores = implode(' ', session('errors')->all());
        $respuesta->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertStringContainsString('Falta completar el nombre', $errores);
        $this->assertStringContainsString('Ya hay otro con el email que escribiste', $errores);
        $this->assertStringNotContainsString('The ', $errores);
    }

    /** @test */
    public function los_pedidos_de_todas_las_fechas_siguen_siendolo_al_pasar_de_pagina_y_al_buscar()
    {
        $duenio = $this->usuario(null);
        $this->enTenant(function () {
            for ($i = 1; $i <= 25; $i++) {
                $pedido = Pedido::create(['nombre' => $i === 1 ? 'María' : 'Cliente', 'apellido' => $i === 1 ? 'Gómez' : 'Viejo ' . $i, 'email' => '', 'celular' => '', 'estado' => 'pendiente']);
                $pedido->forceFill(['created_at' => now()->subDays(90)])->save();
            }
        });

        // Al entrar (últimos 30 días) no hay ninguno, y eso no cuenta como un filtro puesto.
        $this->actingAs($duenio)->get($this->urlTenant('admin/pedidos'))->assertOk()
            ->assertSee('No hay pedidos en los últimos 30 días')
            ->assertDontSee('Quitar todos los filtros');

        // En "todas las fechas" hay dos páginas, y la segunda sigue en todas las fechas.
        $html = $this->actingAs($duenio)->get($this->urlTenant('admin/pedidos?desde=&hasta='))->assertOk()
            ->assertSee('Viejo 25')->getContent();
        $this->assertMatchesRegularExpression('/admin\/pedidos\?[^"]*desde=&(amp;)?hasta=[^"]*page=2|admin\/pedidos\?[^"]*page=2[^"]*desde=&(amp;)?hasta=/', $html);
        $this->actingAs($duenio)->get($this->urlTenant('admin/pedidos?desde=&hasta=&page=2'))->assertOk()
            ->assertSee('Gómez');
        // El buscador conserva las fechas (vacías) al enviar.
        $this->assertMatchesRegularExpression('/<input type="hidden" name="desde" value="">/', $html);

        // Nombre y apellido juntos, sin tildes; y el número con o sin #.
        $this->actingAs($duenio)->get($this->urlTenant('admin/pedidos?desde=&hasta=&buscar=maria+gomez'))->assertOk()
            ->assertSee('Gómez')->assertDontSee('Viejo 2');
        $id = $this->enTenant(function () { return Pedido::where('apellido', 'Gómez')->value('id'); });
        $this->actingAs($duenio)->get($this->urlTenant('admin/pedidos?desde=&hasta=&buscar=' . $id))->assertOk()->assertSee('Gómez');
        // Un "número" imposible no rompe.
        $this->actingAs($duenio)->get($this->urlTenant('admin/pedidos?desde=&hasta=&buscar=%231.5'))->assertOk();
        $this->actingAs($duenio)->get($this->urlTenant('admin/pedidos?desde=&hasta=&buscar=%2399999999999999999999'))->assertOk();
    }

    /** @test */
    public function una_direccion_mal_escrita_del_panel_es_no_encontrado_y_no_un_error()
    {
        $duenio = $this->usuario(null);

        foreach (['pedidos/abc', 'proveedores/abc/edit', 'usuarios/abc/edit', 'perfiles/abc/edit', 'monedas/abc/edit',
                  'etiquetas/abc', 'categorias/abc/edit', 'menus/abc/edit', 'pedidos/99999999999999999999'] as $ruta) {
            $this->actingAs($duenio)->get($this->urlTenant('admin/' . $ruta))->assertNotFound();
        }
    }

    /** @test */
    public function un_filtro_que_no_existe_no_se_muestra_como_puesto_ni_rompe()
    {
        $duenio = $this->usuario(null);

        $this->actingAs($duenio)->get($this->urlTenant('admin/productos?stock=raro&precio_desde=abc&disponible=quizas&valor=solo'))->assertOk()
            ->assertDontSee('Stock: raro')->assertDontSee('desde abc')->assertDontSee('Quitar todos los filtros');
        $this->actingAs($duenio)->get($this->urlTenant('admin/productos?precio_desde=500&precio_hasta=100'))->assertOk()
            ->assertSee('Precio: de 100 a 500');

        // Una lista donde va un texto (dirección armada a mano) se ignora.
        foreach (['productos?buscar[]=x&proveedor[]=1&stock[]=con', 'proveedores?buscar[]=x&activo[]=1', 'usuarios?buscar[]=x&perfil[]=1',
                  'monedas?buscar[]=x', 'etiquetas?buscar[]=x', 'pedidos?buscar[]=x'] as $ruta) {
            $this->actingAs($duenio)->get($this->urlTenant('admin/' . $ruta))->assertOk();
        }
    }

    /** @test */
    public function cada_perfil_ve_solo_los_botones_que_puede_usar()
    {
        $this->enTenant(function () {
            \App\Models\Proveedor::create(['nombre' => 'Distribuidora Sur']);
        });

        $html = $this->actingAs($this->usuario('Operador'))->get($this->urlTenant('admin/proveedores'))->assertOk()
            ->assertSee('Distribuidora Sur')->getContent();
        $operador = $this->enTenant(function () { return Perfil::where('nombre', 'Operador')->with('permisos')->first(); });
        foreach (['crear' => 'proveedores/create', 'eliminar' => 'name="_method" value="DELETE"'] as $permiso => $marca) {
            $this->assertSame($operador->tiene('proveedores.' . $permiso), strpos($html, $marca) !== false, 'proveedores.' . $permiso);
        }

        $this->actingAs($this->usuario(null))->get($this->urlTenant('admin/proveedores'))->assertOk()
            ->assertSee('proveedores/create', false)->assertSee('name="_method" value="DELETE"', false);
    }

    /** @test */
    public function volver_despues_de_editar_un_producto_solo_lleva_a_pantallas_del_panel()
    {
        $this->get($this->urlTenant(''));   // deja armada la dirección de esta tienda
        $pedido = $this->urlTenant('admin/pedidos/7');

        $this->assertSame($pedido, \App\Http\Controllers\Admin\ProductoController::vueltaAlPanel($pedido));
        $this->assertNull(\App\Http\Controllers\Admin\ProductoController::vueltaAlPanel('javascript:alert(1)'));
        $this->assertNull(\App\Http\Controllers\Admin\ProductoController::vueltaAlPanel('https://otro-sitio.com/admin/pedidos/7'));
        $this->assertNull(\App\Http\Controllers\Admin\ProductoController::vueltaAlPanel(['x']));
    }
}
