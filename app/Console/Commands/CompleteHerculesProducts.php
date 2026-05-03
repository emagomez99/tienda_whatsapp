<?php

namespace App\Console\Commands;

use App\Models\Etiqueta;
use App\Models\Producto;
use App\Models\ProductoEspecificacion;
use App\Models\Proveedor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class CompleteHerculesProducts extends Command
{
    protected $signature = 'hercules:complete
                            {--tenant= : ID del tenant (obligatorio)}
                            {--fabricante= : Fabricante específico (Atlas, Case, Ford)}
                            {--producto= : ID de producto específico para pruebas}
                            {--dry-run : Simular sin guardar en la base de datos}
                            {--refresh : Forzar descarga ignorando caché}
                            {--cache-only : Solo usar caché, no hacer requests}
                            {--limit= : Limitar cantidad de productos a procesar}';

    protected $description = 'Completa productos de Hercules con especificaciones desde su página de detalle';

    // IDs resueltos dinámicamente en el contexto del tenant
    private $proveedorId;
    private $etiquetaFabricanteId;

    private $stats = [
        'productos_procesados'        => 0,
        'especificaciones_nuevas'     => 0,
        'especificaciones_actualizadas' => 0,
        'errores'                     => 0,
        'cache_hits'                  => 0,
        'cache_misses'                => 0,
    ];

    private $logFile;
    private $cacheDir;
    private $forceRefresh = false;
    private $cacheOnly = false;

    public function handle()
    {
        // --tenant es obligatorio en arquitectura multi-tenant
        $tenantId = $this->option('tenant');
        if (!$tenantId) {
            $this->error('Debes especificar un tenant: --tenant={id}');
            $this->line('Tenants disponibles: ' . \App\Models\Tenant::pluck('id')->join(', '));
            return Command::FAILURE;
        }

        $tenant = \App\Models\Tenant::findOrFail($tenantId);
        tenancy()->initialize($tenant);
        $this->info("Tenant: {$tenant->nombre} ({$tenant->id})");

        // Resolver IDs dinámicamente en el contexto del tenant
        $proveedor = Proveedor::where('nombre', 'Hercules')->first();
        if (!$proveedor) {
            $this->error('No se encontró el proveedor "Hercules" en este tenant. Ejecutá import:hercules primero.');
            tenancy()->end();
            return Command::FAILURE;
        }
        $this->proveedorId = $proveedor->id;

        $etiquetaFabricante = Etiqueta::where('nombre', 'Fabricante')->first();
        if (!$etiquetaFabricante) {
            $this->error('No se encontró la etiqueta "Fabricante" en este tenant. Ejecutá import:hercules primero.');
            tenancy()->end();
            return Command::FAILURE;
        }
        $this->etiquetaFabricanteId = $etiquetaFabricante->id;

        $fabricante    = $this->option('fabricante');
        $productoId    = $this->option('producto');
        $dryRun        = $this->option('dry-run');
        $this->forceRefresh = $this->option('refresh');
        $this->cacheOnly    = $this->option('cache-only');
        $limit         = $this->option('limit');

        $this->initCache();
        $this->initLog($dryRun);

        if ($dryRun)              $this->info('Modo dry-run activado - no se guardarán cambios');
        if ($this->forceRefresh)  $this->info('Modo refresh activado - ignorando caché');
        if ($this->cacheOnly)     $this->info('Modo cache-only - solo usando datos en caché');

        $this->info('Completando productos de Hercules con especificaciones...');
        $this->info("Log: {$this->logFile}");

        // Construir query de productos del tenant actual
        $query = Producto::where('proveedor_id', $this->proveedorId);

        if ($productoId) {
            $query->where('id', $productoId);
        }

        if ($fabricante) {
            $fabricante = ucfirst(strtolower($fabricante));
            $etiquetaFabricanteId = $this->etiquetaFabricanteId;
            $query->whereHas('etiquetas', function ($q) use ($fabricante, $etiquetaFabricanteId) {
                $q->where('etiqueta_id', $etiquetaFabricanteId)
                  ->where('valor', $fabricante);
            });
            $this->info("Filtrando por fabricante: {$fabricante}");
        }

        if ($limit) {
            $query->limit((int) $limit);
        }

        $productos = $query->get();
        $total     = $productos->count();

        if ($total === 0) {
            $this->warn('No se encontraron productos para procesar');
            tenancy()->end();
            return Command::SUCCESS;
        }

        $this->info("Productos a procesar: {$total}");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        foreach ($productos as $producto) {
            $this->procesarProducto($producto, $dryRun);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('=== Resumen ===');
        $this->info("Productos procesados: {$this->stats['productos_procesados']}");
        $this->info("Especificaciones nuevas: {$this->stats['especificaciones_nuevas']}");
        $this->info("Especificaciones actualizadas: {$this->stats['especificaciones_actualizadas']}");
        $this->info("Errores: {$this->stats['errores']}");
        $this->info("Cache hits: {$this->stats['cache_hits']}");
        $this->info("Cache misses: {$this->stats['cache_misses']}");

        $this->log('INFO', '=== RESUMEN FINAL ===');
        $this->log('INFO', "Productos procesados: {$this->stats['productos_procesados']}");
        $this->log('INFO', "Especificaciones nuevas: {$this->stats['especificaciones_nuevas']}");
        $this->log('INFO', "Especificaciones actualizadas: {$this->stats['especificaciones_actualizadas']}");
        $this->log('INFO', "Errores: {$this->stats['errores']}");

        tenancy()->end();

        return Command::SUCCESS;
    }

    private function procesarProducto(Producto $producto, bool $dryRun): void
    {
        $url = "https://herculesus.com/product_page.php?productid={$producto->id_proveedor}";

        $html = $this->fetchWithCache($url, "producto:{$producto->id_proveedor}");

        if ($html === null) {
            $this->stats['errores']++;
            $this->log('ERROR', 'No se pudo obtener HTML', [
                'producto_id'  => $producto->id,
                'id_proveedor' => $producto->id_proveedor,
            ]);
            return;
        }

        $especificaciones = $this->parsearEspecificaciones($html);

        if (empty($especificaciones)) {
            $this->log('WARN', 'Sin especificaciones encontradas', [
                'producto_id'  => $producto->id,
                'id_proveedor' => $producto->id_proveedor,
            ]);
            return;
        }

        foreach ($especificaciones as $clave => $valor) {
            if ($dryRun) {
                $this->log('DRY-RUN', "Especificación: {$clave} = {$valor}", ['producto_id' => $producto->id]);
                continue;
            }

            try {
                $spec = ProductoEspecificacion::updateOrCreate(
                    ['producto_id' => $producto->id, 'clave' => $clave],
                    ['valor'       => $valor]
                );

                if ($spec->wasRecentlyCreated) {
                    $this->stats['especificaciones_nuevas']++;
                } else {
                    $this->stats['especificaciones_actualizadas']++;
                }
            } catch (\Exception $e) {
                $this->stats['errores']++;
                $this->log('ERROR', "Error guardando especificación", [
                    'producto_id' => $producto->id,
                    'clave'       => $clave,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->stats['productos_procesados']++;
        $this->log('INFO', "Producto completado", [
            'producto_id'   => $producto->id,
            'id_proveedor'  => $producto->id_proveedor,
            'especificaciones' => count($especificaciones),
        ]);
    }

    private function parsearEspecificaciones(string $html): array
    {
        $especificaciones = [];

        preg_match_all(
            '/<tr>\s*<td[^>]*>([^<]+)<\/td>\s*<td[^>]*>([^<]*)<\/td>\s*<\/tr>/i',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $clave = trim($match[1]);
            $valor = trim($match[2]);

            if (empty($valor) || $valor === '-' || $valor === '—') {
                continue;
            }

            if ($clave === 'Product') {
                continue;
            }

            $clave = preg_replace('/\s+/', ' ', $clave);

            if (!isset($especificaciones[$clave])) {
                $especificaciones[$clave] = $valor;
            }
        }

        return $especificaciones;
    }

    private function initCache(): void
    {
        $this->cacheDir = storage_path('app/cache/hercules/productos');

        if (!File::isDirectory($this->cacheDir)) {
            File::makeDirectory($this->cacheDir, 0755, true);
        }
    }

    private function getCacheFilePath(string $tipo): string
    {
        $parts    = explode(':', $tipo);
        $fileName = 'hercules_' . implode('_', $parts) . '.html';
        return $this->cacheDir . '/' . $fileName;
    }

    private function fetchWithCache(string $url, string $tipo = 'request'): ?string
    {
        $cacheFile = $this->getCacheFilePath($tipo);

        if (!$this->forceRefresh && File::exists($cacheFile)) {
            $this->stats['cache_hits']++;
            return File::get($cacheFile);
        }

        if ($this->cacheOnly) {
            $this->log('CACHE', "Miss (cache-only): {$tipo}", ['url' => $url]);
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($url);

            if (!$response->successful()) {
                $this->log('ERROR', "HTTP error: {$tipo}", ['url' => $url, 'status' => $response->status()]);
                return null;
            }

            $html = $response->body();
            File::put($cacheFile, $html);
            $this->stats['cache_misses']++;

            usleep(200000); // 200ms para no saturar el servidor

            return $html;

        } catch (\Exception $e) {
            $this->log('ERROR', "Excepción en request: {$tipo}", ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function initLog(bool $dryRun): void
    {
        $timestamp    = now()->format('Y-m-d_H-i-s');
        $suffix       = $dryRun ? '_dry-run' : '';
        $this->logFile = storage_path("logs/hercules_complete_{$timestamp}{$suffix}.log");

        $header = [
            '========================================',
            'COMPLETAR PRODUCTOS HERCULES',
            '========================================',
            'Fecha: ' . now()->format('Y-m-d H:i:s'),
            'Modo: ' . ($dryRun ? 'DRY-RUN (simulación)' : 'PRODUCCIÓN'),
            '========================================',
            '',
        ];

        File::put($this->logFile, implode("\n", $header));
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp  = now()->format('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        File::append($this->logFile, "[{$timestamp}] [{$level}] {$message}{$contextStr}\n");
    }
}
