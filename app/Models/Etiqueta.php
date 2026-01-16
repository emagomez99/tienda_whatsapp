<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Etiqueta extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'visible_usuarios',
    ];

    protected $casts = [
        'visible_usuarios' => 'boolean',
    ];

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'producto_etiqueta')
                    ->withPivot('valor')
                    ->withTimestamps();
    }
}
