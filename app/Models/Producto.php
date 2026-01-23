<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'proveedor_id',
        'id_proveedor',
        'descripcion',
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
     * Obtiene la URL completa de la imagen (local o externa)
     */
    public function getImagenUrlAttribute(): ?string
    {
        if (!$this->url_imagen) {
            return null;
        }

        // Si empieza con http, es una URL externa
        if (str_starts_with($this->url_imagen, 'http')) {
            return $this->url_imagen;
        }

        // Es una imagen local en storage
        return asset('storage/' . $this->url_imagen);
    }

    /**
     * Verifica si la imagen es una URL externa
     */
    public function esImagenExterna(): bool
    {
        return $this->url_imagen && str_starts_with($this->url_imagen, 'http');
    }

    public function scopeDisponibles($query)
    {
        return $query->where('disponible', true)
                     ->where(function ($q) {
                         $q->where('stock', '>', 0)
                           ->orWhere('por_encargue', true);
                     });
    }
}
