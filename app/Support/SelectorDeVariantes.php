<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\Variante;

/**
 * Lo que necesita la ficha de la tienda para que el cliente elija una variante:
 * las opciones de cada característica (Talle: S, M, L · Color: Rojo, Azul) y cada
 * variante activa con su combinación, su stock y su precio.
 *
 * Las características y sus opciones van en el orden en que se cargaron en el
 * producto (el mismo de la tabla de variantes del panel).
 */
final class SelectorDeVariantes
{
    /** @var array<int, array{id: int, nombre: string, opciones: array}> */
    public $ejes = [];

    /** @var array<int, array> */
    public $variantes = [];

    public static function para(Producto $producto): ?self
    {
        $variantes = $producto->variantes()->where('activa', true)->with('valores.etiqueta')->get();
        if ($variantes->isEmpty()) {
            return null;
        }

        $selector = new self();
        $simbolo = $producto->moneda ? $producto->moneda->simbolo : '$';
        $ejes = [];

        foreach ($variantes as $variante) {
            /** @var Variante $variante */
            $variante->setRelation('producto', $producto);
            $combinacion = [];
            foreach ($variante->valores as $valor) {
                $ejeId = (int) $valor->pivot->etiqueta_id;
                if (!isset($ejes[$ejeId])) {
                    $ejes[$ejeId] = ['id' => $ejeId, 'nombre' => $valor->etiqueta ? $valor->etiqueta->nombre : '', 'opciones' => []];
                }
                $ejes[$ejeId]['opciones'][$valor->id] = ['id' => (int) $valor->id, 'valor' => $valor->valor];
                $combinacion[$ejeId] = (int) $valor->id;
            }

            $selector->variantes[] = [
                'id'         => $variante->id,
                'nombre'     => $variante->nombre(),
                'valores'    => $combinacion,
                'stock'      => $variante->stockMaximo(),        // null = sin límite
                'cantidad'   => (int) $variante->stock,
                'hayStock'   => (int) $variante->stock > 0,
                'sePide'     => $variante->sePuedePedir(),
                'precio'     => $simbolo . number_format((float) $variante->precioFinal(), 2, ',', '.'),
            ];
        }

        foreach ($ejes as $eje) {
            $eje['opciones'] = array_values($eje['opciones']);
            $selector->ejes[] = $eje;
        }

        return $selector;
    }

    /** "el talle" / "el talle y el color": para "Elegí el talle". */
    public function queElegir(): string
    {
        $nombres = array_map(function ($eje) { return mb_strtolower($eje['nombre'], 'UTF-8'); }, $this->ejes);
        $ultimo = array_pop($nombres);

        return $nombres ? implode(', ', $nombres) . ' y ' . $ultimo : (string) $ultimo;
    }
}
