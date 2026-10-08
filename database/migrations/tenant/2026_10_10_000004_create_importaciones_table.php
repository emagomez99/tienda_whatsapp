<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada planilla de Excel que se sube para importar productos: quién, cuándo, qué
 * archivo y cómo terminó. Se aplica por partes (ver ImportacionDeProductos), así que
 * guarda el avance; las filas ya leídas viven en un archivo aparte (`filas`), no acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre_archivo');
            $table->string('ruta');                       // planilla subida (disco local)
            $table->string('estado', 20)->default('revisando');   // revisando, aplicando, terminada, cancelada
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('procesadas')->default(0);
            $table->unsignedInteger('creados')->default(0);
            $table->unsignedInteger('actualizados')->default(0);
            $table->unsignedInteger('con_error')->default(0);
            $table->json('resumen')->nullable();          // lo de la vista previa y los errores al aplicar
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones');
    }
};
