<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'activo',
        'perfil_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin'          => 'boolean',
        'activo'            => 'boolean',
    ];

    public function perfil()
    {
        return $this->belongsTo(Perfil::class);
    }

    public function isAdmin()
    {
        return $this->is_admin;
    }

    public function esSuperAdmin()
    {
        $this->loadMissing('perfil');
        return $this->perfil && $this->perfil->es_superadmin;
    }

    // Verifica si el usuario tiene un permiso específico.
    // Si es admin sin perfil asignado, tiene acceso total (compatibilidad hacia atrás).
    public function puede($permiso)
    {
        if ($this->is_admin && !$this->perfil_id) {
            return true;
        }

        if (!$this->perfil_id) {
            return false;
        }

        $this->loadMissing('perfil.permisos');

        return $this->perfil->tiene($permiso);
    }
}
