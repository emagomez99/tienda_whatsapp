<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ocultar un valor puntual de una etiqueta en la tienda, sin ocultar la etiqueta
 * entera: "Industrial" de Categoría puede no mostrarse mientras "Accesorios" sí.
 *
 * Un valor oculto no aparece en las tarjetas, la ficha, el meta title ni las opciones
 * de los filtros, pero los menús lo siguen pudiendo usar para elegir productos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etiqueta_valores', function (Blueprint $table) {
            $table->boolean('visible')->default(true)->after('normalizado');
        });
    }

    public function down(): void
    {
        Schema::table('etiqueta_valores', function (Blueprint $table) {
            $table->dropColumn('visible');
        });
    }
};
