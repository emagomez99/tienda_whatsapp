<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create
                            {slug     : Identificador único (ej: demo, arcor, perfumes)}
                            {nombre   : Nombre de la tienda}
                            {email    : Email del tenant}
                            {dominio  : Dominio del tenant (ej: tienda-juan.com)}
                            {--seed   : Ejecutar TenantDatabaseSeeder después de migrar}
                            {--plan=free : Plan del tenant}';

    protected $description = 'Crea un nuevo tenant con su schema PostgreSQL y dominio asociado';

    public function handle()
    {
        $slug    = $this->argument('slug');
        $nombre  = $this->argument('nombre');
        $email   = $this->argument('email');
        $dominio = $this->argument('dominio');
        $plan    = $this->option('plan');
        $seed    = $this->option('seed');

        $this->info("Creando tenant: [{$slug}] {$nombre} ({$email}) → {$dominio}");

        // Crear el tenant — el ID es el slug, el schema será tenant_{slug}
        $tenant = Tenant::create([
            'id'     => $slug,
            'nombre' => $nombre,
            'email'  => $email,
            'plan'   => $plan,
            'activo' => true,
        ]);

        $this->info("Tenant creado con ID: {$tenant->id}");

        // Asociar dominio
        $tenant->domains()->create(['domain' => $dominio]);
        $this->info("Dominio asociado: {$dominio}");

        // Seed opcional dentro del contexto del tenant
        if ($seed) {
            $this->info('Ejecutando TenantDatabaseSeeder...');
            tenancy()->initialize($tenant);

            \Artisan::call('db:seed', [
                '--class' => 'TenantDatabaseSeeder',
                '--force' => true,
            ]);

            tenancy()->end();
            $this->info('Seed completado.');
        }

        $this->info("Tenant {$nombre} creado exitosamente.");
        $this->line("  Schema: tenant_{$tenant->id}");
        $this->line("  Dominio: http://{$dominio}");

        return Command::SUCCESS;
    }
}
