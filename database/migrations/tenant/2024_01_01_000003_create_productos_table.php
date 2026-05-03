<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->onDelete('cascade');
            $table->string('id_proveedor')->nullable()->comment('Código del producto del proveedor');
            $table->string('descripcion');
            $table->text('detalle')->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->boolean('disponible')->default(true);
            $table->integer('stock')->default(0);
            $table->boolean('por_encargue')->default(false);
            $table->string('url_imagen')->nullable();
            $table->foreignId('moneda_id')->nullable()->constrained('monedas')->onDelete('set null');
            $table->timestamps();

            $table->index('disponible');
            $table->index(['disponible', 'stock']);
            $table->index(['disponible', 'por_encargue']);
            $table->index('descripcion');
            $table->index('id_proveedor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
