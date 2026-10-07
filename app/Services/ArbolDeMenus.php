<?php

namespace App\Services;

use App\Models\Menu;

/** El árbol de menús de la tienda: hasta 4 niveles. Ver ArbolOrdenable. */
class ArbolDeMenus extends ArbolOrdenable
{
    /** Nivel más profundo permitido (0 = primer nivel). */
    const NIVEL_MAXIMO = Menu::NIVEL_MAXIMO_PADRE + 1;

    protected function modelo(): string
    {
        return Menu::class;
    }

    public function nivelMaximo(): int
    {
        return self::NIVEL_MAXIMO;
    }

    protected function unElemento(): string
    {
        return 'Un menú';
    }

    protected function despuesDeOrdenar(): void
    {
        Menu::limpiarCache();
    }
}
