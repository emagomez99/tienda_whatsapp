<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pasa las reglas por proveedor (proveedor_etiqueta: obligatoria / opcional / no
 * aplica) a reglas por categoría (categoria_caracteristica). Plan, sección 6.
 *
 *  - Una característica que todos los proveedores con productos exigen pasa a ser
 *    global obligatoria (ej. Marca en perfumes).
 *  - Cada categoría pide las que usan sus productos (asignados a ella), y es
 *    obligatoria si lo era para todos los proveedores de esos productos.
 *  - Si todas las subcategorías de una madre piden lo mismo, se sube a la madre.
 *  - Categoria y Subcategoria no se pasan: ahora son las categorías.
 *
 * Sólo agrega: lo que una categoría ya pide no se toca. Se puede correr de nuevo.
 */
class CaracteristicasDesdeProveedores
{
    /** @var array lo que pasó, para mostrarlo */
    private $informe;

    public function ejecutar(): array
    {
        $this->informe = ['globales' => [], 'porCategoria' => [], 'sinDestino' => []];

        $reglas = $this->reglasPorProveedor();
        $excluidas = $this->etiquetasQueAhoraSonCategorias();

        $globales = $this->globales($reglas, $excluidas);
        foreach ($globales as $etiquetaId) {
            $etiqueta = Etiqueta::find($etiquetaId);
            $etiqueta->update(['global' => true, 'obligatoria_global' => true]);
            $this->informe['globales'][] = $etiqueta->nombre . ' (obligatoria)';
        }
        // Una global no se repite en las categorías.
        $excluidas = array_merge($excluidas, $globales);

        $porCategoria = $this->porCategoria($reglas, $excluidas);
        $porCategoria = $this->subirAMadres($porCategoria);
        $this->guardar($porCategoria);

        $this->avisarLasQueQuedanSinDestino($reglas, $excluidas, $porCategoria);

        return $this->informe;
    }

    /** proveedor_id => [etiqueta_id => true (obligatoria) | false (opcional)]; "no aplica" no cuenta. */
    private function reglasPorProveedor(): array
    {
        $reglas = [];
        foreach (DB::table('proveedor_etiqueta')->whereNotNull('obligatoria')->get() as $fila) {
            $reglas[$fila->proveedor_id][$fila->etiqueta_id] = (bool) $fila->obligatoria;
        }
        return $reglas;
    }

    private function etiquetasQueAhoraSonCategorias(): array
    {
        return Etiqueta::all()->filter(function (Etiqueta $e) {
            return in_array(Str::ascii(EtiquetaValor::normalizar($e->nombre)), [
                CategoriasDesdeEtiquetas::ETIQUETA_CATEGORIA, CategoriasDesdeEtiquetas::ETIQUETA_SUBCATEGORIA,
            ], true);
        })->pluck('id')->all();
    }

    /** Las que exigen todos los proveedores que tienen productos (con al menos uno). */
    private function globales(array $reglas, array $excluidas): array
    {
        $conProductos = Proveedor::has('productos')->pluck('id')->all();
        if (empty($conProductos)) {
            return [];
        }

        $comunes = null;
        foreach ($conProductos as $proveedorId) {
            $exigidas = array_keys(array_filter($reglas[$proveedorId] ?? []));
            $comunes = $comunes === null ? $exigidas : array_values(array_intersect($comunes, $exigidas));
        }

        return array_values(array_diff($comunes ?: [], $excluidas));
    }

    /**
     * categoria_id => [etiqueta_id => obligatoria]: las que usan los productos
     * asignados directamente a cada categoría, según las reglas de sus proveedores.
     */
    private function porCategoria(array $reglas, array $excluidas): array
    {
        $filas = DB::table('categoria_producto as cp')
            ->join('productos as p', 'p.id', '=', 'cp.producto_id')
            ->leftJoin('producto_etiqueta as pe', 'pe.producto_id', '=', 'p.id')
            ->select('cp.categoria_id', 'p.proveedor_id', 'pe.etiqueta_id')
            ->distinct()
            ->get();

        $proveedores = [];   // categoria => [proveedor_id]
        $usadas = [];        // categoria => [etiqueta_id]
        foreach ($filas as $fila) {
            $proveedores[$fila->categoria_id][$fila->proveedor_id] = true;
            if ($fila->etiqueta_id && !in_array((int) $fila->etiqueta_id, $excluidas, true)) {
                $usadas[$fila->categoria_id][$fila->etiqueta_id] = true;
            }
        }

        $resultado = [];
        foreach ($usadas as $categoriaId => $etiquetas) {
            foreach (array_keys($etiquetas) as $etiquetaId) {
                $aplica = false;
                $obligatoria = true;
                foreach (array_keys($proveedores[$categoriaId]) as $proveedorId) {
                    if (array_key_exists($etiquetaId, $reglas[$proveedorId] ?? [])) {
                        $aplica = true;
                        $obligatoria = $obligatoria && $reglas[$proveedorId][$etiquetaId];
                    } else {
                        $obligatoria = false;   // un proveedor no la pedía: no puede ser obligatoria
                    }
                }
                // Usada pero ningún proveedor la configuró: igual se pide, opcional.
                $resultado[$categoriaId][$etiquetaId] = $aplica && $obligatoria;
            }
        }

        return $resultado;
    }

