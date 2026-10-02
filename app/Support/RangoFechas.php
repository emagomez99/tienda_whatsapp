<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Rango de fechas para filtrar listados, con los dos extremos opcionales e inclusivos
 * (un pedido del "hasta" a las 23:59 entra). En la URL viaja como desde/hasta en
 * formato YYYY-MM-DD; un valor inválido se ignora en vez de romper el listado, y si
 * vienen invertidos se dan vuelta.
 */
class RangoFechas
{
    /** @var \Carbon\Carbon|null */
    private $desde;

    /** @var \Carbon\Carbon|null */
    private $hasta;

    private function __construct(Carbon $desde = null, Carbon $hasta = null)
    {
        if ($desde && $hasta && $desde->gt($hasta)) {
            list($desde, $hasta) = [$hasta, $desde];
        }

        $this->desde = $desde ? $desde->copy()->startOfDay() : null;
        $this->hasta = $hasta ? $hasta->copy()->endOfDay() : null;
    }

    public static function desdeTextos($desde, $hasta)
    {
        return new self(self::fecha($desde), self::fecha($hasta));
    }

    public static function delMes(Mes $mes)
    {
        return new self($mes->inicio(), $mes->fin());
    }

    private static function fecha($texto)
    {
        if (! is_string($texto) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
            return null;
        }

        $fecha = Carbon::createFromFormat('!Y-m-d', $texto);

        // createFromFormat acepta 2026-02-31 desbordando al mes siguiente: se descarta.
        return $fecha && $fecha->format('Y-m-d') === $texto ? $fecha : null;
    }

    public function estaActivo()
    {
        return $this->desde !== null || $this->hasta !== null;
    }

    /** Filtra $query por $columna dentro del rango. Sin extremos, no filtra. */
    public function aplicarA($query, $columna)
    {
        if ($this->desde) {
            $query->where($columna, '>=', $this->desde);
        }

        if ($this->hasta) {
            $query->where($columna, '<=', $this->hasta);
        }

        return $query;
    }

    /** Valores para los inputs y la URL ("2026-09-01"), o '' si el extremo está abierto. */
    public function desdeTexto()
    {
        return $this->desde ? $this->desde->format('Y-m-d') : '';
    }

    public function hastaTexto()
    {
        return $this->hasta ? $this->hasta->format('Y-m-d') : '';
    }

    /** Parámetros de URL del rango, para armar links que lo conserven. */
    public function parametros()
    {
        return array_filter(['desde' => $this->desdeTexto(), 'hasta' => $this->hastaTexto()]);
    }
}
