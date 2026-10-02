<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use App\Models\Tenant;
use App\Support\RecortadorDeMargenes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Aplica a los logos ya subidos el recorte de márgenes transparentes que ahora se
 * hace automáticamente al subir uno nuevo desde Ajustes.
 */
class RecortarLogos extends Command
{
    protected $signature = 'logos:recortar {--tenant= : ID de un tenant puntual (por defecto, todos)}';

    protected $description = 'Recorta los márgenes transparentes de los logos de las tiendas';

    public function handle()
    {
        $tenants = $this->option('tenant')
            ? Tenant::whereKey($this->option('tenant'))->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('No se encontró ningún tenant.');
            return Command::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $logo = $tenant->run(function () {
                return Configuracion::logo();
            });

            if (! $logo) {
                $this->line("[{$tenant->id}] sin logo");
                continue;
            }

            $ruta = Storage::disk('public')->path($logo);

            if (! is_file($ruta)) {
                $this->warn("[{$tenant->id}] el archivo {$logo} no existe");
                continue;
            }

            $this->line(RecortadorDeMargenes::recortar($ruta)
                ? "[{$tenant->id}] recortado: {$logo}"
                : "[{$tenant->id}] sin márgenes transparentes para recortar");
        }

        return Command::SUCCESS;
    }
}
