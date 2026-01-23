<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEtiquetasTable extends Migration
{
    public function up()
    {
        Schema::create('etiquetas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique()->comment('Nombre de la etiqueta, ej: Categoria, Rubro, Marca');
            $table->boolean('visible_usuarios')->default(true)->comment('Si es visible para usuarios en tienda');
            $table->timestamps();
        });

        // Tabla pivot para productos y etiquetas con valor específico
        Schema::create('producto_etiqueta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->foreignId('etiqueta_id')->constrained('etiquetas')->onDelete('cascade');
            $table->string('valor')->comment('Valor de la etiqueta para este producto');
            $table->timestamps();

            $table->unique(['producto_id', 'etiqueta_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('producto_etiqueta');
        Schema::dropIfExists('etiquetas');
    }
}
