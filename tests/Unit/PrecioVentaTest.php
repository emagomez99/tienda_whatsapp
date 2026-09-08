<?php

namespace Tests\Unit;

use App\Support\PrecioVenta;
use Tests\TestCase;

/**
 * La cuenta del precio de venta, sin base de datos.
 *
 * Que el mismo número salga también del UPDATE masivo de Postgres lo verifica
 * Tests\Feature\PrecioProductoTest, que necesita un tenant real para comparar.
 */
class PrecioVentaTest extends TestCase
{
    /** Cotizaciones de ejemplo, con el peso de base. */
    const ARS = 1;
    const USD = 1500;
    const EUR = 1650;

    /** @test */
    public function convierte_de_una_moneda_a_la_base()
    {
        // 100 dólares a 1500 pesos cada uno, sin margen.
        $calculo = PrecioVenta::calcular(100, self::USD, self::ARS, 0);

        $this->assertSame(150000.0, $calculo->monto);
        $this->assertTrue($calculo->esCalculable());
    }

    /** @test */
    public function convierte_entre_dos_monedas_pasando_por_la_base()
    {
        // 1 USD = 1500/1650 EUR = 0,909091 EUR (redondeado a los 6 decimales de la
        // cotización). 100 USD -> 90,91 EUR.
        $calculo = PrecioVenta::calcular(100, self::USD, self::EUR, 0);

        $this->assertSame(0.909091, $calculo->factor);
        $this->assertSame(90.91, $calculo->monto);
    }

    /** @test */
    public function con_la_misma_moneda_de_compra_y_de_venta_el_factor_es_uno()
    {
        $calculo = PrecioVenta::calcular(250, self::USD, self::USD, 0);

        $this->assertSame(1.0, $calculo->factor);
        $this->assertSame(250.0, $calculo->monto);
    }

    /** @test */
    public function la_cotizacion_de_la_base_contra_si_misma_es_uno()
    {
        $this->assertSame(1.0, PrecioVenta::factor(self::ARS, self::ARS));
        $this->assertSame(1.0, PrecioVenta::factor(self::USD, self::USD));
    }

    /** @test */
    public function un_margen_cero_deja_el_costo_convertido()
    {
        $calculo = PrecioVenta::calcular(1234.56, self::ARS, self::ARS, 0);

        $this->assertSame(1234.56, $calculo->monto);
        $this->assertSame(1234.56, $calculo->costoConvertido);
    }

    /** @test */
    public function aplica_el_margen_sobre_el_costo_convertido()
    {
        // 10 dólares a 1500 = 15.000 pesos, más 40% = 21.000.
        $calculo = PrecioVenta::calcular(10, self::USD, self::ARS, 40);

        $this->assertSame(15000.0, $calculo->costoConvertido);
        $this->assertSame(21000.0, $calculo->monto);
    }

    /** @test */
    public function soporta_margenes_altos()
    {
        $this->assertSame(2500.0, PrecioVenta::calcular(1000, self::ARS, self::ARS, 150)->monto);
        $this->assertSame(10100.0, PrecioVenta::calcular(100, self::ARS, self::ARS, 10000)->monto);
    }

    /**
     * El medio centavo redondea para arriba (mismo criterio que ROUND de Postgres:
     * half away from zero). Es el caso donde PHP y Postgres se separan si el cálculo
     * no está escrito con cuidado.
     *
     * @test
     */
    public function el_medio_centavo_redondea_para_arriba()
    {
        $this->assertSame(1.01, PrecioVenta::calcular(1.00, self::ARS, self::ARS, 0.5)->monto);
        $this->assertSame(3.02, PrecioVenta::calcular(3.00, self::ARS, self::ARS, 0.5)->monto);
        $this->assertSame(1.73, PrecioVenta::calcular(1.15, self::ARS, self::ARS, 50)->monto);
        $this->assertSame(2.53, PrecioVenta::calcular(2.02, self::ARS, self::ARS, 25)->monto);
    }

    /** @test */
    public function el_resultado_siempre_queda_en_dos_decimales()
    {
        $monto = PrecioVenta::calcular(33.33, self::ARS, self::ARS, 33.33)->monto;

        $this->assertSame(44.44, $monto);
        $this->assertSame($monto, round($monto, 2));
    }

    /** @test */
    public function sin_compra_declarada_no_hay_precio_que_calcular()
    {
        $sinPrecio = PrecioVenta::calcular(null, self::USD, self::ARS, 40);
        $sinMargen = PrecioVenta::calcular(100, self::USD, self::ARS, null);

        $this->assertFalse($sinPrecio->esCalculable());
        $this->assertNull($sinPrecio->monto);
        $this->assertFalse($sinMargen->esCalculable());
        $this->assertFalse(PrecioVenta::indeterminado()->esCalculable());
    }

    /** @test */
    public function sin_cotizacion_valida_no_hay_precio_que_calcular()
    {
        $this->assertFalse(PrecioVenta::calcular(100, self::USD, 0, 40)->esCalculable());
        $this->assertFalse(PrecioVenta::calcular(100, self::USD, null, 40)->esCalculable());
        $this->assertFalse(PrecioVenta::calcular(100, 0, self::ARS, 40)->esCalculable());
    }

    /** @test */
    public function convertir_devuelve_el_importe_en_la_moneda_destino()
    {
        $this->assertSame(150000.0, PrecioVenta::convertir(100, self::USD, self::ARS));
        $this->assertSame(90.91, PrecioVenta::convertir(100, self::USD, self::EUR));
        $this->assertNull(PrecioVenta::convertir(100, self::USD, 0));
    }
}