    /** Si todas las hijas de una madre piden lo mismo (y con la misma obligatoriedad), lo pide la madre. */
    private function subirAMadres(array $porCategoria): array
    {
        $categorias = Categoria::enOrdenDeArbol();

        // De abajo hacia arriba: primero las madres más profundas.
        $madres = $categorias->filter(function (Categoria $c) use ($categorias) {
            return $categorias->contains('parent_id', $c->id);
        })->sortByDesc(function (Categoria $c) { return $c->nivel(); });

        foreach ($madres as $madre) {
            $hijas = $categorias->where('parent_id', $madre->id)->pluck('id')->all();
            $comunes = null;
            foreach ($hijas as $hija) {
                $suyas = $porCategoria[$hija] ?? [];
                if ($comunes === null) {
                    $comunes = $suyas;
                } else {
                    $comunes = array_filter($comunes, function ($obligatoria, $etiquetaId) use ($suyas) {
                        return array_key_exists($etiquetaId, $suyas) && $suyas[$etiquetaId] === $obligatoria;
                    }, ARRAY_FILTER_USE_BOTH);
                }
            }

            foreach ($comunes ?: [] as $etiquetaId => $obligatoria) {
                $porCategoria[$madre->id][$etiquetaId] = $obligatoria;
                foreach ($hijas as $hija) {
                    unset($porCategoria[$hija][$etiquetaId]);
                }
            }
        }

        return array_filter($porCategoria);
    }

    private function guardar(array $porCategoria): void
    {
        $nombres = Etiqueta::pluck('nombre', 'id');

        foreach (Categoria::enOrdenDeArbol() as $categoria) {
            $nuevas = $porCategoria[$categoria->id] ?? [];
            if (!$nuevas) {
                continue;
            }

            // Primero las obligatorias; dentro de cada grupo, por nombre.
            uksort($nuevas, function ($a, $b) use ($nuevas, $nombres) {
                return [!$nuevas[$a], $nombres[$a]] <=> [!$nuevas[$b], $nombres[$b]];
            });

            $ya = $categoria->caracteristicas()->pluck('etiquetas.id')->all();
            $orden = count($ya);
            $agregadas = [];
            foreach ($nuevas as $etiquetaId => $obligatoria) {
                if (in_array($etiquetaId, $ya)) {
                    continue;
                }
                $categoria->caracteristicas()->attach($etiquetaId, ['obligatoria' => $obligatoria, 'orden' => $orden++]);
                $agregadas[] = $nombres[$etiquetaId] . ($obligatoria ? ' (obligatoria)' : '');
            }
            if ($agregadas) {
                $this->informe['porCategoria'][$categoria->camino()] = implode(', ', $agregadas);
            }
        }
    }

    /** Reglas obligatorias que no fueron a ningún lado (ej. arcor, sin categorías). */
    private function avisarLasQueQuedanSinDestino(array $reglas, array $excluidas, array $porCategoria): void
    {
        $destinadas = [];
        foreach ($porCategoria as $etiquetas) {
            $destinadas += $etiquetas;
        }

        $nombresProveedor = Proveedor::pluck('nombre', 'id');
        $nombresEtiqueta = Etiqueta::pluck('nombre', 'id');
        foreach ($reglas as $proveedorId => $etiquetas) {
            foreach ($etiquetas as $etiquetaId => $obligatoria) {
                if ($obligatoria && !in_array($etiquetaId, $excluidas) && !array_key_exists($etiquetaId, $destinadas)) {
                    $this->informe['sinDestino'][] = ($nombresProveedor[$proveedorId] ?? '?') . ': ' . ($nombresEtiqueta[$etiquetaId] ?? '?');
                }
            }
        }
    }
}
