<?php

namespace Tests\Unit;

use App\Models\Configuracion;
use App\Support\MensajeWhatsapp;
use Tests\TestCase;

class MensajeWhatsappTest extends TestCase
{
    private function valores(array $cambios = [])
    {
        return array_merge(MensajeWhatsapp::valoresDeEjemplo(true, true), $cambios);
    }

    public function test_reemplaza_las_variables()
    {
        $this->assertSame(
            "Pedido #123 de Juan Pérez",
            MensajeWhatsapp::armar('Pedido #{pedido_id} de {nombre} {apellido}', $this->valores())
        );
    }

    public function test_sin_direccion_no_quedan_restos_ni_el_titulo_de_la_seccion()
    {
        $mensaje = MensajeWhatsapp::armar(
            Configuracion::templateWhatsappDefault(),
            MensajeWhatsapp::valoresDeEjemplo(false, true)
        );

        $this->assertStringNotContainsString('Dirección', $mensaje);
        $this->assertStringNotContainsString("\n,", $mensaje);
        $this->assertStringNotContainsString('CP:', $mensaje);
        $this->assertStringNotContainsString("\n\n\n", $mensaje);
        // Las demás secciones siguen separadas por un renglón en blanco.
        $this->assertStringContainsString("Celular: +54 9 291 555-1234\n\n*Productos:*", $mensaje);
    }

    public function test_con_direccion_la_seccion_queda_completa()
    {
        $mensaje = MensajeWhatsapp::armar(Configuracion::templateWhatsappDefault(), $this->valores());

        $this->assertStringContainsString("*Dirección:*\nAlsina 250, Bahía Blanca\nBuenos Aires - CP: B8000", $mensaje);
    }

    public function test_una_vacia_se_lleva_su_separador_y_su_etiqueta()
    {
        $plantilla = "{direccion}, {localidad}\n{provincia} - CP: {cp}";

        $this->assertSame(
            "Bahía Blanca\nBuenos Aires",
            MensajeWhatsapp::armar($plantilla, $this->valores(['direccion' => '', 'cp' => '']))
        );
        $this->assertSame(
            "Alsina 250\nCP: B8000",
            MensajeWhatsapp::armar($plantilla, $this->valores(['localidad' => '', 'provincia' => '']))
        );
    }

    public function test_una_linea_con_todas_sus_variables_vacias_se_borra()
    {
        $this->assertSame(
            "Hola\nChau",
            MensajeWhatsapp::armar("Hola\nEmail: {email}\nChau", $this->valores(['email' => '']))
        );
    }

    public function test_sin_precios_se_borra_la_linea_del_total()
    {
        $mensaje = MensajeWhatsapp::armar("*Productos:*\n{productos}\n{total}", MensajeWhatsapp::valoresDeEjemplo(true, false));

        $this->assertStringNotContainsString('Total', $mensaje);
        $this->assertStringNotContainsString('$', $mensaje);
        $this->assertStringEndsWith('Crema Hidratante (CRM-050) x1', $mensaje);
    }

    public function test_las_lineas_sin_variables_y_las_desconocidas_quedan_igual()
    {
        $this->assertSame(
            "🛒 *Gracias* {desconocida}\n\nPedido 123",
            MensajeWhatsapp::armar("🛒 *Gracias* {desconocida}\n\nPedido {pedido_id}", $this->valores())
        );
    }
}
