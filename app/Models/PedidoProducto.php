<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoProducto extends Model
{
    protected $fillable = [
        'pedido_id', 'producto_id', 'descripcion',
        'precio_unitario', 'cantidad', 'subtotal', 'moneda_id', 'variante_id',
    ];

    protected $casts = [
        'precio_unitario' => 'decimal:2',
        'subtotal'        => 'decimal:2',
        'cantidad'        => 'integer',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class);
    }

    public function variante()
    {
        return $this->belongsTo(Variante::class);
    }

    /**
     * De dónde sale el stock de este ítem: su variante (Talle M) o el producto.
     * null si el producto ya no existe. Las dos tienen evaluarCantidad,
     * registrarMovimiento y stockMaximo.
     *
     * @return Variante|Producto|null
     */
    public function origenDeStock()
    {
        if ($this->variante_id && $this->variante) {
            return $this->variante;
        }

        return $this->producto;
    }
}
