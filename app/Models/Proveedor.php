<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'prefijo',
        'contacto',
        'telefono',
        'email',
        'notas',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function etiquetas()
    {
        return $this->belongsToMany(Etiqueta::class, 'proveedor_etiqueta')
                    ->withPivot('obligatoria')
                    ->withTimestamps();
    }

    public function etiquetasObligatorias()
    {
        return $this->belongsToMany(Etiqueta::class, 'proveedor_etiqueta')
                    ->wherePivot('obligatoria', true)
                    ->withTimestamps();
    }
}
