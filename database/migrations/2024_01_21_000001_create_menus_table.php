<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Texto visible del menú
            $table->foreignId('parent_id')->nullable()->constrained('menus')->onDelete('cascade');
            $table->enum('tipo_enlace', ['ninguno', 'proveedor', 'categoria', 'especificacion'])->default('ninguno');
            $table->unsignedBigInteger('enlace_id')->nullable(); // ID del elemento relacionado
            $table->string('enlace_valor')->nullable(); // Para especificaciones: valor específico a filtrar
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'orden']);
            $table->index(['tipo_enlace', 'enlace_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
