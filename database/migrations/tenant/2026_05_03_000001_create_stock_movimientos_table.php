<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockMovimientosTable extends Migration
{
    public function up()
    {
        Schema::create('stock_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->string('tipo', 30);
            $table->integer('variacion');       // signed: positivo=entrada, negativo=salida
            $table->integer('stock_resultante'); // snapshot del stock tras el movimiento
            $table->string('descripcion')->nullable();
            // Sin FK en pedido_id/user_id para preservar historial si se eliminan
            $table->unsignedBigInteger('pedido_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['producto_id', 'created_at']);
            $table->index('pedido_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_movimientos');
    }
}
