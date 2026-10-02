<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\User;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * El alto del logo en la cabecera se elige desde Ajustes, dentro de un rango.
 */
class LogoAltoTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testlogoalto';
    const TENANT_DOMAIN = 'testlogoalto.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    protected function guardarAlto($alto)
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                ['name' => 'Admin', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]
            );
        });

        return $this->actingAs($admin)->put($this->urlTenant('admin/configuraciones'), [
            'nombre_tienda'               => 'Tienda de prueba',
            'mostrar_precios'             => 'true',
            'mostrar_productos_sin_stock' => 'true',
            'mostrar_nombre_tienda'       => 'true',
            'mostrar_proveedor'           => 'false',
            'color_primario'              => '#0d6efd',
            'logo_alto'                   => $alto,
            'posicion_menu'               => 'superior',
            'pedir_direccion_envio'       => 'true',
            'robots_index'                => 'true',
            'ubicacion_activa'            => 'false',
        ]);
    }

    public function test_sin_configurar_usa_el_alto_por_defecto()
    {
        $this->assertSame(Configuracion::LOGO_ALTO_DEFAULT, $this->enTenant(function () {
            return Configuracion::logoAlto();
        }));
    }

    public function test_se_guarda_y_se_aplica_en_la_tienda()
    {
        $this->guardarAlto(60)->assertSessionHasNoErrors();

        $this->get($this->urlTenant('/'))->assertOk()->assertSee('--logo-alto: 60px;', false);
    }

    public function test_fuera_de_rango_se_rechaza()
    {
        $this->guardarAlto(Configuracion::LOGO_ALTO_MAX + 1)->assertSessionHasErrors('logo_alto');
        $this->guardarAlto(Configuracion::LOGO_ALTO_MIN - 1)->assertSessionHasErrors('logo_alto');
    }

    public function test_un_valor_viejo_fuera_de_rango_se_acota()
    {
        $this->enTenant(function () {
            Configuracion::establecer('logo_alto', '500');
        });

        $this->assertSame(Configuracion::LOGO_ALTO_MAX, $this->enTenant(function () {
            return Configuracion::logoAlto();
        }));
    }
}
