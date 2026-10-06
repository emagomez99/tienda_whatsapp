<?php

namespace App\Support;

use App\Models\EtiquetaValor;
use JsonSerializable;

/**
 * Qué pasa con lo que se escribió en el valor de una etiqueta, para avisarlo en el
 * formulario de producto mientras se carga:
 *
 *   - existente: ya es un valor de la etiqueta (sin importar mayúsculas ni espacios);
 *   - nuevo:     se va a crear al guardar; si se parece mucho a uno existente se
 *                propone ese, que suele ser un error de tipeo ("Notebok");
 *   - vacio:     no hay nada escrito.
 */
class EstadoValorEtiqueta implements JsonSerializable
{
    const EXISTENTE = 'existente';
    const NUEVO     = 'nuevo';
    const VACIO     = 'vacio';

    /** @var string */
    private $estado;

    /** @var EtiquetaValor|null */
    private $valor;

    /** @var EtiquetaValor|null */
    private $parecido;

    private function __construct(string $estado, ?EtiquetaValor $valor, ?EtiquetaValor $parecido)
    {
        $this->estado   = $estado;
        $this->valor    = $valor;
        $this->parecido = $parecido;
    }

    /** @param EtiquetaValor $valor con asignaciones_count cargado */
    public static function existente(EtiquetaValor $valor): self
    {
        return new self(self::EXISTENTE, $valor, null);
    }

    /** @param EtiquetaValor|null $parecido con asignaciones_count cargado */
    public static function nuevo(?EtiquetaValor $parecido): self
    {
        return new self(self::NUEVO, null, $parecido);
    }

    public static function vacio(): self
    {
        return new self(self::VACIO, null, null);
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function parecido(): ?EtiquetaValor
    {
        return $this->parecido;
    }

    public function jsonSerialize()
    {
        return [
            'estado'    => $this->estado,
            'valor'     => $this->valor ? $this->valor->valor : null,
            'productos' => $this->valor ? (int) $this->valor->asignaciones_count : 0,
            'parecido'  => $this->parecido ? [
                'valor'     => $this->parecido->valor,
                'productos' => (int) $this->parecido->asignaciones_count,
            ] : null,
        ];
    }
}
