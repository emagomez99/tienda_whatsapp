<?php

namespace App\Support;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;

/**
 * Los filtros del listado de productos del panel.
 *
 * Viven acá y no en el controlador porque los usan dos cosas que tienen que dar
 * exactamente lo mismo: el listado, y "aplicar a todos los del filtro" de la
 * asignación masiva. Si cada una armara su consulta, una podría asignar
 * categorías a productos que la otra no mostró.
 */
class FiltroDeProductos
{
    /** Valor del filtro de categoría para "los que no tienen ninguna". */
    const SIN_CATEGORIA = 'sin';

    /** Los parámetros que entiende, tal como viajan en la dirección. */
    const CAMPOS = ['buscar', 'proveedor', 'disponible', 'stock', 'categoria'];

    /** @var array */
    private $valores;

    public function __construct(array $valores)
    {
        $this->valores = array_filter(
            array_intersect_key($valores, array_flip(self::CAMPOS)),
            function ($valor) { return $valor !== null && $valor !== ''; }
        );
    }

    public function valores(): array
    {
        return $this->valores;
    }

    public function hayActivos(): bool
    {
        return !empty($this->valores);
    }

    public function consulta(): Builder
    {
        return $this->aplicar(Producto::query());
    }

    public function aplicar(Builder $query): Builder
    {
        $v = $this->valores;

        if (isset($v['buscar'])) {
            $buscar = $v['buscar'];
            $query->where(function ($q) use ($buscar) {
                $q->where('descripcion', 'ilike', "%{$buscar}%")
                  ->orWhere('id_proveedor', 'ilike', "%{$buscar}%");
            });
        }

        if (isset($v['proveedor'])) {
            $query->where('proveedor_id', (int) $v['proveedor']);
        }

        if (isset($v['disponible'])) {
            $query->where('disponible', (bool) $v['disponible']);
        }

        if (($v['stock'] ?? null) === 'con') {
            $query->where('stock', '>', 0);
        } elseif (($v['stock'] ?? null) === 'sin') {
            $query->where(function ($q) {
                $q->where('stock', 0)->orWhereNull('stock');
            });
        }

        if (isset($v['categoria'])) {
            if ($v['categoria'] === self::SIN_CATEGORIA) {
                $query->sinCategoria();
            } else {
                $categoria = Categoria::find((int) $v['categoria']);
                // Una categoría que ya no existe no puede devolver "todos".
                $categoria ? $query->enCategoria($categoria) : $query->whereRaw('false');
            }
        }

        return $query;
    }
}
