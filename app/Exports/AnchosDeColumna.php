<?php

namespace App\Exports;

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
    const FIJAS = [16, 40, 22, 32, 12, 9, 9, 14, 14, 40];

    /** La hoja "Cómo completarla" de la plantilla: Columna, Qué poner, Ejemplo. */
    const INSTRUCCIONES = [28, 90, 30];

    public static function antesDeAbrir(Writer $escritor): void
    {
        $escritor->setDefaultColumnWidth(18);   // imágenes y características
    }

    public static function aplicar(Writer $escritor, array $anchos = self::FIJAS): void
    {
        foreach ($anchos as $i => $ancho) {
            $escritor->setColumnWidth($ancho, $i + 1);
        }
    }
}
