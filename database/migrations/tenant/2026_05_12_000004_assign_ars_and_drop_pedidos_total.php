<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssignArsAndDropPedidosTotal extends Migration
{
    public function up()
    {
        // Buscar moneda ARS (Peso Argentino)
        $arsId = DB::table('monedas')->where('codigo', 'ARS')->value('id');

        if (!$arsId) {
            $arsId = DB::table('monedas')
                ->whereRaw("lower(nombre) like '%peso%'")
                ->value('id');
        }

        // Asignar ARS a los registros de pedido_totales que quedaron sin moneda
        if ($arsId) {
            DB::table('pedido_totales')
                ->whereNull('moneda_id')
                ->update(['moneda_id' => $arsId]);
        }

        // Eliminar columna total de pedidos (ahora vive en pedido_totales)
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('total');
        });
    }

    public function down()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->decimal('total', 10, 2)->default(0);
        });
    }
}
