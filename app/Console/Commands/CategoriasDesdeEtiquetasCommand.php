<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\CategoriasDesdeEtiquetas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migración de etiquetas a categorías (plan de Categorías, sección 6).
 *
 *   php artisan categorias:desde-etiquetas --tenant=perfumes --dry-run
 *   php artisan categorias:desde-etiquetas --tenant=oleomc --proveedor="Hercules=Kits hidráulicos"
 *
 * Con --dry-run hace todo dentro de una transacción y la deshace: lo que muestra
 * es exactamente lo que haría, no una estimación.
 */
class CategoriasDesdeEtiquetasCommand extends Command
{
    protected $signature = 'categorias:desde-etiquetas
                            {--tenant=* : Tienda (id); se puede repetir}
                            {--proveedor=* : "Proveedor=Camino de categoría" para todos sus productos; se puede repetir}
                            {--dry-run : Mostrar qué haría, sin guardar nada}';

    protected $description = 'Crea categorías desde las etiquetas Categoria/Subcategoria, asigna los productos y convierte los menús';

    public function handle(CategoriasDesdeEtiquetas $migracion)
    {
        $tenants = $this->option('tenant');
        if (empty($tenants)) {
            $this->error('Indicá la tienda con --tenant=id (se puede repetir).');
            return 1;
        }

        $porProveedor = [];
        foreach ($this->option('proveedor') as $par) {
            if (strpos($par, '=') === false) {
                $this->error("--proveedor tiene que ser \"Proveedor=Categoría\": «{$par}».");
                return 1;
            }
            [$proveedor, $camino] = array_map('trim', explode('=', $par, 2));
            $porProveedor[$proveedor] = $camino;
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
                $informe = $migracion->ejecutar($porProveedor);
                $simulacion ? DB::rollBack() : DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("{$id}: {$e->getMessage()}");
                return 1;
            } finally {
                tenancy()->end();
            }

            $this->mostrar($id, $informe, $simulacion);
        }

        return 0;
    }

    private function mostrar(string $tienda, array $informe, bool $simulacion): void
    {
        $this->line('');
        $this->info("══ {$tienda}" . ($simulacion ? '  (simulación: no se guardó nada)' : ''));

        $lista = function (string $titulo, array $items, string $vacio) {
            $this->line("<comment>{$titulo}</comment>");
            if (empty($items)) {
                $this->line("  {$vacio}");
            }
            foreach ($items as $clave => $valor) {
                $this->line('  ' . (is_int($clave) ? $valor : "{$clave} → {$valor}"));
            }
        };

        $lista('Categorías creadas', array_values($informe['creadas']), '(ninguna)');
        $lista('Categorías que ya existían y se usaron', array_values($informe['usadas']), '(ninguna)');
        $lista('Productos agregados por categoría', array_map(function ($n) {
            return $n === 0 ? 'ninguno nuevo (ya estaban)' : $n . ($n === 1 ? ' producto' : ' productos');
        }, $informe['asignados']), '(ninguno)');
        $lista('Menús convertidos a "Los de una categoría"', $informe['menus'], '(ninguno)');
        $lista('Menús que quedan como estaban', $informe['menusSinConvertir'], '(ninguno)');
        $this->line('<comment>Productos sin categoría al terminar:</comment> ' . $informe['sinCategoria']);
    }
}
