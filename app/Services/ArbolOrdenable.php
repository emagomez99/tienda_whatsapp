<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guarda el orden y el anidamiento de un árbol (menús, categorías) tal como quedó
 * después de arrastrar en la lista (ver admin/partials/arbol-ordenable).
 *
 * Arrastrar permite meter un elemento dentro de otro, así que acá se controla lo
 * que la interfaz ya intenta evitar: que quede dentro de sí mismo (o de uno de sus
 * hijos) y que el árbol pase del nivel permitido.
 */
abstract class ArbolOrdenable
{
    /** @return string clase del modelo, con columnas parent_id y orden */
    abstract protected function modelo(): string;

    /** Nivel más profundo permitido (0 = primer nivel). */
    abstract public function nivelMaximo(): int;

    /** Cómo se nombra un elemento en los mensajes ("Un menú", "Una categoría"). */
    abstract protected function unElemento(): string;

    /** Después de guardar (ej. limpiar caché). */
    protected function despuesDeOrdenar(): void
    {
    }

    /**
     * @param array $items [['id' => 3, 'parent_id' => 1|null, 'orden' => 0], ...]
     * @throws ValidationException
     */
    public function ordenar(array $items): void
    {
        /** @var Model $modelo */
        $modelo = $this->modelo();

        // El árbol como quedaría: lo que ya está guardado, pisado por lo enviado.
        $padres = $modelo::pluck('parent_id', 'id')->all();
        foreach ($items as $item) {
            $padres[(int) $item['id']] = $item['parent_id'] !== null ? (int) $item['parent_id'] : null;
        }

        foreach (array_keys($padres) as $id) {
            if ($this->nivel($id, $padres) > $this->nivelMaximo()) {
                throw ValidationException::withMessages([
                    'items' => 'Se admiten hasta ' . ($this->nivelMaximo() + 1) . ' niveles: más adentro, los clientes no lo encuentran.',
                ]);
            }
        }

        DB::transaction(function () use ($items, $modelo) {
            foreach ($items as $item) {
                $modelo::whereKey((int) $item['id'])->update([
                    'parent_id' => $item['parent_id'] !== null ? (int) $item['parent_id'] : null,
                    'orden'     => (int) $item['orden'],
                ]);
            }
        });

        $this->despuesDeOrdenar();
    }

    /**
     * Sube o baja un elemento un lugar entre sus hermanos (la alternativa a
     * arrastrar, desde el menú ⋯). Se renumera a todos los hermanos (0, 1, 2…)
     * porque puede haber posiciones repetidas, y con posiciones iguales "subir" no
     * tendría con quién intercambiar.
     */
    public function moverEntreHermanos(Model $elemento, string $direccion): void
    {
        $modelo = $this->modelo();

        $hermanos = $modelo::where('parent_id', $elemento->parent_id)->orderBy('orden')->orderBy('id')->get()->values();
        $actual   = $hermanos->search(function ($h) use ($elemento) { return (int) $h->id === (int) $elemento->id; });
        $destino  = $direccion === 'arriba' ? $actual - 1 : $actual + 1;

        if ($actual === false || $destino < 0 || $destino >= $hermanos->count()) {
            return;
        }

        $orden = $hermanos->all();
        [$orden[$actual], $orden[$destino]] = [$orden[$destino], $orden[$actual]];

        foreach ($orden as $posicion => $hermano) {
            if ((int) $hermano->orden !== $posicion) {
                $hermano->update(['orden' => $posicion]);
            }
        }

        $this->despuesDeOrdenar();
    }

    /**
     * Profundidad de un elemento en el árbol dado.
     *
     * @throws ValidationException si el camino hacia arriba vuelve sobre sí mismo
     */
    private function nivel(int $id, array $padres): int
    {
        $nivel  = 0;
        $vistos = [$id => true];
        $actual = $padres[$id] ?? null;

        while ($actual !== null) {
            if (isset($vistos[$actual])) {
                throw ValidationException::withMessages([
                    'items' => $this->unElemento() . ' no puede quedar dentro de sí mismo ni de uno de los suyos.',
                ]);
            }
            $vistos[$actual] = true;
            $nivel++;
            $actual = $padres[$actual] ?? null;
        }

        return $nivel;
    }
}
