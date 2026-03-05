<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->enum('tipo_enlace', ['ninguno', 'proveedor', 'etiqueta', 'especificacion'])->default('ninguno');
            $table->unsignedBigInteger('enlace_id')->nullable();
            $table->string('enlace_valor')->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->json('filtros_etiquetas')->nullable();
            $table->boolean('filtros_requeridos')->default(false);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('menus')->onDelete('cascade');
            $table->index(['parent_id', 'orden']);
            $table->index(['tipo_enlace', 'enlace_id']);
            $table->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
