<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Un mes calendario, para filtrar estadísticas por período (ej. el dashboard).
 *
 * Se identifica en la URL como "YYYY-MM". Un valor inválido o un mes futuro se toma
 * como el mes actual: no hay datos que mostrar del futuro y un parámetro roto no
 * debería romper la pantalla.
 */
class Mes
{
    /** @var \Carbon\Carbon primer instante del mes */
    private $inicio;

    private function __construct(Carbon $fecha)
    {
        $this->inicio = $fecha->copy()->startOfMonth();
    }

    public static function actual()
    {
        return new self(Carbon::now());
    }

    public static function de(Carbon $fecha)
    {
        return new self($fecha);
    }

    public static function desdeParametro($valor)
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)) {
            return self::actual();
        }

        $mes = new self(Carbon::createFromFormat('!Y-m', $valor));

        return $mes->esPosteriorA(self::actual()) ? self::actual() : $mes;
    }

    /**
     * Meses desde $desde hasta el actual, del más reciente al más viejo.
     *
     * @return self[]
     */
    public static function hastaHoyDesde(self $desde)
    {
        $meses = [];

        for ($mes = self::actual(); ! $mes->esAnteriorA($desde); $mes = $mes->anterior()) {
            $meses[] = $mes;
        }

        return $meses;
    }

    public function inicio()
    {
        return $this->inicio->copy();
    }

    public function fin()
    {
        return $this->inicio->copy()->endOfMonth();
    }

    /** Valor para la URL: "2026-09". */
    public function parametro()
    {
        return $this->inicio->format('Y-m');
    }

    /** "septiembre 2026" */
    public function nombre()
    {
        return $this->inicio->translatedFormat('F Y');
    }

    /** "sep" */
    public function nombreCorto()
    {
        return $this->inicio->translatedFormat('M');
    }

    public function esActual()
    {
        return $this->equals(self::actual());
    }

    public function anterior()
    {
        return new self($this->inicio->copy()->subMonth());
    }

    /** El mes siguiente, o null si este es el actual (no hay futuro que mostrar). */
    public function siguiente()
    {
        return $this->esActual() ? null : new self($this->inicio->copy()->addMonth());
    }

    public function equals(self $otro)
    {
        return $this->parametro() === $otro->parametro();
    }

    public function esAnteriorA(self $otro)
    {
        return $this->inicio->lt($otro->inicio);
    }

    public function esPosteriorA(self $otro)
    {
        return $this->inicio->gt($otro->inicio);
    }
}
