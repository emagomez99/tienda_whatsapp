<?php

namespace App\Support;

/**
 * Precio de venta derivado del costo de compra: convierte el costo a la moneda de
 * venta y le aplica el margen de ganancia.
 *
 * Es el ÚNICO lugar donde vive la fórmula. El recálculo masivo (un solo UPDATE de
 * Postgres, ver Producto::recalcularPreciosEnMargen) no puede reimplementarla por su
 * cuenta: la reconstruye con expresionSql(), que está escrita acá al lado para que
 * las dos no puedan separarse sin que se note.
 *
 * ─── Por qué el cálculo va en dos pasos redondeados ──────────────────────────────
 *
 * La forma directa -- redondear una sola vez al final -- da resultados que Postgres y
 * PHP no siempre comparten. Postgres hace aritmética decimal exacta sobre `numeric`;
 * PHP hace aritmética binaria y su round() corrige el valor a ~15 dígitos
 * significativos antes de redondear. Cuando el producto exacto tiene más de 15
 * dígitos significativos y cae justo al lado de un medio centavo, cada uno redondea
 * para un lado distinto y el precio guardado por el alta deja de coincidir con el que
 * escribe el recálculo masivo.
 *
 * Redondeando en dos pasos, cada valor que entra a un round() es un decimal exacto de
 * a lo sumo 8 decimales (paso 1) o 6 decimales (paso 2), y para precios por debajo de
 * los ocho dígitos eso entra holgado en los 15 significativos que PHP recupera. Con
 * eso los dos lados coinciden por construcción, no por suerte.
 *
 * Además el resultado es el que un humano espera y puede auditar a mano: primero el
 * costo convertido a la moneda de venta, en centavos; después el margen sobre ese
 * costo.
 */
class PrecioVenta
{
    /** Decimales de la cotización y, por lo tanto, del factor de conversión. */
    const ESCALA_FACTOR = 6;

    /** Decimales de cualquier importe en dinero. */
    const ESCALA_MONTO = 2;

    /** @var float|null Precio de venta final. null si no se puede calcular. */
    public $monto;

    /** @var float|null Factor aplicado para pasar de la moneda de compra a la de venta. */
    public $factor;

    /** @var float|null Costo de compra ya convertido a la moneda de venta. */
    public $costoConvertido;

    private function __construct($monto, $factor, $costoConvertido)
    {
        $this->monto           = $monto;
        $this->factor          = $factor;
        $this->costoConvertido = $costoConvertido;
    }

    /**
     * Calcula el precio de venta. Devuelve un resultado indeterminado -- y no una
     * excepción ni un cero -- si falta algún dato: un producto a medio cargar es una
     * situación normal, no un error.
     *
     * Las cotizaciones son "cuántas unidades de la moneda base vale 1 unidad de esta
     * moneda", así que cualquier par convierte pasando por la base y el factor es el
     * cociente entre las dos.
     */
    public static function calcular($precioCompra, $cotizacionCompra, $cotizacionVenta, $margen)
    {
        if ($precioCompra === null || $precioCompra === '' || $margen === null || $margen === '') {
            return self::indeterminado();
        }

        if (!$cotizacionVenta || (float) $cotizacionVenta <= 0 || !$cotizacionCompra || (float) $cotizacionCompra <= 0) {
            return self::indeterminado();
        }

        $factor          = self::factor($cotizacionCompra, $cotizacionVenta);
        $costoConvertido = round((float) $precioCompra * $factor, self::ESCALA_MONTO);
        $monto           = round($costoConvertido * (1 + (float) $margen / 100), self::ESCALA_MONTO);

        return new self($monto, $factor, $costoConvertido);
    }

    /** No hay compra declarada (o está incompleta): el precio no se deriva de nada. */
    public static function indeterminado()
    {
        return new self(null, null, null);
    }

    /**
     * Cuántas unidades de la moneda de venta vale 1 de la moneda de compra.
     * Misma moneda -> exactamente 1, sin pasar por el redondeo.
     */
    public static function factor($cotizacionCompra, $cotizacionVenta)
    {
        if ((float) $cotizacionCompra === (float) $cotizacionVenta) {
            return 1.0;
        }

        return round((float) $cotizacionCompra / (float) $cotizacionVenta, self::ESCALA_FACTOR);
    }

    /** Convierte un importe entre dos monedas, redondeado a centavos. */
    public static function convertir($monto, $cotizacionOrigen, $cotizacionDestino)
    {
        if (!$cotizacionDestino || (float) $cotizacionDestino <= 0) {
            return null;
        }

        return round((float) $monto * self::factor($cotizacionOrigen, $cotizacionDestino), self::ESCALA_MONTO);
    }

    public function esCalculable()
    {
        return $this->monto !== null;
    }

    /**
     * La misma fórmula de calcular(), en SQL, para el recálculo masivo.
     *
     * Si se toca una, hay que tocar la otra: PrecioVentaCoincideConSqlTest verifica
     * que las dos devuelvan exactamente el mismo número.
     *
     * @param string $productos Tabla (o alias) de productos dentro del UPDATE.
     * @param string $compra    Alias de la moneda de compra.
     * @param string $venta     Alias de la moneda de venta.
     */
    public static function expresionSql($productos = 'productos', $compra = 'mc', $venta = 'mv')
    {
        $factor = 'ROUND(' . $compra . '.cotizacion / ' . $venta . '.cotizacion, ' . self::ESCALA_FACTOR . ')';
        $costo  = 'ROUND(' . $productos . '.precio_compra * ' . $factor . ', ' . self::ESCALA_MONTO . ')';

        return 'ROUND(' . $costo . ' * (1 + ' . $productos . '.margen_ganancia / 100), ' . self::ESCALA_MONTO . ')';
    }
}
