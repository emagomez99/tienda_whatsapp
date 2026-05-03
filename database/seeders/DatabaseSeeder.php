<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Las migraciones centrales solo crean tenants y domains.
        // Los datos de la aplicación se crean por tenant con TenantDatabaseSeeder.
    }
}
