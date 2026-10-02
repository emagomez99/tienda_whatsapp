<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\User;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * Vista previa del mensaje de WhatsApp en Ajustes → Pedidos.
 */
class WhatsappVistaPreviaTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testwapreview';
    const TENANT_DOMAIN = 'testwapreview.test';

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

    private function vistaPrevia(array $datos)
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                ['name' => 'Admin', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]
            );
        });

        return $this->actingAs($admin)->postJson($this->urlTenant('admin/configuraciones/vista-previa-whatsapp'), $datos);
    }

    public function test_sin_direccion_no_quedan_restos()
    {
        $mensaje = $this->vistaPrevia([
            'plantilla'       => Configuracion::templateWhatsappDefault(),
            'pedir_direccion' => false,
            'mostrar_precios' => true,
        ])->assertOk()->json('mensaje');

        $this->assertStringContainsString('NUEVO PEDIDO #123', $mensaje);
        $this->assertStringNotContainsString('Dirección', $mensaje);
        $this->assertStringNotContainsString("\n,", $mensaje);
        $this->assertStringContainsString('Total', $mensaje);
    }

    public function test_con_direccion_y_sin_precios()
    {
        $mensaje = $this->vistaPrevia([
            'plantilla'       => Configuracion::templateWhatsappDefault(),
            'pedir_direccion' => true,
            'mostrar_precios' => false,
        ])->assertOk()->json('mensaje');

        $this->assertStringContainsString('Alsina 250, Bahía Blanca', $mensaje);
        $this->assertStringNotContainsString('Total', $mensaje);
    }

    public function test_plantilla_vacia_usa_la_de_fabrica()
    {
        $this->vistaPrevia(['plantilla' => '', 'pedir_direccion' => true, 'mostrar_precios' => true])
            ->assertOk()
            ->assertJsonFragment(['mensaje' => \App\Support\MensajeWhatsapp::armar(
                Configuracion::templateWhatsappDefault(),
                \App\Support\MensajeWhatsapp::valoresDeEjemplo(true, true)
            )]);
    }

    public function test_la_pestana_pedidos_tiene_el_numero_la_direccion_y_la_vista_previa()
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                ['name' => 'Admin', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]
            );
        });

        $html = $this->actingAs($admin)->get($this->urlTenant('admin/configuraciones'))->assertOk()->getContent();

        $panel = substr($html, strpos($html, 'id="pane-whatsapp"'));
        $panel = substr($panel, 0, strpos($panel, 'id="pane-avanzada"'));

        $this->assertStringContainsString('name="whatsapp_admin"', $panel);
        $this->assertStringContainsString('name="pedir_direccion_envio"', $panel);
        $this->assertStringContainsString('id="whatsapp-preview-texto"', $panel);
    }
}
