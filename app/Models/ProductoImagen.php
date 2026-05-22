<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoImagen extends Model
{
    protected $table = 'producto_imagenes';

    protected $fillable = ['producto_id', 'url', 'orden'];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function getImagenUrlAttribute()
    {
        if (strpos($this->url, 'http') === 0) {
            return $this->url;
        }
        return url('storage/' . $this->url);
    }

    public function esExterna()
    {
        return strpos($this->url, 'http') === 0;
    }
}
