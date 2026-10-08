<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * En qué orden se ven los productos de una categoría (ver App\Support\OrdenDeProductos):
 * por nombre, más nuevos, por precio, o "a mano": los destacados primero, en el
 * orden que se eligió, y después el resto por nombre.
 *
 * Los destacados van en su propia tabla y no en categoria_producto: en «Bebidas» se
 * puede destacar una Coca-Cola que está cargada en «Bebidas › Gaseosas».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->string('orden_productos', 20)->default('nombre');
        });

        Schema::create('categoria_destacados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->unsignedInteger('posicion');

            $table->unique(['categoria_id', 'producto_id']);
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_destacados');
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropColumn('orden_productos');
        });
    }
};
