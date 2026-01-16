<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductosTable extends Migration
{
    public function up()
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->onDelete('cascade');
            $table->string('id_proveedor')->nullable()->comment('Código del producto del proveedor');
            $table->string('descripcion');
            $table->decimal('precio', 10, 2)->default(0);
            $table->boolean('disponible')->default(true);
            $table->integer('stock')->default(0);
            $table->boolean('por_encargue')->default(false);
            $table->string('url_imagen')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('productos');
    }
}
