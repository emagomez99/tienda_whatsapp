<?php

namespace App\Models;

use App\Support\Mes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Pedido extends Model
{
    protected $fillable = [
        'nombre', 'apellido', 'email', 'celular',
        'direccion', 'localidad', 'provincia', 'cp',
        'estado',
    ];

    public function items()
    {
        return $this->hasMany(PedidoProducto::class);
    }

    public function totales()
    {
        return $this->hasMany(PedidoTotal::class);
    }

    /**
     * Los totales para mostrar: sin los que dan cero (productos sin precio en esa
     * moneda), que sólo confunden al lado del total de verdad. Si todos dan cero,
     * se muestran igual.
     */
    public function totalesVisibles()
    {
        $conPlata = $this->totales->filter(function ($total) {
            return (float) $total->total != 0.0;
        });

        return $conPlata->isNotEmpty() ? $conPlata->values() : $this->totales;
    }

    /**
     * Meses que se ofrecen para consultar pedidos, del más reciente al más viejo: al
     * menos los últimos 12, o desde el mes del primer pedido si es anterior.
     *
     * @return \App\Support\Mes[]
     */
    public static function mesesConsultables()
    {
        $masViejo     = Mes::de(Carbon::now()->subMonths(11));
        $primerPedido = self::min('created_at');

        if ($primerPedido && Mes::de(Carbon::parse($primerPedido))->esAnteriorA($masViejo)) {
            $masViejo = Mes::de(Carbon::parse($primerPedido));
        }

        return Mes::hastaHoyDesde($masViejo);
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

    /**
     * Bloquea el pedido hasta que termine la transacción y trae su estado de ahora.
     *
     * Confirmar, cancelar y cambiar ítems mueven stock: dos a la vez sobre el mismo
     * pedido (un doble clic, dos personas en el panel) descontaban o devolvían el
     * stock dos veces. Con el bloqueo, el segundo espera y ve el estado que dejó el
     * primero. Sólo tiene sentido dentro de DB::transaction.
     */
    public function bloquear(): self
    {
        $this->estado = static::whereKey($this->id)->lockForUpdate()->value('estado');
        $this->syncOriginalAttribute('estado');

        return $this;
    }

    public function recalcularTotal()
    {
        $this->totales()->delete();
        DB::table('pedido_productos')
            ->select('moneda_id', DB::raw('SUM(subtotal) as total_moneda'))
            ->where('pedido_id', $this->id)
            ->groupBy('moneda_id')
            ->get()
            ->each(function ($row) {
                $this->totales()->create([
                    'moneda_id' => $row->moneda_id,
                    'total'     => $row->total_moneda,
                ]);
            });
    }

    /**
     * Agrega unidades de un producto (o de una de sus variantes: Talle M) al pedido.
     * Si ya está, acumula la cantidad en el ítem existente.
     */
    public function agregarProducto(Producto $producto, $cantidad, Variante $variante = null)
    {
        return $this->enTransaccion(function () use ($producto, $cantidad, $variante) {
            $item = $this->items()->where('producto_id', $producto->id)
                ->where('variante_id', $variante ? $variante->id : null)->first();
            $cantAnterior = $item ? $item->cantidad : 0;

            return $this->aplicarCantidad($producto, $cantAnterior + $cantidad, $item, $variante);
        });
    }

    /**
     * Modifica la cantidad de un ítem existente del pedido.
     */
    public function actualizarItem(Producto $producto, $nuevaCantidad, PedidoProducto $item)
    {
        return $this->enTransaccion(function () use ($producto, $nuevaCantidad, $item) {
            // La cantidad de ahora, no la de cuando se abrió la pantalla.
            $item = $item->fresh();
            if (!$item) {
                return ['ok' => false, 'error' => 'Ese ítem ya no está en el pedido.'];
            }

            return $this->aplicarCantidad($producto, $nuevaCantidad, $item, $item->variante_id ? $item->variante : null);
        });
    }

    /**
     * Todo o nada, con el pedido bloqueado: si el stock cambió entre que se miró y
     * se movió (otra venta al mismo tiempo), no queda un movimiento sin su ítem ni
     * un error 500.
     */
    private function enTransaccion(callable $cambio): array
    {
        try {
            return DB::transaction(function () use ($cambio) {
                $this->bloquear();
                if ($this->esCancelado()) {
                    return ['ok' => false, 'error' => 'No se puede editar un pedido cancelado.'];
                }

                return $cambio();
            });
        } catch (\UnderflowException $e) {
            return ['ok' => false, 'error' => 'El stock cambió mientras se guardaba (¿otra venta al mismo tiempo?). No se cambió nada. ' . $e->getMessage()];
        }
    }

    /**
     * Núcleo compartido: valida stock, ajusta si está confirmado, persiste el ítem.
     * El stock es el de la variante si la hay (Talle M), o el del producto.
     */
    private function aplicarCantidad(Producto $producto, $nuevaCantidad, $item = null, Variante $variante = null)
    {
        $cantAnterior = $item ? $item->cantidad : 0;
        $diferencia   = $nuevaCantidad - $cantAnterior;
        $origen       = $variante ?: $producto;

        if ($variante === null && $producto->tieneVariantes()) {
            return ['ok' => false, 'error' => '"' . $producto->descripcion . '" tiene variantes: elegí cuál (por ejemplo, el talle).'];
        }

        if ($this->esConfirmado() && $diferencia > 0) {
            $result = $origen->evaluarCantidad($diferencia);
            if (! $result->esSuficiente()) {
                return ['ok' => false, 'error' => $result->error];
            }
        } elseif ($this->esPendiente()) {
            $result = $origen->evaluarCantidad($nuevaCantidad);
            if (! $result->esSuficiente()) {
                return ['ok' => false, 'error' => $result->error];
            }
        }

        if ($this->esConfirmado() && $diferencia !== 0 && !$producto->por_encargue) {
            // variacion = -diferencia: más unidades = salida, menos = devolución
            $variacion = -$diferencia;
            $tipo = $variacion < 0
                ? StockMovimiento::TIPO_SALIDA_PEDIDO
                : StockMovimiento::TIPO_DEVOLUCION_PEDIDO;

            $origen->registrarMovimiento(
                $variacion,
                $tipo,
                'Pedido #' . $this->id . ': ajuste de ítem',
                $this->id,
                Auth::id()
            );
        }

        if ($item === null) {
            $precio = $variante ? $variante->precioFinal() : $producto->precio;
            $this->items()->create([
                'producto_id'     => $producto->id,
                'variante_id'     => $variante ? $variante->id : null,
                'descripcion'     => $variante ? $variante->descripcionCompleta() : $producto->descripcion,
                'precio_unitario' => $precio,
                'cantidad'        => $nuevaCantidad,
                'subtotal'        => $precio * $nuevaCantidad,
                'moneda_id'       => $producto->moneda_id,
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
