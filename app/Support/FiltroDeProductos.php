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

    /** "Poco stock": de 1 a esta cantidad. */
    const POCO_STOCK = 5;

    /** Los parámetros que entiende, tal como viajan en la dirección. */
    const CAMPOS = [
        'buscar', 'proveedor', 'disponible', 'stock', 'categoria',
        'precio_desde', 'precio_hasta', 'variantes', 'foto', 'encargue', 'caracteristica', 'valor',
    ];

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

    /**
     * Cuántos filtros del panel lateral están puestos (para el globito del embudo).
     * El buscador no cuenta: está a la vista. Característica y su valor son uno.
     */
    public function cantidadDelPanel(): int
    {
        return count(array_diff_key($this->valores, array_flip(['buscar', 'valor'])));
    }

    public function valor(string $campo)
    {
        return $this->valores[$campo] ?? null;
    }

    public function consulta(): Builder
    {
        return $this->aplicar(Producto::query());
    }

    public function aplicar(Builder $query): Builder
    {
        $v = $this->valores;

        if (isset($v['buscar'])) {
            // Como el buscador de la tienda: sin tildes y palabra por palabra.
            foreach (BusquedaDeTexto::palabras((string) $v['buscar']) as $palabra) {
                $patron = BusquedaDeTexto::patron($palabra);
                $query->where(function ($q) use ($patron) {
                    BusquedaDeTexto::contiene($q, 'descripcion', $patron);
                    BusquedaDeTexto::contiene($q, 'id_proveedor', $patron);
                });
            }
        }

        if (isset($v['proveedor'])) {
            $query->where('proveedor_id', (int) $v['proveedor']);
        }

        if (isset($v['disponible'])) {
            $query->where('disponible', (bool) $v['disponible']);
        }

        if (($v['stock'] ?? null) === 'con') {
            $query->where('stock', '>', 0);
        } elseif (($v['stock'] ?? null) === 'poco') {
            $query->whereBetween('stock', [1, self::POCO_STOCK]);
        } elseif (($v['stock'] ?? null) === 'sin') {
            $query->where(function ($q) {
                $q->where('stock', 0)->orWhereNull('stock');
            });
        }

        // Precio en la moneda de cada producto (como se ve en el listado).
        if (isset($v['precio_desde']) && is_numeric($v['precio_desde'])) {
            $query->where('precio', '>=', (float) $v['precio_desde']);
        }
        if (isset($v['precio_hasta']) && is_numeric($v['precio_hasta'])) {
            $query->where('precio', '<=', (float) $v['precio_hasta']);
        }

        if (($v['variantes'] ?? null) === 'con') {
            $query->has('variantes');
        } elseif (($v['variantes'] ?? null) === 'sin') {
            $query->doesntHave('variantes');
        }

        // Con foto: la principal o alguna de la galería.
        if (($v['foto'] ?? null) === 'con') {
            $query->where(function ($q) {
                $q->where(function ($q2) { $q2->whereNotNull('url_imagen')->where('url_imagen', '<>', ''); })
                  ->orWhereHas('imagenes');
            });
        } elseif (($v['foto'] ?? null) === 'sin') {
            $query->where(function ($q) { $q->whereNull('url_imagen')->orWhere('url_imagen', ''); })
                  ->doesntHave('imagenes');
        }

        if (isset($v['encargue'])) {
            $query->where('por_encargue', (bool) $v['encargue']);
        }

        // Una característica (los que la tienen cargada) o una característica y un valor.
        if (isset($v['caracteristica']) && ctype_digit((string) $v['caracteristica'])) {
            if (isset($v['valor'])) {
                $query->conEtiqueta((int) $v['caracteristica'], (string) $v['valor']);
            } else {
                $etiquetaId = (int) $v['caracteristica'];
                $query->whereHas('etiquetas', function ($q) use ($etiquetaId) { $q->where('etiquetas.id', $etiquetaId); });
            }
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
