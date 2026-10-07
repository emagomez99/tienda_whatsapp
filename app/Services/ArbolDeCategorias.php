<?php

namespace App\Services;

use App\Models\Categoria;

/** El árbol de categorías: hasta 3 niveles (Deportes › Pádel › Paletas). Ver ArbolOrdenable. */
class ArbolDeCategorias extends ArbolOrdenable
{
    const NIVEL_MAXIMO = Categoria::NIVEL_MAXIMO;

    protected function modelo(): string
    {
        return Categoria::class;
    }

    public function nivelMaximo(): int
    {
        return self::NIVEL_MAXIMO;
    }

    protected function unElemento(): string
    {
        return 'Una categoría';
    }
}
