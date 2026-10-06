<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Fila de producto_etiqueta: qué valor tiene un producto en una etiqueta.
 *
 * Al guardarse resuelve el texto contra la lista de valores de la etiqueta
 * (EtiquetaValor::resolver). Se hace acá y no en cada lugar que carga etiquetas para
 * que valga igual en el formulario de producto, en el importador de Hercules y en
 * cualquier attach/sync futuro.
 *
 * La columna de texto `valor` se mantiene igual al valor resuelto: todo lo que hoy
 * lee $etiqueta->pivot->valor (grilla, carrito, meta title, filtros) sigue andando
 * sin cambios.
 */
class ProductoEtiqueta extends Pivot
{
    protected $table = 'producto_etiqueta';

    protected static function booted()
    {
        static::saving(function (ProductoEtiqueta $fila) {
            $valor = EtiquetaValor::resolver($fila->etiqueta_id, $fila->valor);

            $fila->etiqueta_valor_id = $valor->id;
            $fila->valor = $valor->valor;
        });
    }
}
