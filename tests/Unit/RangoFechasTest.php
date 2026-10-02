<?php

namespace Tests\Unit;

use App\Support\Mes;
use App\Support\RangoFechas;
use Carbon\Carbon;
use Tests\TestCase;

class RangoFechasTest extends TestCase
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

    public function test_sin_fechas_no_esta_activo()
    {
        $rango = RangoFechas::desdeTextos(null, '');

        $this->assertFalse($rango->estaActivo());
        $this->assertSame([], $rango->parametros());
    }

    public function test_fechas_invalidas_se_ignoran()
    {
        $rango = RangoFechas::desdeTextos('2026-02-31', '15/10/2026');

        $this->assertFalse($rango->estaActivo());
    }

    public function test_un_solo_extremo_deja_el_otro_abierto()
    {
        $rango = RangoFechas::desdeTextos('2026-09-01', null);

        $this->assertTrue($rango->estaActivo());
        $this->assertSame('2026-09-01', $rango->desdeTexto());
        $this->assertSame('', $rango->hastaTexto());
        $this->assertSame(['desde' => '2026-09-01'], $rango->parametros());
    }

    public function test_fechas_invertidas_se_dan_vuelta()
    {
        $rango = RangoFechas::desdeTextos('2026-09-30', '2026-09-01');

        $this->assertSame('2026-09-01', $rango->desdeTexto());
        $this->assertSame('2026-09-30', $rango->hastaTexto());
    }

    public function test_del_mes_cubre_el_mes_entero()
    {
        $this->assertSame(
            ['desde' => '2026-09-01', 'hasta' => '2026-09-30'],
            RangoFechas::delMes(Mes::desdeParametro('2026-09'))->parametros()
        );
    }
}
