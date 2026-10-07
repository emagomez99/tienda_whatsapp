<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Categorías: qué es cada producto (Ropa › Remeras). Árbol de hasta 3 niveles, y un
 * producto puede estar en varias. Ver docs/plan-categorias-y-caracteristicas.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categorias')->restrictOnDelete();
            $table->string('nombre');
            $table->string('normalizado')->comment('Nombre en minúsculas y con espacios simples: para no repetir hermanas y para buscar por camino');
            $table->string('slug')->unique();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('visible')->default(true);
            $table->string('meta_title', 60)->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'orden']);
        });

        // Dos hermanas no pueden llamarse igual ("Remeras" y "remeras" dentro de Ropa).
        // En Postgres un unique con parent_id NULL no impide repetir en la raíz, de ahí
        // el coalesce.
        DB::statement('CREATE UNIQUE INDEX categorias_hermanas_unique ON categorias (COALESCE(parent_id, 0), normalizado)');

        Schema::create('categoria_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['categoria_id', 'producto_id']);
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_producto');
        Schema::dropIfExists('categorias');
    }
};
