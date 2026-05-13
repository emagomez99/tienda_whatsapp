<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePedidoTotalesTable extends Migration
{
    public function up()
    {
        Schema::create('pedido_totales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->onDelete('cascade');
            $table->foreignId('moneda_id')->nullable()->constrained('monedas')->onDelete('set null');
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['pedido_id', 'moneda_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('pedido_totales');
    }
}
