<?php

namespace App\Exports;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Services\CaracteristicasPorCategoria;
use App\Support\Caracteristica;
use Illuminate\Support\Facades\DB;
use OpenSpout\Writer\Common\Creator\Style\StyleBuilder;
use OpenSpout\Writer\Common\Creator\WriterEntityFactory;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Las hojas "para copiar y pegar" que acompañan a la plantilla y a la exportación:
 *
 *  - "Categorías": las que ya existen, escritas como van en la columna Categorías
 *    ("Celulares > Iphone"), y qué datos pide cada una (los obligatorios con " *").
 *  - "Valores": los valores ya usados de cada característica (las marcas, los talles).
 *
 * Copiar de acá en vez de escribir evita el error de tipeo que crea una categoría o
 * un valor duplicado ("Celulares > Ifone", "Arcorr"). Al importar sólo se lee la
 * primera hoja: estas no molestan si la planilla se sube con ellas.
 */
class HojasDeReferencia
{
    /** Valores que se listan por característica: los más usados (un "Modelo" puede tener miles). */
    const MAX_VALORES = 300;

    /** Anchos: Categoría, Qué datos pide. */
    const ANCHOS_CATEGORIAS = [45, 90];

    /**
     * Agrega las dos hojas al final del archivo.
     *
     * @param int[]|null $etiquetaIds las características de la hoja "Valores"; null = todas
     */
    public static function agregar(Writer $escritor, ?array $etiquetaIds = null): void
    {
        $negrita = (new StyleBuilder())->setFontBold()->build();

        $categorias = static::categorias();
        if ($categorias) {
            $escritor->addNewSheetAndMakeItCurrent()->setName('Categorías');
            AnchosDeColumna::aplicar($escritor, self::ANCHOS_CATEGORIAS);
            AnchosDeColumna::fijarTitulos($escritor);
            $escritor->addRow(WriterEntityFactory::createRowFromArray(['Categoría (copiala así en la columna Categorías)', 'Qué datos pide (con * los obligatorios)'], $negrita));
            foreach ($categorias as $fila) {
                $escritor->addRow(WriterEntityFactory::createRowFromArray($fila));
            }
        }

        $valores = static::valores($etiquetaIds);
        if ($valores) {
            $escritor->addNewSheetAndMakeItCurrent()->setName('Valores');
            AnchosDeColumna::aplicar($escritor, array_fill(0, count($valores), 24));
            AnchosDeColumna::fijarTitulos($escritor);
            $escritor->addRow(WriterEntityFactory::createRowFromArray(array_keys($valores), $negrita));
            $alto = max(array_map('count', $valores));
            for ($i = 0; $i < $alto; $i++) {
                $escritor->addRow(WriterEntityFactory::createRowFromArray(array_map(function (array $columna) use ($i) {
                    return $columna[$i] ?? '';
                }, array_values($valores))));
            }
        }
    }

    /** @return array<int, array{0: string, 1: string}> [camino, "Marca *, Color"] en el orden del árbol */
    public static function categorias(): array
    {
        $caracteristicas = new CaracteristicasPorCategoria();

        return Categoria::enOrdenDeArbol()->map(function (Categoria $categoria) use ($caracteristicas) {
            return [
                $categoria->camino(),
                implode(', ', array_map(function (Caracteristica $c) {
                    return $c->nombre() . ($c->obligatoria() ? ' *' : '');
                }, $caracteristicas->deCategoria($categoria->id))),
            ];
        })->values()->all();
    }

    /**
     * Una columna por característica, con sus valores más usados primero.
     *
     * @return array<string, string[]> nombre de la característica => valores (sólo las que tienen alguno)
     */
    public static function valores(?array $etiquetaIds = null): array
    {
        $etiquetas = Etiqueta::when($etiquetaIds !== null, function ($q) use ($etiquetaIds) { $q->whereIn('id', $etiquetaIds); })
            ->orderByDesc('global')->orderBy('nombre')->get(['id', 'nombre']);

        $columnas = [];
        foreach ($etiquetas as $etiqueta) {
            $valores = DB::table('etiqueta_valores as v')
                ->leftJoin('producto_etiqueta as pe', 'pe.etiqueta_valor_id', '=', 'v.id')
                ->where('v.etiqueta_id', $etiqueta->id)
                ->groupBy('v.id', 'v.valor')
                ->orderByRaw('count(pe.id) desc')->orderBy('v.valor')
                ->limit(self::MAX_VALORES)
                ->pluck('v.valor')->all();
            if ($valores) {
                $columnas[$etiqueta->nombre] = $valores;
            }
        }

        return $columnas;
    }
}
