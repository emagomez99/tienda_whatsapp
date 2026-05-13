<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class BackfillPedidoTotales extends Migration
{
    public function up()
    {
        // Pedidos sin filas en pedido_totales
        $pedidoIds = DB::table('pedidos')
            ->whereNotIn('id', function ($q) {
                $q->select('pedido_id')->from('pedido_totales');
            })
            ->pluck('id');

        foreach ($pedidoIds as $pedidoId) {
            $grupos = DB::table('pedido_productos')
                ->where('pedido_id', $pedidoId)
                ->select('moneda_id', DB::raw('SUM(subtotal) as total'))
                ->groupBy('moneda_id')
                ->get();

            if ($grupos->isEmpty()) {
                // Pedido sin ítems: usar el total guardado en pedidos.total
                $total = DB::table('pedidos')->where('id', $pedidoId)->value('total');
                if ($total > 0) {
                    DB::table('pedido_totales')->insert([
                        'pedido_id'  => $pedidoId,
                        'moneda_id'  => null,
                        'total'      => $total,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                foreach ($grupos as $grupo) {
                    DB::table('pedido_totales')->insert([
                        'pedido_id'  => $pedidoId,
                        'moneda_id'  => $grupo->moneda_id,
                        'total'      => $grupo->total,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down()
    {
        //
    }
}
