<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\User;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * El color principal de la tienda es libre: se guarda desde Ajustes, se aplica a la
 * tienda con un texto legible encima, y las tiendas que tenían una paleta cerrada
 * se siguen viendo igual sin migrar nada.
 */
class ColorPrimarioTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testcolor';
    const TENANT_DOMAIN = 'testcolor.test';

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

    protected function comoAdmin()
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                [
                    'name'     => 'Admin de prueba',
                    'password' => bcrypt('secreto'),
                    'is_admin' => true,
                    'activo'   => true,
                ]
            );
        });

        return $this->actingAs($admin);
    }

    protected function guardarColor($color)
    {
        return $this->comoAdmin()->put($this->urlTenant('admin/configuraciones'), [
            'nombre_tienda'               => 'Tienda de prueba',
            'mostrar_precios'             => 'true',
            'mostrar_productos_sin_stock' => 'true',
            'mostrar_nombre_tienda'       => 'true',
            'mostrar_proveedor'           => 'false',
            'color_primario'              => $color,
            'posicion_menu'               => 'superior',
            'pedir_direccion_envio'       => 'true',
            'robots_index'                => 'true',
            'ubicacion_activa'            => 'false',
        ]);
    }

    protected function colorGuardado()
    {
        return $this->enTenant(function () {
            return Configuracion::colorPrimario()->hex();
        });
    }

    public function test_se_puede_guardar_cualquier_color()
    {
        $this->guardarColor('#A1B2C3')->assertSessionHasNoErrors();

        $this->assertSame('#a1b2c3', $this->colorGuardado());
    }

    public function test_ajustes_muestra_el_selector_y_la_vista_previa_con_el_color_actual()
    {
        $this->guardarColor('#a1b2c3');

        $this->comoAdmin()->get($this->urlTenant('admin/configuraciones'))
            ->assertOk()
            ->assertSee('type="color"', false)
            ->assertSee('value="#a1b2c3"', false)
            ->assertSee('id="cabecera_preview"', false)
            ->assertSee('background-color: #a1b2c3; color: #212529;', false);
    }

    public function test_guarda_el_por_encargue_por_defecto()
    {
        // Mismo payload que guardarColor(), más el ajuste nuevo.
        $this->comoAdmin()->put($this->urlTenant('admin/configuraciones'), [
            'nombre_tienda'               => 'Tienda de prueba',
            'mostrar_precios'             => 'true',
            'mostrar_productos_sin_stock' => 'true',
            'mostrar_nombre_tienda'       => 'true',
            'mostrar_proveedor'           => 'false',
            'por_encargue_por_defecto'    => 'true',
            'color_primario'              => '#0d6efd',
            'posicion_menu'               => 'superior',
            'pedir_direccion_envio'       => 'true',
            'robots_index'                => 'true',
            'ubicacion_activa'            => 'false',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($this->enTenant(function () {
            return Configuracion::porEncarguePorDefecto();
        }));
    }

    public function test_un_color_mal_formado_se_rechaza()
    {
        $this->guardarColor('azul')->assertSessionHasErrors('color_primario');
        $this->guardarColor('#fff')->assertSessionHasErrors('color_primario');
    }

    public function test_tienda_con_paleta_vieja_conserva_su_color()
    {
        $this->enTenant(function () {
            Configuracion::establecer('paleta', 'verde', 'Paleta de colores');
        });

        $this->assertSame('#198754', $this->colorGuardado());
    }

    public function test_sin_nada_configurado_usa_el_azul_por_defecto()
    {
        $this->assertSame('#0d6efd', $this->colorGuardado());
    }

    public function test_color_claro_pone_texto_oscuro_en_la_tienda()
    {
        $this->guardarColor('#ffeb3b');

        $html = $this->get($this->urlTenant('/'))->assertOk()->getContent();

        $this->assertStringContainsString('--color-primary: #ffeb3b;', $html);
        $this->assertStringContainsString('--color-primary-texto: #212529;', $html);
        $this->assertStringContainsString('navbar-light navbar-custom', $html);
    }

    public function test_el_admin_usa_el_mismo_color_con_texto_legible()
    {
        $this->guardarColor('#ffeb3b');

        $this->comoAdmin()->get($this->urlTenant('admin/configuraciones'))
            ->assertOk()
            ->assertSee('--admin-color: #ffeb3b;', false)
            ->assertSee('--admin-texto: #212529;', false)
            ->assertSee('navbar-light admin-navbar', false);
    }

    public function test_color_oscuro_mantiene_texto_blanco_en_la_tienda()
    {
        $this->guardarColor('#4a148c');

        $html = $this->get($this->urlTenant('/'))->assertOk()->getContent();

        $this->assertStringContainsString('--color-primary-texto: #ffffff;', $html);
        $this->assertStringContainsString('navbar-dark navbar-custom', $html);
    }
}
