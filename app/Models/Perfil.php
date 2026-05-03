<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perfil extends Model
{
    protected $table = 'perfiles';

    protected $fillable = ['nombre', 'descripcion', 'es_superadmin', 'activo'];

    protected $casts = [
        'es_superadmin' => 'boolean',
        'activo'        => 'boolean',
    ];

    public function permisos()
    {
        return $this->belongsToMany(Permiso::class, 'perfil_permiso');
    }

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    public function tiene($permiso)
    {
        if ($this->es_superadmin) {
            return true;
        }

        return $this->permisos->contains('nombre', $permiso);
    }
}
