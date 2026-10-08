<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El historial de stock guarda de qué variante fue cada movimiento por su nombre
 * ("Talle: P"), además del id: si la variante se borra, el historial lo sigue
 * diciendo (el id queda en null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->string('variante_nombre')->nullable();
        });

        DB::statement("
            UPDATE stock_movimientos m SET variante_nombre = sub.nombre
            FROM (
                SELECT vv.variante_id, string_agg(e.nombre || ': ' || ev.valor, ' · ' ORDER BY vv.id) AS nombre
                FROM producto_variante_valores vv
                JOIN etiquetas e ON e.id = vv.etiqueta_id
                JOIN etiqueta_valores ev ON ev.id = vv.etiqueta_valor_id
                GROUP BY vv.variante_id
            ) sub
            WHERE m.variante_id = sub.variante_id
        ");
    }

    public function down(): void
    {
        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->dropColumn('variante_nombre');
        });
    }
};
