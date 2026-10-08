<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué características (hoy, la tabla etiquetas) pide cada categoría, y si son
 * obligatorias. Reemplaza a proveedor_etiqueta: lo que lleva un producto lo decide
 * qué es, no quién lo provee. Ver docs/plan-categorias-y-caracteristicas.md.
 *
 * etiquetas.global: la característica se pide en todos los productos, tengan la
 * categoría que tengan (ej. Marca).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('categoria_caracteristica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->foreignId('etiqueta_id')->constrained('etiquetas')->cascadeOnDelete();
            $table->boolean('obligatoria')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['categoria_id', 'etiqueta_id']);
            $table->index('etiqueta_id');
        });

        Schema::table('etiquetas', function (Blueprint $table) {
            $table->boolean('global')->default(false);
        });
    }

    public function down()
    {
        Schema::table('etiquetas', function (Blueprint $table) {
            $table->dropColumn('global');
        });
        Schema::dropIfExists('categoria_caracteristica');
    }
};
