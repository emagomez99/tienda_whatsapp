<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índice para filtrar etiquetas por valor sin distinguir mayúsculas.
 *
 * Producto::scopeConEtiqueta compara lower(valor) = lower(?), y el índice de
 * (etiqueta_id, valor) no sirve para una expresión. En oleomc cada etiqueta del
 * importador tiene unos 30.900 productos: sin este índice cada filtro recorre todos
 * los de la etiqueta en vez de ir directo al valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS producto_etiqueta_etiqueta_id_lower_valor_index ON producto_etiqueta (etiqueta_id, lower(valor))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS producto_etiqueta_etiqueta_id_lower_valor_index');
    }
};
