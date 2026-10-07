<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guarda el orden y el anidamiento de los menús tal como quedaron después de
 * arrastrar en la lista.
 *
 * Arrastrar permite meter un menú dentro de otro, así que acá se controla lo que
 * la interfaz ya intenta evitar: que un menú quede dentro de sí mismo (o de uno de
 * sus submenús) y que el árbol pase de los niveles que la tienda muestra bien.
 */
class ArbolDeMenus
{
    /** Nivel más profundo permitido (0 = primer nivel). */
    const NIVEL_MAXIMO = Menu::NIVEL_MAXIMO_PADRE + 1;

    /**
     * @param array $items [['id' => 3, 'parent_id' => 1|null, 'orden' => 0], ...]
     * @throws ValidationException
     */
    public function ordenar(array $items): void
    {
        // El árbol como quedaría: lo que ya está guardado, pisado por lo enviado.
        $padres = Menu::pluck('parent_id', 'id')->all();
        foreach ($items as $item) {
            $padres[(int) $item['id']] = $item['parent_id'] !== null ? (int) $item['parent_id'] : null;
        }

        foreach (array_keys($padres) as $id) {
            $nivel = $this->nivel($id, $padres);

            if ($nivel > self::NIVEL_MAXIMO) {
                throw ValidationException::withMessages([
                    'items' => 'El menú admite hasta ' . (self::NIVEL_MAXIMO + 1) . ' niveles: más adentro, los clientes no lo encuentran.',
                ]);
            }
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                Menu::whereKey((int) $item['id'])->update([
                    'parent_id' => $item['parent_id'] !== null ? (int) $item['parent_id'] : null,
                    'orden'     => (int) $item['orden'],
                ]);
            }
        });

        Menu::limpiarCache();
    }

    /**
     * Profundidad de un menú en el árbol dado.
     *
     * @throws ValidationException si el camino hacia arriba vuelve sobre sí mismo
     */
    private function nivel(int $id, array $padres): int
    {
        $nivel   = 0;
        $vistos  = [$id => true];
        $actual  = $padres[$id] ?? null;

        while ($actual !== null) {
            if (isset($vistos[$actual])) {
                throw ValidationException::withMessages([
                    'items' => 'Un menú no puede quedar dentro de sí mismo ni de uno de sus submenús.',
                ]);
            }
            $vistos[$actual] = true;
            $nivel++;
            $actual = $padres[$actual] ?? null;
        }

        return $nivel;
    }
}
