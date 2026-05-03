<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Evita que Sanctum registre sus propias migraciones automáticamente.
        // La migración personal_access_tokens está en database/migrations/tenant/
        // y debe correr solo en cada schema de tenant, no en el central.
        Sanctum::ignoreMigrations();
    }

    public function boot()
    {
        Carbon::setLocale('es');

        // Fuerza search_path=public en la conexión central de PostgreSQL.
        // Necesario porque stancl/tenancy puede interferir con el search_path
        // al cambiar de schema para cada tenant.
        if (config('database.default') === 'pgsql' && !tenancy()->initialized) {
            DB::statement("SET search_path TO public");
        }
    }
}
