<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductoEspecificacionesTable extends Migration
{
    public function up()
    {
        Schema::create('producto_especificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->string('clave')->comment('Nombre de la especificacion, ej: Peso');
            $table->string('valor')->comment('Valor de la especificacion, ej: 1.75');
            $table->timestamps();

            $table->unique(['producto_id', 'clave']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('producto_especificaciones');
    }
}
