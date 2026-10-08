<?php

namespace App\Support;

/**
 * Una característica que pide una categoría (o un producto), ya resuelta la
 * herencia: si es obligatoria y de dónde viene.
 */
class Caracteristica
{
    /** @var int */
    private $etiquetaId;

    /** @var string */
    private $nombre;

    /** @var bool */
    private $visible;

    /** @var bool */
    private $obligatoria;

    /** @var int|null la categoría que la define; null = global (todos los productos) */
    private $categoriaId;

    /** @var string|null el nombre de esa categoría */
    private $origen;

    public function __construct(int $etiquetaId, string $nombre, bool $visible, bool $obligatoria, ?int $categoriaId, ?string $origen)
    {
        $this->etiquetaId  = $etiquetaId;
        $this->nombre      = $nombre;
        $this->visible     = $visible;
        $this->obligatoria = $obligatoria;
        $this->categoriaId = $categoriaId;
        $this->origen      = $origen;
    }

    public function etiquetaId(): int { return $this->etiquetaId; }

    public function nombre(): string { return $this->nombre; }

    public function visible(): bool { return $this->visible; }

    public function obligatoria(): bool { return $this->obligatoria; }

    public function categoriaId(): ?int { return $this->categoriaId; }

    public function origen(): ?string { return $this->origen; }

    public function esGlobal(): bool { return $this->categoriaId === null; }

    /** La misma, obligatoria (un producto con dos categorías: alcanza con que una la exija). */
    public function obligatoriaSi(bool $obligatoria): self
    {
        $copia = clone $this;
        $copia->obligatoria = $this->obligatoria || $obligatoria;
        return $copia;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->etiquetaId, 'nombre' => $this->nombre, 'visible' => $this->visible,
            'obligatoria' => $this->obligatoria, 'categoria_id' => $this->categoriaId, 'origen' => $this->origen,
        ];
    }
}
