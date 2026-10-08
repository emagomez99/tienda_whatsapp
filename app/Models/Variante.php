<?php

namespace App\Models;

use App\Support\StockResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Una variante de un producto: lo que el cliente elige al comprar (Talle M, Color
 * Rojo), con su propio stock.
 *
 * Qué es cada variante lo dicen sus valores (producto_variante_valores): un valor
 * por característica de las que se eligen (Talle = M). Se arman en el formulario
 * del producto a partir de las características con varios valores.
 *
 * El stock del producto es la suma del de sus variantes activas: cada movimiento de
 * una variante lo recalcula (registrarMovimiento). Así lo que ya mira el stock del
 * producto (listados, "sin stock", el sitemap) sigue andando sin cambios.
 *
 * Precio y código son opcionales: vacíos, valen los del producto.
 */
class Variante extends Model
{
    protected $table = 'producto_variantes';

    protected $fillable = ['producto_id', 'codigo', 'precio', 'stock', 'activa', 'orden'];

    protected $casts = [
        'precio' => 'decimal:2',
        'stock'  => 'integer',
        'activa' => 'boolean',
        'orden'  => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    /** Qué valor tiene en cada característica (Talle = M), en el orden de las características. */
    public function valores()
    {
        return $this->belongsToMany(EtiquetaValor::class, 'producto_variante_valores', 'variante_id', 'etiqueta_valor_id')
            ->withPivot('etiqueta_id')
            ->orderBy('producto_variante_valores.id');
    }

    public function movimientos()
    {
        return $this->hasMany(StockMovimiento::class, 'variante_id');
    }

    /** "M · Rojo": los valores, en el orden de las características. */
    public function nombre(): string
    {
        return $this->valores->pluck('valor')->implode(' · ');
    }

    /** "Talle: M · Color: Rojo": con el nombre de cada característica, para que se entienda solo. */
    public function nombreDetallado(): string
    {
        return $this->valores->map(function (EtiquetaValor $valor) {
            return ($valor->etiqueta ? $valor->etiqueta->nombre . ': ' : '') . $valor->valor;
        })->implode(' · ');
    }

    /** "Remera lisa (M · Rojo)": para el carrito, el pedido y el historial de stock. */
    public function descripcionCompleta(): string
    {
        return $this->producto->descripcion . ' (' . $this->nombre() . ')';
    }

    /** etiqueta_id => etiqueta_valor_id: la combinación, para compararla con otra. */
    public function combinacion(): array
    {
        $combinacion = [];
        foreach ($this->valores as $valor) {
            $combinacion[(int) $valor->pivot->etiqueta_id] = (int) $valor->id;
        }
        ksort($combinacion);

        return $combinacion;
    }

    public static function claveDeCombinacion(array $combinacion): string
    {
        ksort($combinacion);
        $partes = [];
        foreach ($combinacion as $etiquetaId => $valorId) {
            $partes[] = $etiquetaId . ':' . $valorId;
        }

        return implode('|', $partes);
    }

    /** El precio que se cobra: el suyo o, si no tiene, el del producto. */
    public function precioFinal()
    {
        return $this->precio !== null ? $this->precio : $this->producto->precio;
    }

    /** null = sin límite (el producto se puede pedir sin stock). */
    public function stockMaximo(): ?int
    {
        return $this->producto->por_encargue ? null : (int) $this->stock;
    }

    /** Se puede elegir en la tienda: activa y con stock (o el producto se encarga). */
    public function sePuedePedir(): bool
    {
        return $this->activa && ($this->producto->por_encargue || $this->stock > 0);
    }

    /** Como Producto::evaluarCantidad, con el stock de la variante. */
    public function evaluarCantidad($solicitada): StockResult
    {
        $maximo = $this->stockMaximo();

        if ($maximo === null) {
            return StockResult::ok($solicitada);
        }
        if ($maximo <= 0) {
            return StockResult::insuficiente(0, 'Sin stock disponible para "' . $this->descripcionCompleta() . '".');
        }
        if ($solicitada > $maximo) {
            return StockResult::insuficiente($maximo, 'Stock insuficiente para "' . $this->descripcionCompleta() . '". Disponible: ' . $maximo . '.');
        }

        return StockResult::ok($solicitada);
    }

    /** Para el carrito de la tienda: una variante apagada (o de un producto oculto) no se pide. */
    public function evaluarParaLaTienda($solicitada): StockResult
    {
        if (!$this->activa || !$this->producto->disponible) {
            return StockResult::insuficiente(0, '"' . $this->descripcionCompleta() . '" ya no está a la venta.');
        }

        return $this->evaluarCantidad($solicitada);
    }

    /**
     * Suma o resta stock de esta variante y deja el movimiento en el historial del
     * producto (con la variante). El stock del producto pasa a ser la nueva suma.
     *
     * @throws \UnderflowException si quedaría negativo
     */
    public function registrarMovimiento($variacion, $tipo, $descripcion = null, $pedidoId = null, $userId = null)
    {
        return DB::transaction(function () use ($variacion, $tipo, $descripcion, $pedidoId, $userId) {
            // Primero el producto, siempre en el mismo orden: dos movimientos a la vez
            // de variantes del mismo producto no se cruzan.
            Producto::where('id', $this->producto_id)->lockForUpdate()->value('id');
            $actual = (int) static::where('id', $this->id)->lockForUpdate()->value('stock');
            $resultante = $actual + $variacion;

            if ($resultante < 0) {
                throw new \UnderflowException(
                    "El movimiento resultaría en stock negativo para \"{$this->descripcionCompleta()}\" (actual: {$actual}, variación: {$variacion})."
                );
            }

            static::where('id', $this->id)->update(['stock' => $resultante]);
            $this->stock = $resultante;
            $this->producto->recalcularStockDeVariantes();

            return StockMovimiento::create([
                'producto_id'      => $this->producto_id,
                'variante_id'      => $this->id,
                // El nombre también: si la variante se borra, el historial lo sigue diciendo.
                'variante_nombre'  => $this->nombreDetallado(),
                'tipo'             => $tipo,
                'variacion'        => $variacion,
                'stock_resultante' => $resultante,
                'descripcion'      => $descripcion,
                'pedido_id'        => $pedidoId,
                'user_id'          => $userId,
            ]);
        });
    }
}
