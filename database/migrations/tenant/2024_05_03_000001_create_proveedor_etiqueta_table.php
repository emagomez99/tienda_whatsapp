<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedor_etiqueta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->onDelete('cascade');
            $table->foreignId('etiqueta_id')->constrained('etiquetas')->onDelete('cascade');
            $table->boolean('obligatoria')->default(true);
            $table->timestamps();

            $table->unique(['proveedor_id', 'etiqueta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_etiqueta');
    }
};
