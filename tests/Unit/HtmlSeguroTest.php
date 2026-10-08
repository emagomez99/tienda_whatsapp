<?php

namespace Tests\Unit;

use App\Support\HtmlSeguro;
use PHPUnit\Framework\TestCase;

/** La descripción detallada se muestra como HTML en la tienda: nada que ejecute código. */
class HtmlSeguroTest extends TestCase
{
    /** @test */
    public function deja_el_formato_del_editor()
    {
        $html = '<p><strong>Ibuprofeno</strong> 400 mg, <em>venta libre</em>.</p><ol><li class="ql-indent-1">Uno</li></ol><p><a href="https://ejemplo.com/prospecto">Prospecto</a></p>';

        $this->assertSame(
            '<p><strong>Ibuprofeno</strong> 400 mg, <em>venta libre</em>.</p><ol><li class="ql-indent-1">Uno</li></ol><p><a href="https://ejemplo.com/prospecto" rel="nofollow noopener">Prospecto</a></p>',
            HtmlSeguro::limpiar($html)
        );
    }

    /** @test */
    public function saca_scripts_eventos_y_enlaces_que_ejecutan_codigo()
    {
        $peligrosos = [
            '<script>alert(1)</script>',
            '<img src="x" onerror="alert(1)">',
            '<a href="javascript:alert(1)">clic</a>',
            '<a href="JaVa&#x09;ScRiPt:alert(1)">clic</a>',
            '<a href=" javascript:alert(1)">clic</a>',
            '<img src="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">',
            '<svg onload="alert(1)"><circle/></svg>',
            '<iframe src="https://malo.com"></iframe>',
            '<p style="background:url(javascript:alert(1))" onclick="alert(1)">hola</p>',
            '<div><span onmouseover="alert(1)">hola</span></div>',
            '<style>body{display:none}</style>',
            '<!-- <script>alert(1)</script> -->',
            '<p class="x" id="y">hola</p>',
        ];

        foreach ($peligrosos as $html) {
            $limpio = HtmlSeguro::limpiar($html);
            $this->assertDoesNotMatchRegularExpression('/<script|on[a-z]+=|javascript:|data:|<svg|<iframe|<style|style=|id=|class="x"/i', $limpio, $html);
        }

        // El texto de lo que no está permitido queda.
        $this->assertSame('hola', HtmlSeguro::limpiar('<div><span onmouseover="alert(1)">hola</span></div>'));
        $this->assertSame('<p>hola</p>', HtmlSeguro::limpiar('<p style="color:red" onclick="alert(1)">hola</p>'));
    }

    /** @test */
    public function respeta_tildes_y_vacios()
    {
        $this->assertSame('<p>Ñandú, pañal, acción</p>', HtmlSeguro::limpiar('<p>Ñandú, pañal, acción</p>'));
        $this->assertSame('', HtmlSeguro::limpiar(null));
        $this->assertSame('', HtmlSeguro::limpiar('   '));
        $this->assertSame('', HtmlSeguro::limpiar('<script>x</script>'));
    }
}
