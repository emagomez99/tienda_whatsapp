<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Más decimales en la cotización, para que cambiar la moneda de la tienda no pierda
 * precisión.
 *
 * Con 6 decimales, una moneda débil expresada contra una fuerte se queda sin dígitos
 * significativos: el peso contra el dólar es 1/1500 = 0,000667, apenas 3 cifras. Al
 * volver a convertir da 1499,25 en vez de 1500, y un producto de $65.625 se desvía
 * $33 por el sólo hecho de haber cambiado la unidad de medida.
 *
 * Con 10 decimales quedan 0,0006666667 y la vuelta da 1499,99992, que redondeado a
 * centavos devuelve exactamente el precio original.
 *
 * Agrandar la precisión de un numeric en Postgres es un cambio de metadatos: no
 * reescribe la tabla ni puede perder datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE monedas ALTER COLUMN cotizacion TYPE numeric(20,10)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE monedas ALTER COLUMN cotizacion TYPE numeric(20,6)');
    }
};
