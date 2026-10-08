<?php

namespace App\Support;

use App\Models\Categoria;
use App\Models\Etiqueta;
use Illuminate\Support\Facades\DB;

/**
 * En qué orden se muestran los datos (características) de un producto en la
 * tienda: tarjetas, ficha, carrito.
 *
 * Antes salían en el orden en que los devolvía la base, distinto según la página,
 * y Marca solía ir última. Ahora es uno solo y lógico:
 *   1. las globales (Marca), por nombre;
 *   2. las que piden las categorías, recorriendo el árbol en su orden y, dentro
 *      de cada categoría, en el orden configurado ("Datos que se piden");
 *   3. el resto, por nombre (lo resuelve la consulta: no están en la lista).
 *
 * Decisión: un orden único para toda la tienda y no uno por producto. Ordenar por
 * las categorías de cada producto obligaba a cargarlas en cada listado y a
 * reordenar en PHP producto por producto; con un ranking único, la consulta que
 * ya trae las etiquetas las ordena (array_position, ver Producto::etiquetasPublicas)
 * sin consultas de más. Como un producto suele estar en una sola rama, el orden
 * que ve es el de sus categorías. Si una característica la piden dos categorías
 * en distinto lugar, vale el de la primera en el árbol.
 *
 * El ranking cuesta tres consultas chicas y se arma una vez por pedido (se guarda
 * en el Request, con la tienda en la clave: los tests cambian de tienda en el
 * mismo proceso).
 */
class OrdenDeCaracteristicas
{
    /** @return int[] ids de etiquetas, en el orden en que se muestran */
    public static function ids(): array
    {
        $request = app()->bound('request') ? app('request') : null;
        $clave = 'orden_caracteristicas.' . (tenancy()->initialized ? tenant('id') : 'central');

        if ($request && $request->attributes->has($clave)) {
            return $request->attributes->get($clave);
        }

        $ids = self::armar();
        if ($request) {
            $request->attributes->set($clave, $ids);
        }

        return $ids;
    }

    /**
     * Para un ORDER BY: la posición de cada etiqueta en ids(); las que no están
     * quedan al final (y ahí las ordena por nombre quien llama). null si no hay
     * ninguna para ordenar.
     */
    public static function ordenSql(string $columnaId = 'etiquetas.id'): ?string
    {
        $ids = self::ids();
        if (!$ids) {
            return null;
        }

        // Enteros armados acá, no texto del usuario: van en el SQL sin bindings.
        return 'array_position(ARRAY[' . implode(',', array_map('intval', $ids)) . ']::bigint[], '
            . $columnaId . '::bigint) nulls last';
    }

    private static function armar(): array
    {
        $ids = Etiqueta::where('global', true)->orderBy('nombre')->pluck('id')->map(function ($id) { return (int) $id; })->all();

        $reglas = [];
        foreach (DB::table('categoria_caracteristica')->orderBy('orden')->orderBy('id')->get(['categoria_id', 'etiqueta_id']) as $fila) {
            $reglas[$fila->categoria_id][] = (int) $fila->etiqueta_id;
        }

        foreach (Categoria::enOrdenDeArbol() as $categoria) {
            foreach ($reglas[$categoria->id] ?? [] as $etiquetaId) {
                $ids[] = $etiquetaId;
            }
        }

        return array_values(array_unique($ids));
    }
}
