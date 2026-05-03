<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovimiento extends Model
{
    const TIPO_AJUSTE_INICIAL    = 'ajuste_inicial';
    const TIPO_AJUSTE_MANUAL     = 'ajuste_manual';
    const TIPO_SALIDA_PEDIDO     = 'salida_pedido';
    const TIPO_DEVOLUCION_PEDIDO = 'devolucion_pedido';
    const TIPO_IMPORTACION       = 'importacion';

    protected $table = 'stock_movimientos';

    protected $fillable = [
        'producto_id', 'tipo', 'variacion', 'stock_resultante',
        'descripcion', 'pedido_id', 'user_id',
    ];

    protected $casts = [
        'variacion'        => 'integer',
        'stock_resultante' => 'integer',
        'pedido_id'        => 'integer',
        'user_id'          => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function esEntrada()
    {
        return $this->variacion > 0;
    }

    public function esSalida()
    {
        return $this->variacion < 0;
    }

    public function tipoLabel()
    {
        $labels = [
            self::TIPO_AJUSTE_INICIAL    => 'Ajuste inicial',
            self::TIPO_AJUSTE_MANUAL     => 'Ajuste manual',
            self::TIPO_SALIDA_PEDIDO     => 'Salida por pedido',
            self::TIPO_DEVOLUCION_PEDIDO => 'Devolución de pedido',
            self::TIPO_IMPORTACION       => 'Importación',
        ];

        return $labels[$this->tipo] ?? $this->tipo;
    }

    public function tipoIcono()
    {
        $iconos = [
            self::TIPO_AJUSTE_INICIAL    => 'bi-play-circle',
            self::TIPO_AJUSTE_MANUAL     => 'bi-pencil-square',
            self::TIPO_SALIDA_PEDIDO     => 'bi-cart-dash',
            self::TIPO_DEVOLUCION_PEDIDO => 'bi-arrow-counterclockwise',
            self::TIPO_IMPORTACION       => 'bi-cloud-download',
        ];

        return $iconos[$this->tipo] ?? 'bi-circle';
    }

    public function tipoBadgeClase()
    {
        if ($this->esEntrada()) return 'success';
        if ($this->esSalida())  return 'danger';
        return 'secondary';
    }
}
