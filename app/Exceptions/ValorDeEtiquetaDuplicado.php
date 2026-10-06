<?php

namespace App\Exceptions;

use App\Models\EtiquetaValor;
use RuntimeException;

/**
 * Se quiso renombrar un valor a uno que ya existe en la misma etiqueta.
 *
 * No se fusionan solos: unir dos valores mueve productos de uno a otro y no se puede
 * deshacer, así que quien renombra tiene que confirmarlo (EtiquetaValor::fusionarEn).
 */
class ValorDeEtiquetaDuplicado extends RuntimeException
{
    /** @var EtiquetaValor */
    private $existente;

    public function __construct(EtiquetaValor $existente)
    {
        parent::__construct("Ya existe el valor «{$existente->valor}» en esta etiqueta.");

        $this->existente = $existente;
    }

    public function existente(): EtiquetaValor
    {
        return $this->existente;
    }
}
