<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Cuida que un listado paginado no quede en una página que ya no existe (se borró
 * lo último de la lista, se achicó el filtro, un enlace viejo): en vez de mostrar
 * "no hay nada" cuando sí hay, manda a la última página que existe.
 */
final class Paginas
{
    public static function enRango(LengthAwarePaginator $paginador): LengthAwarePaginator
    {
        if ($paginador->total() > 0 && $paginador->currentPage() > $paginador->lastPage()) {
            abort(redirect($paginador->url($paginador->lastPage())));
        }

        return $paginador;
    }
}
