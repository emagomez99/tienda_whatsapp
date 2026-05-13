<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoTotal extends Model
{
    protected $table = 'pedido_totales';

    protected $fillable = ['pedido_id', 'moneda_id', 'total'];

    protected $casts = ['total' => 'decimal:2'];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class);
    }
}
