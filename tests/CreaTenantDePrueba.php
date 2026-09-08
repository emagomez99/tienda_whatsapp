<?php

namespace Tests;

use App\Models\Tenant;

/**
 * Tenant real de prueba: schema Postgres propio, creado antes de cada test y
 * destruido al terminar.
 *
 * A propósito NO se usa RefreshDatabase: apuntaría a la base central de desarrollo y
 * se llevaría puestos los tenants reales del usuario. Crear el tenant dispara
 * CreateDatabase + MigrateDatabase de forma síncrona, así que cada test corre contra
 * el esquema de verdad, migraciones incluidas.
 *
 * Cada clase de test define su propio TENANT_ID y TENANT_DOMAIN para que dos clases
 * no se pisen el schema.
 */
trait CreaTenantDePrueba
{
    /** @var \App\Models\Tenant */
    protected $tenant;

    protected function crearTenantDePrueba()
    {
        // Por si una corrida anterior se cortó a la mitad.
        $this->destruirTenantDePrueba();

        $this->tenant = Tenant::create([
            'id'     => static::TENANT_ID,
            'nombre' => 'Tenant de prueba (' . static::TENANT_ID . ')',
            'email'  => static::TENANT_ID . '@test.local',
            'plan'   => 'free',
            'activo' => true,
        ]);
        $this->tenant->domains()->create(['domain' => static::TENANT_DOMAIN]);

        return $this->tenant;
    }

    /** Borra el tenant de prueba y, con él, su schema completo. */
    protected function destruirTenantDePrueba()
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $tenant = Tenant::find(static::TENANT_ID);

        if ($tenant) {
            $tenant->delete(); // dispara DeleteDatabase -> DROP SCHEMA
        }
    }

    /** Ejecuta un callback dentro del contexto del tenant de prueba. */
    protected function enTenant(callable $callback)
    {
        tenancy()->initialize($this->tenant);

        try {
            return $callback();
        } finally {
            tenancy()->end();
        }
    }

    /** URL absoluta en el dominio del tenant (la tenancy identifica por Host). */
    protected function urlTenant($path)
    {
        return 'http://' . static::TENANT_DOMAIN . '/' . ltrim($path, '/');
    }
}
