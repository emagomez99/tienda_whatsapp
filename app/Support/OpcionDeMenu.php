<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Una opción del menú de la tienda, tal como la dibujan la barra, el lateral y el
 * menú del celular.
 *
 * El menú puede salir de los menús armados a mano o de las categorías (ver
 * MenuDeLaTienda): las vistas reciben siempre esto y no tienen que saber cuál.
 */
class OpcionDeMenu
{
    /** @var string único en la página (sirve para los id del HTML): "m12", "c5" */
    private $clave;

    /** @var string */
    private $nombre;

    /** @var string|null null = sólo agrupa, no lleva a ningún lado */
    private $url;

    /** @var Collection|OpcionDeMenu[] */
    private $hijos;

    public function __construct(string $clave, string $nombre, ?string $url, Collection $hijos = null)
    {
        $this->clave  = $clave;
        $this->nombre = $nombre;
        $this->url    = $url;
        $this->hijos  = $hijos ?: collect();
    }

    public function clave(): string
    {
        return $this->clave;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function url(): string
    {
        return $this->url ?? '#';
    }

    public function esContenedor(): bool
    {
        return $this->url === null;
    }

    /** @return Collection|OpcionDeMenu[] */
    public function hijos(): Collection
    {
        return $this->hijos;
    }

    public function tieneHijos(): bool
    {
        return $this->hijos->isNotEmpty();
    }
}
