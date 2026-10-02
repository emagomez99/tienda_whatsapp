<?php

namespace Tests\Unit;

use App\Support\ColorHex;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * El color principal es libre, así que el texto que va encima (cabecera, botones,
 * menú lateral) se elige por contraste en vez de asumir blanco.
 */
class ColorHexTest extends TestCase
{
    public function test_normaliza_a_minusculas()
    {
        $this->assertSame('#0d6efd', ColorHex::desde('#0D6EFD')->hex());
    }

    public function test_rechaza_formatos_que_no_son_rrggbb()
    {
        foreach (['0d6efd', '#fff', '#0d6efdff', '#gggggg', '', null, 'red'] as $invalido) {
            $this->assertFalse(ColorHex::esValido($invalido), var_export($invalido, true));
        }

        $this->expectException(InvalidArgumentException::class);
        ColorHex::desde('#fff');
    }

    public function test_colores_oscuros_llevan_texto_blanco()
    {
        foreach (['#0d6efd', '#198754', '#dc3545', '#6f42c1', '#212529', '#000000'] as $oscuro) {
            $this->assertSame(ColorHex::TEXTO_CLARO, ColorHex::desde($oscuro)->textoLegible()->hex(), $oscuro);
            $this->assertFalse(ColorHex::desde($oscuro)->esClaro(), $oscuro);
        }
    }

    public function test_colores_claros_llevan_texto_oscuro()
    {
        foreach (['#ffffff', '#ffc107', '#0dcaf0', '#fd7e14', '#f8bbd0'] as $claro) {
            $this->assertSame(ColorHex::TEXTO_OSCURO, ColorHex::desde($claro)->textoLegible()->hex(), $claro);
            $this->assertTrue(ColorHex::desde($claro)->esClaro(), $claro);
        }
    }

    public function test_contraste_wcag()
    {
        $blanco = ColorHex::desde('#ffffff');
        $negro  = ColorHex::desde('#000000');

        $this->assertEqualsWithDelta(21.0, $blanco->contrasteCon($negro), 0.01);
        $this->assertEqualsWithDelta(1.0, $blanco->contrasteCon($blanco), 0.01);
    }

    public function test_rgb_para_css()
    {
        $this->assertSame('13, 110, 253', ColorHex::desde('#0d6efd')->rgb());
    }
}
