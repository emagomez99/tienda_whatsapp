<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\CaracteristicasDesdeProveedores;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migración de las reglas por proveedor a reglas por categoría (plan de Categorías,
 * sección 6). Correrlo después de categorias:desde-etiquetas: necesita los
 * productos ya en sus categorías.
 *
 *   php artisan caracteristicas:desde-proveedores --tenant=perfumes --dry-run
 */
class CaracteristicasDesdeProveedoresCommand extends Command
{
    protected $signature = 'caracteristicas:desde-proveedores
                            {--tenant=* : Tienda (id); se puede repetir}
                            {--dry-run : Mostrar qué haría, sin guardar nada}';

    protected $description = 'Pasa las etiquetas obligatorias/opcionales de cada proveedor a las categorías de sus productos';

    public function handle(CaracteristicasDesdeProveedores $migracion)
    {
        $tenants = $this->option('tenant');
        if (empty($tenants)) {
            $this->error('Indicá la tienda con --tenant=id (se puede repetir).');
            return 1;
        }

        $simulacion = (bool) $this->option('dry-run');

        foreach ($tenants as $id) {
            $tenant = Tenant::find($id);
            if (!$tenant) {
                $this->error("No existe la tienda «{$id}».");
                return 1;
            }

            tenancy()->initialize($tenant);
            try {
                DB::beginTransaction();
                $informe = $migracion->ejecutar();
                $simulacion ? DB::rollBack() : DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("{$id}: {$e->getMessage()}");
                return 1;
            } finally {
                tenancy()->end();
            }

            $this->line('');
            $this->info("══ {$id}" . ($simulacion ? '  (simulación: no se guardó nada)' : ''));
            $this->line('<comment>Para todos los productos (globales)</comment>');
            foreach ($informe['globales'] ?: ['(ninguna)'] as $linea) {
                $this->line("  {$linea}");
            }
            $this->line('<comment>Por categoría</comment>');
            if (empty($informe['porCategoria'])) {
                $this->line('  (nada nuevo)');
            }
            foreach ($informe['porCategoria'] as $camino => $lista) {
                $this->line("  {$camino} → {$lista}");
            }
            if ($informe['sinDestino']) {
                $this->line('<comment>Obligatorias que no tienen categoría a la que pasar (quedan sin exigirse):</comment>');
                foreach ($informe['sinDestino'] as $linea) {
                    $this->line("  {$linea}");
                }
            }
        }

        return 0;
    }
}
