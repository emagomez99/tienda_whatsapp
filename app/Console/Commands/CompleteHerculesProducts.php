<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Models\ProductoEspecificacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CompleteHerculesProducts extends Command
{
    protected $signature = 'hercules:complete
                            {--fabricante= : Fabricante específico (Atlas, Case)}
                            {--producto= : ID de producto específico para pruebas}
                            {--dry-run : Simular sin guardar en la base de datos}
                            {--refresh : Forzar descarga ignorando caché}
                            {--cache-only : Solo usar caché, no hacer requests}
                            {--limit= : Limitar cantidad de productos a procesar}';

    protected $description = 'Completa productos de Hercules con especificaciones desde su página de detalle';

    private const PROVEEDOR_ID = 3;
    private const ETIQUETA_FABRICANTE = 1;

    private $stats = [
        'productos_procesados' => 0,
        'especificaciones_nuevas' => 0,
        'especificaciones_actualizadas' => 0,
        'errores' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
    ];

    private $logFile;
    private $cacheDir;
    private $forceRefresh = false;
    private $cacheOnly = false;

    public function handle()
    {
        $fabricante = $this->option('fabricante');
        $productoId = $this->option('producto');
        $dryRun = $this->option('dry-run');
        $this->forceRefresh = $this->option('refresh');
        $this->cacheOnly = $this->option('cache-only');
        $limit = $this->option('limit');

        // Inicializar caché y log
        $this->initCache();
        $this->initLog($dryRun);

        if ($dryRun) {
            $this->info('🔍 Modo dry-run activado - no se guardarán cambios');
        }
        if ($this->forceRefresh) {
            $this->info('🔄 Modo refresh activado - ignorando caché');
        }
        if ($this->cacheOnly) {
            $this->info('📦 Modo cache-only - solo usando datos en caché');
        }

        $this->info('Completando productos de Hercules con especificaciones...');
        $this->info("Log: {$this->logFile}");

        // Construir query de productos
        $query = Producto::where('proveedor_id', self::PROVEEDOR_ID);

        // Si se especifica un producto específico
        if ($productoId) {
            $query->where('id', $productoId);
        }

        // Si se especifica un fabricante, filtrar por etiqueta
        if ($fabricante) {
            $fabricante = ucfirst(strtolower($fabricante));
            $query->whereHas('etiquetas', function ($q) use ($fabricante) {
                $q->where('etiqueta_id', self::ETIQUETA_FABRICANTE)
                  ->where('valor', $fabricante);
            });
            $this->info("Filtrando por fabricante: {$fabricante}");
        }

        // Aplicar límite si se especifica
        if ($limit) {
            $query->limit((int) $limit);
        }

        $productos = $query->get();
        $total = $productos->count();

        if ($total === 0) {
            $this->warn('No se encontraron productos para procesar');
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

        // Mostrar resumen
        $this->info('=== Resumen ===');
        $this->info("Productos procesados: {$this->stats['productos_procesados']}");
        $this->info("Especificaciones nuevas: {$this->stats['especificaciones_nuevas']}");
        $this->info("Especificaciones actualizadas: {$this->stats['especificaciones_actualizadas']}");
        $this->info("Errores: {$this->stats['errores']}");
        $this->info("Cache hits: {$this->stats['cache_hits']}");
        $this->info("Cache misses: {$this->stats['cache_misses']}");

        // Log resumen
        $this->log('INFO', '=== RESUMEN FINAL ===');
        $this->log('INFO', "Productos procesados: {$this->stats['productos_procesados']}");
        $this->log('INFO', "Especificaciones nuevas: {$this->stats['especificaciones_nuevas']}");
        $this->log('INFO', "Especificaciones actualizadas: {$this->stats['especificaciones_actualizadas']}");
        $this->log('INFO', "Errores: {$this->stats['errores']}");

        return Command::SUCCESS;
    }

    private function procesarProducto(Producto $producto, bool $dryRun): void
    {
        $url = "https://herculesus.com/product_page.php?productid={$producto->id_proveedor}";

        $html = $this->fetchWithCache($url, "producto:{$producto->id_proveedor}");

        if ($html === null) {
            $this->stats['errores']++;
            $this->log('ERROR', 'No se pudo obtener HTML', [
                'producto_id' => $producto->id,
                'id_proveedor' => $producto->id_proveedor
            ]);
            return;
        }

        // Parsear especificaciones del HTML
        $especificaciones = $this->parsearEspecificaciones($html);

        if (empty($especificaciones)) {
            $this->log('WARN', 'Sin especificaciones encontradas', [
                'producto_id' => $producto->id,
                'id_proveedor' => $producto->id_proveedor
            ]);
            return;
        }

        // Guardar especificaciones
        foreach ($especificaciones as $clave => $valor) {
            if ($dryRun) {
                $this->log('DRY-RUN', "Especificación: {$clave} = {$valor}", [
                    'producto_id' => $producto->id
                ]);
                continue;
            }

            try {
                $spec = ProductoEspecificacion::updateOrCreate(
                    [
                        'producto_id' => $producto->id,
                        'clave' => $clave,
                    ],
                    [
                        'valor' => $valor,
                    ]
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
                    'clave' => $clave,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->stats['productos_procesados']++;
        $this->log('INFO', "Producto completado", [
            'producto_id' => $producto->id,
            'id_proveedor' => $producto->id_proveedor,
            'especificaciones' => count($especificaciones)
        ]);
    }

    private function parsearEspecificaciones(string $html): array
    {
        $especificaciones = [];

        // Formato real del HTML:
        // <tr><td bgcolor="#EEEEEE" class="whiteborder">Clave</td><td bgcolor="#EEEEEE" class="whiteborder">Valor</td></tr>
        preg_match_all(
            '/<tr>\s*<td[^>]*>([^<]+)<\/td>\s*<td[^>]*>([^<]*)<\/td>\s*<\/tr>/i',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $clave = trim($match[1]);
            $valor = trim($match[2]);

            // Ignorar especificaciones vacías o con valor "-"
            if (empty($valor) || $valor === '-' || $valor === '—') {
                continue;
            }

            // Ignorar la fila "Product" ya que es el código del producto
            if ($clave === 'Product') {
                continue;
            }

            // Limpiar el nombre de clave
            $clave = preg_replace('/\s+/', ' ', $clave);

            // Evitar duplicados, quedarse con el primer valor
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
        // Formato: producto:13451 -> hercules_producto_13451.html
        $parts = explode(':', $tipo);
        $fileName = 'hercules_' . implode('_', $parts) . '.html';
        return $this->cacheDir . '/' . $fileName;
    }

    private function fetchWithCache(string $url, string $tipo = 'request'): ?string
    {
        $cacheFile = $this->getCacheFilePath($tipo);

        // Verificar si existe en caché y no estamos forzando refresh
        if (!$this->forceRefresh && File::exists($cacheFile)) {
            $this->stats['cache_hits']++;
            return File::get($cacheFile);
        }

        // Si estamos en modo cache-only, no hacer request
        if ($this->cacheOnly) {
            $this->log('CACHE', "Miss (cache-only mode): {$tipo}", ['url' => $url]);
            return null;
        }

        // Hacer request y guardar en caché
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($url);

            if (!$response->successful()) {
                $this->log('ERROR', "HTTP error: {$tipo}", [
                    'url' => $url,
                    'status' => $response->status()
                ]);
                return null;
            }

            $html = $response->body();

            // Guardar en caché
            File::put($cacheFile, $html);
            $this->stats['cache_misses']++;

            // Pausa para no saturar el servidor
            usleep(200000); // 200ms

            return $html;

        } catch (\Exception $e) {
            $this->log('ERROR', "Excepción en request: {$tipo}", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function initLog(bool $dryRun): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $suffix = $dryRun ? '_dry-run' : '';
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
        $timestamp = now()->format('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        File::append($this->logFile, $line);
    }
}
