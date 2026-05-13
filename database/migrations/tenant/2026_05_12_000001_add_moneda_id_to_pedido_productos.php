<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMonedaIdToPedidoProductos extends Migration
{
    public function up()
    {
        Schema::table('pedido_productos', function (Blueprint $table) {
            $table->foreignId('moneda_id')->nullable()->constrained('monedas')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('pedido_productos', function (Blueprint $table) {
            $table->dropForeign(['moneda_id']);
            $table->dropColumn('moneda_id');
        });
    }
}
