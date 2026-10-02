<?php

namespace Tests\Unit;

use App\Support\Mes;
use Carbon\Carbon;
use Tests\TestCase;

class MesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 15, 12));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sin_parametro_o_invalido_es_el_mes_actual()
    {
        foreach ([null, '', 'septiembre', '2026-13', '2026-9', '26-09'] as $valor) {
            $this->assertSame('2026-10', Mes::desdeParametro($valor)->parametro(), var_export($valor, true));
        }
    }

    public function test_un_mes_futuro_se_toma_como_el_actual()
    {
        $this->assertSame('2026-10', Mes::desdeParametro('2026-11')->parametro());
        $this->assertSame('2026-10', Mes::desdeParametro('2027-01')->parametro());
    }

    public function test_un_mes_pasado_se_respeta()
    {
        $mes = Mes::desdeParametro('2026-09');

        $this->assertSame('2026-09', $mes->parametro());
        $this->assertFalse($mes->esActual());
        $this->assertSame('septiembre 2026', $mes->nombre());
    }

    public function test_el_periodo_cubre_el_mes_entero()
    {
        $mes = Mes::desdeParametro('2026-02');

        $this->assertSame('2026-02-01 00:00:00', $mes->inicio()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-28 23:59:59', $mes->fin()->format('Y-m-d H:i:s'));
    }

    public function test_navegacion_entre_meses()
    {
        $septiembre = Mes::desdeParametro('2026-09');

        $this->assertSame('2026-08', $septiembre->anterior()->parametro());
        $this->assertSame('2026-10', $septiembre->siguiente()->parametro());
        $this->assertNull(Mes::actual()->siguiente(), 'no hay mes siguiente al actual');
        $this->assertSame('2025-12', Mes::desdeParametro('2026-01')->anterior()->parametro());
    }

    public function test_meses_hasta_hoy_del_mas_reciente_al_mas_viejo()
    {
        $meses = Mes::hastaHoyDesde(Mes::desdeParametro('2026-07'));

        $this->assertSame(['2026-10', '2026-09', '2026-08', '2026-07'], array_map(function ($mes) {
            return $mes->parametro();
        }, $meses));
    }
}
