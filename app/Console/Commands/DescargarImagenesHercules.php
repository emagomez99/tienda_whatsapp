<?php

namespace App\Console\Commands;

use App\Models\Etiqueta;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Proveedor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class DescargarImagenesHercules extends Command
{
    protected $signature = 'hercules:descargar:imagenes:productos
                            {--tenant= : ID del tenant (obligatorio)}
                            {--fabricante= : Fabricante específico (Atlas, Case, Ford)}
                            {--producto= : ID de producto específico para pruebas}
                            {--dry-run : Simular sin descargar ni guardar}
                            {--limit= : Limitar cantidad de productos a procesar}
                            {--force : Descargar incluso si ya tiene imagen local}';

    protected $description = 'Descarga las imágenes de productos Hercules y las guarda localmente en el storage del tenant';

    // IDs resueltos dinámicamente en el contexto del tenant
    private $proveedorId;
    private $etiquetaFabricanteId;
    private $tenantStoragePrefix;

    private $stats = [
        'productos_procesados' => 0,
        'imagenes_descargadas' => 0,
        'imagenes_ya_locales'  => 0,
        'imagenes_sin_url'     => 0,
        'errores'              => 0,
    ];

    private $logFile;

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
            $this->error('No se encontró la etiqueta "Fabricante" en este tenant.');
            tenancy()->end();
            return Command::FAILURE;
        }
        $this->etiquetaFabricanteId = $etiquetaFabricante->id;

        // Prefijo de storage por tenant para no mezclar imágenes entre tenants
        $this->tenantStoragePrefix = tenant('id') . '/productos/hercules';

        $fabricante = $this->option('fabricante');
        $productoId = $this->option('producto');
        $dryRun     = $this->option('dry-run');
        $limit      = $this->option('limit');
        $force      = $this->option('force');

        $this->initLog($dryRun);

        if ($dryRun) $this->info('Modo dry-run activado - no se descargarán imágenes');

        $this->info('Descargando imágenes de productos Hercules...');
        $this->info("Log: {$this->logFile}");
        $this->info("Storage: {$this->tenantStoragePrefix}/");

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

        // Solo productos con al menos una imagen externa (URL http), salvo --force
        if (!$force) {
            $query->whereHas('imagenes', function ($q) {
                $q->where('url', 'ilike', 'http%');
            });
        }

        if ($limit) {
            $query->limit((int) $limit);
        }

        $productos = $query->with('imagenes')->get();
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
            $this->procesarProducto($producto, $dryRun, $force);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('=== Resumen ===');
        $this->info("Productos procesados: {$this->stats['productos_procesados']}");
        $this->info("Imágenes descargadas: {$this->stats['imagenes_descargadas']}");
        $this->info("Ya tenían imagen local: {$this->stats['imagenes_ya_locales']}");
        $this->info("Sin URL de imagen: {$this->stats['imagenes_sin_url']}");
        $this->info("Errores: {$this->stats['errores']}");

        $this->log('INFO', '=== RESUMEN FINAL ===');
        $this->log('INFO', "Productos procesados: {$this->stats['productos_procesados']}");
        $this->log('INFO', "Imágenes descargadas: {$this->stats['imagenes_descargadas']}");
        $this->log('INFO', "Errores: {$this->stats['errores']}");

        tenancy()->end();

        return Command::SUCCESS;
    }

    private function procesarProducto(Producto $producto, bool $dryRun, bool $force): void
    {
        $this->stats['productos_procesados']++;

        $imagenes = $producto->imagenes;

        if ($imagenes->isEmpty()) {
            $this->stats['imagenes_sin_url']++;
            $this->log('SKIP', 'Sin imágenes', ['producto_id' => $producto->id]);
            return;
        }

        foreach ($imagenes as $imagen) {
            // strpos() === 0 en lugar de str_starts_with() — compatible con PHP 7.3
            $esUrlExterna = strpos($imagen->url, 'http') === 0;

            if (!$esUrlExterna) {
                $this->stats['imagenes_ya_locales']++;
                $this->log('SKIP', 'Ya tiene imagen local', [
                    'producto_id' => $producto->id,
                    'url'         => $imagen->url,
                ]);
                continue;
            }

            $this->descargarImagen($imagen, $producto, $dryRun);
        }
    }

    private function descargarImagen(ProductoImagen $imagen, Producto $producto, bool $dryRun): void
    {
        $urlExterna = $imagen->url;

        // Ruta de destino con prefijo de tenant para aislar imágenes por tenant
        $extension    = pathinfo(parse_url($urlExterna, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $nombreArch   = $this->sanitizeFileName($producto->descripcion) . '_' . $imagen->id . '.' . $extension;
        $rutaRelativa = $this->tenantStoragePrefix . '/' . $nombreArch;

        if ($dryRun) {
            $this->log('DRY-RUN', "Descargaría imagen", [
                'producto_id' => $producto->id,
                'imagen_id'   => $imagen->id,
                'url'         => $urlExterna,
                'destino'     => $rutaRelativa,
            ]);
            return;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept'     => 'image/*',
                ])
                ->get($urlExterna);

            if (!$response->successful()) {
                $this->stats['errores']++;
                $this->log('ERROR', "HTTP error al descargar imagen", [
                    'producto_id' => $producto->id,
                    'imagen_id'   => $imagen->id,
                    'url'         => $urlExterna,
                    'status'      => $response->status(),
                ]);
                return;
            }

            $contentType = $response->header('Content-Type');
            // strpos() en lugar de str_starts_with() — PHP 7.3 compatible
            if (strpos($contentType, 'image/') !== 0) {
                $this->stats['errores']++;
                $this->log('ERROR', "Respuesta no es imagen", [
                    'producto_id'  => $producto->id,
                    'content_type' => $contentType,
                ]);
                return;
            }

            Storage::disk('public')->put($rutaRelativa, $response->body());

            $imagen->url = $rutaRelativa;
            $imagen->save();

            $this->stats['imagenes_descargadas']++;
            $this->log('OK', "Imagen descargada", [
                'producto_id' => $producto->id,
                'imagen_id'   => $imagen->id,
                'ruta'        => $rutaRelativa,
            ]);

            usleep(100000); // 100ms para no saturar el servidor

        } catch (\Exception $e) {
            $this->stats['errores']++;
            $this->log('ERROR', "Excepción al descargar", [
                'producto_id' => $producto->id,
                'url'         => $urlExterna,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private function sanitizeFileName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        return trim($name, '_');
    }

    private function initLog(bool $dryRun): void
    {
        $timestamp    = now()->format('Y-m-d_H-i-s');
        $suffix       = $dryRun ? '_dry-run' : '';
        $this->logFile = storage_path("logs/hercules_imagenes_{$timestamp}{$suffix}.log");

        $header = [
            '========================================',
            'DESCARGA DE IMAGENES HERCULES',
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
