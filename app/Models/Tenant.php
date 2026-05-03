<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public static function getCustomColumns(): array
    {
        return ['id', 'nombre', 'email', 'plan', 'activo'];
    }

    protected $fillable = ['id', 'nombre', 'email', 'plan', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // El ID es un slug string (ej: "demo", "arcor"), no auto-incremental.
    // Necesario porque id_generator=null deja getIncrementing()=true en el trait GeneratesIds.
    public function getIncrementing()
    {
        return false;
    }

    public function getKeyType()
    {
        return 'string';
    }
}
