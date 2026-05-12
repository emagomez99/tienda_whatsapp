<?php

namespace App\Models;

use App\Support\StockResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Producto extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($modelo) {
            if (empty($modelo->public_id)) {
                $modelo->public_id = Str::uuid()->toString();
            }
        });
    }

    protected $fillable = [
        'public_id',
        'proveedor_id',
        'id_proveedor',
        'descripcion',
        'detalle',
        'precio',
        'moneda_id',
        'disponible',
        'stock',
        'por_encargue',
        'url_imagen',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'disponible' => 'boolean',
        'por_encargue' => 'boolean',
        'stock' => 'integer',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class);
    }

    public function etiquetas()
    {
        return $this->belongsToMany(Etiqueta::class, 'producto_etiqueta')
                    ->withPivot('valor')
                    ->withTimestamps();
    }

    public function especificaciones()
    {
        return $this->hasMany(ProductoEspecificacion::class);
    }

    public function movimientos()
    {
        return $this->hasMany(StockMovimiento::class);
    }

    /**
     * Registra un movimiento de stock y actualiza el campo `stock` atómicamente.
     * Usa lock pesimista para evitar condiciones de carrera concurrentes.
     *
     * @throws \UnderflowException si el movimiento resultaría en stock negativo
     */
    public function registrarMovimiento($variacion, $tipo, $descripcion = null, $pedidoId = null, $userId = null)
    {
        return DB::transaction(function () use ($variacion, $tipo, $descripcion, $pedidoId, $userId) {
            $stockActual     = static::where('id', $this->id)->lockForUpdate()->value('stock');
            $stockResultante = $stockActual + $variacion;

            if ($stockResultante < 0) {
                throw new \UnderflowException(
                    "El movimiento resultaría en stock negativo para \"{$this->descripcion}\" (actual: {$stockActual}, variación: {$variacion})."
                );
            }

            static::where('id', $this->id)->update(['stock' => $stockResultante]);
            $this->stock = $stockResultante;

            return StockMovimiento::create([
                'producto_id'      => $this->id,
                'tipo'             => $tipo,
                'variacion'        => $variacion,
                'stock_resultante' => $stockResultante,
                'descripcion'      => $descripcion,
                'pedido_id'        => $pedidoId,
                'user_id'          => $userId,
            ]);
        });
    }

    // Accessor para obtener el precio formateado con símbolo de moneda
    public function getPrecioConMonedaAttribute()
    {
        $simbolo = $this->moneda ? $this->moneda->simbolo : '$';
        return $simbolo . number_format($this->precio, 2);
    }

    public function estaDisponible()
    {
        return $this->disponible && ($this->stock > 0 || $this->por_encargue);
    }

    /**
     * Retorna el límite máximo de unidades que se pueden pedir.
     * null = sin límite (por encargue o sin control de stock).
     */
    public function stockMaximo()
    {
        if ($this->por_encargue || $this->stock === null) {
            return null;
        }
        return (int) $this->stock;
    }

    /**
     * Evalúa si la cantidad solicitada es satisfacible con el stock actual.
     * Retorna un StockResult con la cantidad permitida y, si corresponde, el motivo del recorte.
     */
    public function evaluarCantidad($solicitada)
    {
        $maximo = $this->stockMaximo();

        if ($maximo === null) {
            return StockResult::ok($solicitada);
        }

        if ($maximo <= 0) {
            return StockResult::insuficiente(0, 'Sin stock disponible para "' . $this->descripcion . '".');
        }

        if ($solicitada > $maximo) {
            return StockResult::insuficiente($maximo, 'Stock insuficiente para "' . $this->descripcion . '". Disponible: ' . $maximo . '.');
        }

        return StockResult::ok($solicitada);
    }

    /**
     * Obtiene la URL completa de la imagen (local o externa)
     */
    public function getImagenUrlAttribute(): ?string
    {
        if (!$this->url_imagen) {
            return null;
        }

        // Si empieza con http, es una URL externa
        if (strpos($this->url_imagen, 'http') === 0) {
            return $this->url_imagen;
        }

        // Es una imagen local en storage. Usamos url() en vez de Storage::disk()->url()
        // porque Storage usa APP_URL (dominio central) mientras que url() usa el dominio
        // del request actual (correcto para multi-tenant).
        return url('storage/' . $this->url_imagen);
    }

    /**
     * Verifica si la imagen es una URL externa
     */
    public function esImagenExterna(): bool
    {
        return $this->url_imagen && strpos($this->url_imagen, 'http') === 0;
    }

    public function scopeDisponibles($query)
    {
        return $query->where('disponible', true)
                     ->where(function ($q) {
                         $q->where('stock', '>', 0)
                           ->orWhere('por_encargue', true);
                     });
    }

    public function getRouteKeyName()
    {
        return 'public_id';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (!Str::isUuid($value)) {
            abort(404);
        }
        return $this->where($field ?? $this->getRouteKeyName(), $value)->firstOrFail();
    }
}
