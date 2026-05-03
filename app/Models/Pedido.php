<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Pedido extends Model
{
    protected $fillable = [
        'nombre', 'apellido', 'email', 'celular',
        'direccion', 'localidad', 'provincia', 'cp',
        'estado', 'total',
    ];

    public function items()
    {
        return $this->hasMany(PedidoProducto::class);
    }

    public function esPendiente()
    {
        return $this->estado === 'pendiente';
    }

    public function esConfirmado()
    {
        return $this->estado === 'confirmado';
    }

    public function esCancelado()
    {
        return $this->estado === 'cancelado';
    }

    public function recalcularTotal()
    {
        $this->total = $this->items()->sum('subtotal');
        $this->save();
    }

    /**
     * Agrega unidades de un producto al pedido.
     * Si el producto ya existe, acumula la cantidad en el ítem existente.
     */
    public function agregarProducto(Producto $producto, $cantidad)
    {
        $item         = $this->items()->where('producto_id', $producto->id)->first();
        $cantAnterior = $item ? $item->cantidad : 0;

        return $this->aplicarCantidad($producto, $cantAnterior + $cantidad, $item);
    }

    /**
     * Modifica la cantidad de un ítem existente del pedido.
     */
    public function actualizarItem(Producto $producto, $nuevaCantidad, PedidoProducto $item)
    {
        return $this->aplicarCantidad($producto, $nuevaCantidad, $item);
    }

    /**
     * Núcleo compartido: valida stock, ajusta si está confirmado, persiste el ítem.
     */
    private function aplicarCantidad(Producto $producto, $nuevaCantidad, $item = null)
    {
        $cantAnterior = $item ? $item->cantidad : 0;
        $diferencia   = $nuevaCantidad - $cantAnterior;

        if ($this->esConfirmado() && $diferencia > 0) {
            $result = $producto->evaluarCantidad($diferencia);
            if (! $result->esSuficiente()) {
                return ['ok' => false, 'error' => $result->error];
            }
        } elseif ($this->esPendiente()) {
            $result = $producto->evaluarCantidad($nuevaCantidad);
            if (! $result->esSuficiente()) {
                return ['ok' => false, 'error' => $result->error];
            }
        }

        if ($this->esConfirmado() && $diferencia !== 0) {
            // variacion = -diferencia: más unidades = salida, menos = devolución
            $variacion = -$diferencia;
            $tipo = $variacion < 0
                ? StockMovimiento::TIPO_SALIDA_PEDIDO
                : StockMovimiento::TIPO_DEVOLUCION_PEDIDO;

            $producto->registrarMovimiento(
                $variacion,
                $tipo,
                'Pedido #' . $this->id . ': ajuste de ítem',
                $this->id,
                Auth::id()
            );
        }

        if ($item === null) {
            $this->items()->create([
                'producto_id'     => $producto->id,
                'descripcion'     => $producto->descripcion,
                'precio_unitario' => $producto->precio,
                'cantidad'        => $nuevaCantidad,
                'subtotal'        => $producto->precio * $nuevaCantidad,
            ]);
        } else {
            $item->cantidad = $nuevaCantidad;
            $item->subtotal = $item->precio_unitario * $nuevaCantidad;
            $item->save();
        }

        $this->recalcularTotal();

        return ['ok' => true, 'error' => null];
    }
}
