<?php

namespace App\Models;

use App\Support\PrecioVenta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Moneda con cotización contra una moneda base.
 *
 * `cotizacion` es cuántas unidades de la moneda BASE vale 1 unidad de esta moneda.
 * La base vale 1 por definición (es la unidad de medida), y con eso cualquier par
 * convierte pasando por ella: monto * (origen.cotizacion / destino.cotizacion).
 * Ej. con ARS de base: ARS 1, USD 1500, EUR 1650 -> 1 USD = 1500/1650 EUR.
 */
class Moneda extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'simbolo',
        'activa',
        'cotizacion',
        'es_base',
        'es_default',
    ];

    protected $casts = [
        'activa'     => 'boolean',
        'es_base'    => 'boolean',
        'es_default' => 'boolean',
        'cotizacion' => 'decimal:6',
    ];

    /**
     * Invariantes de la moneda base. Viven acá y no en el formulario porque también
     * tienen que valer para las altas del seeder, de los imports y de los tests.
     */
    protected static function boot()
    {
        parent::boot();

        // La base es la unidad de referencia: su cotización es 1 por definición, y
        // desactivarla dejaría al resto de las cotizaciones sin contra qué medirse.
        // La preseleccionada tampoco puede estar inactiva: el formulario de producto
        // sólo ofrece monedas activas, así que preseleccionaría algo que no está en
        // la lista y el campo aparecería vacío sin explicación.
        static::saving(function ($moneda) {
            if ($moneda->es_base) {
                $moneda->cotizacion = 1;
                $moneda->activa     = true;
            }

            if ($moneda->es_default) {
                $moneda->activa = true;
            }
        });

        // Base y preseleccionada son únicas. Se degrada a la anterior por query
        // builder y no por save(), para no volver a entrar en este mismo hook.
        static::saved(function ($moneda) {
            if ($moneda->es_base) {
                static::where('id', '!=', $moneda->id)
                    ->where('es_base', true)
                    ->update(['es_base' => false, 'updated_at' => now()]);
            }

            if ($moneda->es_default) {
                static::where('id', '!=', $moneda->id)
                    ->where('es_default', true)
                    ->update(['es_default' => false, 'updated_at' => now()]);
            }
        });
    }

    /** Productos que se VENDEN en esta moneda. */
    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    /** Productos que se COMPRAN en esta moneda. */
    public function productosDeCompra()
    {
        return $this->hasMany(Producto::class, 'moneda_compra_id');
    }

    /** Unidad contra la que se miden todas las cotizaciones. Siempre hay una. */
    public static function base()
    {
        return static::where('es_base', true)->first();
    }

    /**
     * Moneda preseleccionada al cargar un producto. Puede no haber ninguna: es una
     * comodidad de carga, no una pieza estructural como la base.
     */
    public static function porDefecto()
    {
        return static::where('es_default', true)->where('activa', true)->first();
    }

    public static function idPorDefecto()
    {
        $moneda = static::porDefecto();

        return $moneda ? $moneda->id : null;
    }

    /** Convierte un importe de esta moneda a otra, redondeado a centavos. */
    public function convertirA(Moneda $destino, $monto)
    {
        return PrecioVenta::convertir($monto, $this->cotizacion, $destino->cotizacion);
    }

    /**
     * Dónde está siendo usada la moneda, en texto legible. Vacío = se puede eliminar.
     *
     * Se consulta con exists() y no con count(): alcanza con saber si hay al menos
     * uno, y en catálogos de 30.000 productos contar es tirar trabajo a la basura.
     */
    public function usos()
    {
        $usos = [];

        if ($this->productos()->exists()) {
            $usos[] = 'productos que se venden en esta moneda';
        }

        if ($this->productosDeCompra()->exists()) {
            $usos[] = 'productos que se compran en esta moneda';
        }

        if ($this->pedidoProductos()->exists() || $this->pedidoTotales()->exists()) {
            $usos[] = 'pedidos ya registrados';
        }

        return $usos;
    }

    public function pedidoProductos()
    {
        return $this->hasMany(PedidoProducto::class);
    }

    public function pedidoTotales()
    {
        return $this->hasMany(PedidoTotal::class);
    }
}
