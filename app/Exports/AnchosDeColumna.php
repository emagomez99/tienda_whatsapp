<?php

namespace App\Exports;

use OpenSpout\Writer\WriterMultiSheetsAbstract;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Anchos de las columnas fijas de la planilla de productos, para que se lea al
 * abrirla sin tener que agrandar nada.
 *
 * OpenSpout pide el ancho por defecto ANTES de abrir el archivo (antesDeAbrir) y el
 * de cada columna DESPUÉS, antes de escribir filas en la hoja (aplicar).
 */
class AnchosDeColumna
{
    /** En el orden de FormatoDePlanilla::COLUMNAS. */
    const FIJAS = [16, 40, 22, 32, 12, 9, 12, 12, 10, 9, 14, 14, 40];

    /** La hoja "Cómo completarla" de la plantilla: Columna, Qué poner, Ejemplo. */
    const INSTRUCCIONES = [28, 90, 30];

    public static function antesDeAbrir(Writer $escritor): void
    {
        $escritor->setDefaultColumnWidth(18);   // imágenes y características
    }

    /**
     * Los títulos quedan fijos arriba al bajar por la planilla. Después de crear la
     * hoja y antes de escribirle filas.
     */
    public static function fijarTitulos(Writer $escritor): void
    {
        $escritor->getCurrentSheet()->setSheetView((new SheetView())->setFreezeRow(2));
    }

    /**
     * Los anchos de la hoja actual (y sólo de ella).
     *
     * OpenSpout 3 guarda los anchos para todo el archivo y los va acumulando: sin
     * olvidar los de la hoja anterior, cada hoja nueva salía con los de "Productos"
     * y los propios encimados.
     */
    public static function aplicar(Writer $escritor, array $anchos = self::FIJAS): void
    {
        static::olvidarLosDeLaHojaAnterior($escritor);

        foreach ($anchos as $i => $ancho) {
            $escritor->setColumnWidth($ancho, $i + 1);
        }
    }

    /**
     * La librería no ofrece cómo vaciar los anchos: están en una propiedad privada
     * del que escribe las hojas, compartido por todas. Se llega hasta ahí a mano.
     */
    private static function olvidarLosDeLaHojaAnterior(Writer $escritor): void
    {
        $libro = \Closure::bind(function () { return $this->workbookManager; }, $escritor, WriterMultiSheetsAbstract::class)();
        $hojas = (function () { return $this->worksheetManager; })->call($libro);
        (function () { $this->columnWidths = []; })->call($hojas);
    }
}
