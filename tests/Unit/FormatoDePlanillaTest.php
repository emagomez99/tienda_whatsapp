<?php

namespace Tests\Unit;

use App\Support\Planilla\FormatoDePlanilla;
use PHPUnit\Framework\TestCase;

/** Cómo se leen las celdas de la planilla de productos (lo que escribe la gente). */
class FormatoDePlanillaTest extends TestCase
{
    /** @test */
    public function lee_los_numeros_como_se_escriben_aca_y_como_los_guarda_excel()
    {
        $casos = [
            '1.500,50' => 1500.5, '1500,5' => 1500.5, '1500.5' => 1500.5, '$ 1.500' => 1500.0,
            '12.345.678' => 12345678.0, '1,5' => 1.5, '0' => 0.0, '1,500.25' => 1500.25,
        ];
        foreach ($casos as $escrito => $esperado) {
            $this->assertSame($esperado, FormatoDePlanilla::leerNumero($escrito), "«{$escrito}»");
        }
        $this->assertSame(1500.5, FormatoDePlanilla::leerNumero(1500.5));
        $this->assertNull(FormatoDePlanilla::leerNumero(''));
        $this->assertFalse(FormatoDePlanilla::leerNumero('mil'));
    }

    /** @test */
    public function lee_si_y_no_en_sus_formas_habituales()
    {
        foreach (['Sí', 'si', 'S', 'x', '1', 'VERDADERO'] as $si) {
            $this->assertTrue(FormatoDePlanilla::leerSiNo($si), $si);
        }
        foreach (['No', 'n', '0', 'falso'] as $no) {
            $this->assertFalse(FormatoDePlanilla::leerSiNo($no), $no);
        }
        $this->assertNull(FormatoDePlanilla::leerSiNo(''));

        $entendido = true;
        $this->assertNull(FormatoDePlanilla::leerSiNo('quizás', $entendido));
        $this->assertFalse($entendido);
    }

    /** @test */
    public function reconoce_las_columnas_por_su_titulo_sin_importar_tildes_ni_el_asterisco()
    {
        $resultado = FormatoDePlanilla::interpretarTitulos(
            ['CODIGO', 'Nombre', 'Categorias', 'Imagen 2', 'Sabor *', 'Color raro', ''],
            ['sabor' => 7]
        );

        $this->assertSame(['tipo' => 'fija', 'clave' => 'codigo'], $resultado['columnas'][0]);
        $this->assertSame(['tipo' => 'fija', 'clave' => 'categorias'], $resultado['columnas'][2]);
        $this->assertSame(['tipo' => 'imagen', 'clave' => 2], $resultado['columnas'][3]);
        $this->assertSame(['tipo' => 'caracteristica', 'clave' => 7], $resultado['columnas'][4]);
        $this->assertSame(['Color raro'], $resultado['desconocidas']);
    }

    /** @test */
    public function separa_las_categorias_por_coma()
    {
        $this->assertSame(['Bebidas > Gaseosas', 'Ofertas'], FormatoDePlanilla::leerCategorias(' Bebidas > Gaseosas ,Ofertas, '));
    }
}
