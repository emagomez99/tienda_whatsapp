<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una característica puede admitir varios valores en un mismo producto (Talle: S, M
 * y L; los modelos de auto en los que entra un repuesto). Se marca en la
 * característica: Marca sigue siendo una sola.
 *
 * producto_etiqueta deja de ser "una fila por producto y característica" y pasa a
 * "una fila por valor": lo que no se repite es el mismo valor dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etiquetas', function (Blueprint $table) {
            $table->boolean('varios_valores')->default(false);
        });

        Schema::table('producto_etiqueta', function (Blueprint $table) {
            $table->dropUnique(['producto_id', 'etiqueta_id']);
            $table->unique(['producto_id', 'etiqueta_id', 'etiqueta_valor_id']);
        });
    }

    public function down(): void
    {
        // Vuelve a uno por característica: se queda el primero que se cargó.
        DB::statement('DELETE FROM producto_etiqueta a USING producto_etiqueta b
                       WHERE a.producto_id = b.producto_id AND a.etiqueta_id = b.etiqueta_id AND a.id > b.id');

        Schema::table('producto_etiqueta', function (Blueprint $table) {
            $table->dropUnique(['producto_id', 'etiqueta_id', 'etiqueta_valor_id']);
            $table->unique(['producto_id', 'etiqueta_id']);
        });

        Schema::table('etiquetas', function (Blueprint $table) {
            $table->dropColumn('varios_valores');
        });
    }
};
