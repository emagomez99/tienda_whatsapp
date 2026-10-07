<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Agregar o quitar categorías a muchos productos de una vez.
 *
 * Trabaja con una consulta de productos y no con una lista de ids: así "todos los
 * del filtro" en un catálogo como el de oleomc (30.000 productos) es una sola
 * sentencia en la base, sin traer nada a PHP.
 *
 * Quitar saca sólo la categoría marcada: si el producto está en "Ropa › Remeras",
 * quitar "Ropa" no lo saca de "Remeras" (y por eso sigue apareciendo en "Ropa").
 */
class AsignacionDeCategorias
{
    /**
     * @param Builder $productos consulta de productos (se usa sólo su WHERE)
     * @param int[]   $categorias
     * @return int cuántas asociaciones nuevas se crearon
     */
    public function asignar(Builder $productos, array $categorias): int
    {
        $ahora = now();
        $creadas = 0;

        DB::transaction(function () use ($productos, $categorias, $ahora, &$creadas) {
            foreach (array_unique(array_map('intval', $categorias)) as $categoriaId) {
                $faltan = (clone $productos)
                    ->select(DB::raw($categoriaId . ' as categoria_id'), 'productos.id as producto_id')
                    ->selectRaw('?::timestamp as created_at, ?::timestamp as updated_at', [$ahora, $ahora])
                    ->whereNotIn('productos.id', function ($sub) use ($categoriaId) {
                        $sub->select('producto_id')->from('categoria_producto')->where('categoria_id', $categoriaId);
                    })
                    ->toBase();
                $faltan->orders = null;

                $creadas += DB::table('categoria_producto')
                    ->insertUsing(['categoria_id', 'producto_id', 'created_at', 'updated_at'], $faltan);
            }
        });

        return $creadas;
    }

    /**
     * @param Builder $productos
     * @param int[]   $categorias
     * @return int cuántas asociaciones se borraron
     */
    public function quitar(Builder $productos, array $categorias): int
    {
        $ids = (clone $productos)->select('productos.id')->toBase();
        $ids->orders = null;

        return DB::table('categoria_producto')
            ->whereIn('categoria_id', array_map('intval', $categorias))
            ->whereIn('producto_id', $ids)
            ->delete();
    }
}
