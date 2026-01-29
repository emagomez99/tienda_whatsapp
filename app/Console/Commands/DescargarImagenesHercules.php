<?php

namespace App\Console\Commands;

use App\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class DescargarImagenesHercules extends Command
{
    protected $signature = 'hercules:descargar:imagenes:productos
                            {--fabricante= : Fabricante específico (Atlas, Case)}
                            {--producto= : ID de producto específico para pruebas}
                            {--dry-run : Simular sin descargar ni guardar}
                            {--limit= : Limitar cantidad de productos a procesar}
                            {--force : Descargar incluso si ya tiene imagen local}';

    protected $description = 'Descarga las imágenes de productos Hercules y las guarda localmente';

    private const PROVEEDOR_ID = 3;
    private const ETIQUETA_FABRICANTE = 1;

    private $stats = [
        'productos_procesados' => 0,
        'imagenes_descargadas' => 0,
        'imagenes_ya_locales' => 0,
        'imagenes_sin_url' => 0,
        'errores' => 0,
    ];

    private $logFile;
    private $imageDir;

    public function handle()
    {
        $fabricante = $this->option('fabricante');
        $productoId = $this->option('producto');
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        $force = $this->option('force');

        $this->initLog($dryRun);
        $this->initImageDir();

        if ($dryRun) {
            $this->info('Modo dry-run activado - no se descargarán imágenes');
        }

        $this->info('Descargando imágenes de productos Hercules...');
        $this->info("Log: {$this->logFile}");
        $this->info("Directorio de imágenes: {$this->imageDir}");

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

        // Si no se fuerza, solo productos con imágenes externas
        if (!$force) {
            $query->where('url_imagen', 'like', 'http%');
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
            $this->procesarProducto($producto, $dryRun, $force);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Mostrar resumen
        $this->info('=== Resumen ===');
        $this->info("Productos procesados: {$this->stats['productos_procesados']}");
        $this->info("Imágenes descargadas: {$this->stats['imagenes_descargadas']}");
        $this->info("Ya tenían imagen local: {$this->stats['imagenes_ya_locales']}");
        $this->info("Sin URL de imagen: {$this->stats['imagenes_sin_url']}");
        $this->info("Errores: {$this->stats['errores']}");

        // Log resumen
        $this->log('INFO', '=== RESUMEN FINAL ===');
        $this->log('INFO', "Productos procesados: {$this->stats['productos_procesados']}");
        $this->log('INFO', "Imágenes descargadas: {$this->stats['imagenes_descargadas']}");
        $this->log('INFO', "Errores: {$this->stats['errores']}");

        return Command::SUCCESS;
    }

    private function procesarProducto(Producto $producto, bool $dryRun, bool $force): void
    {
        $this->stats['productos_procesados']++;

        // Verificar si tiene URL de imagen
        if (empty($producto->url_imagen)) {
            $this->stats['imagenes_sin_url']++;
            $this->log('SKIP', 'Sin URL de imagen', ['producto_id' => $producto->id]);
            return;
        }

        // Verificar si ya es imagen local (no empieza con http)
        if (!$force && !str_starts_with($producto->url_imagen, 'http')) {
            $this->stats['imagenes_ya_locales']++;
            $this->log('SKIP', 'Ya tiene imagen local', [
                'producto_id' => $producto->id,
                'url_imagen' => $producto->url_imagen
            ]);
            return;
        }

        $urlExterna = $producto->url_imagen;

        // Generar nombre de archivo local
        // Usar el código del producto (descripcion) como nombre de archivo
        $extension = pathinfo(parse_url($urlExterna, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $nombreArchivo = $this->sanitizeFileName($producto->descripcion) . '.' . $extension;
        $rutaRelativa = 'productos/hercules/' . $nombreArchivo;

        if ($dryRun) {
            $this->log('DRY-RUN', "Descargaría imagen", [
                'producto_id' => $producto->id,
                'url' => $urlExterna,
                'destino' => $rutaRelativa
            ]);
            return;
        }

        // Descargar imagen
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'image/*',
                ])
                ->get($urlExterna);

            if (!$response->successful()) {
                $this->stats['errores']++;
                $this->log('ERROR', "HTTP error al descargar imagen", [
                    'producto_id' => $producto->id,
                    'url' => $urlExterna,
                    'status' => $response->status()
                ]);
                return;
            }

            // Verificar que el contenido sea una imagen
            $contentType = $response->header('Content-Type');
            if (!str_starts_with($contentType, 'image/')) {
                $this->stats['errores']++;
                $this->log('ERROR', "Respuesta no es imagen", [
                    'producto_id' => $producto->id,
                    'content_type' => $contentType
                ]);
                return;
            }

            // Guardar imagen
            Storage::disk('public')->put($rutaRelativa, $response->body());

            // Actualizar producto
            $producto->url_imagen = $rutaRelativa;
            $producto->save();

            $this->stats['imagenes_descargadas']++;
            $this->log('OK', "Imagen descargada", [
                'producto_id' => $producto->id,
                'ruta' => $rutaRelativa
            ]);

            // Pequeña pausa para no saturar el servidor
            usleep(100000); // 100ms

        } catch (\Exception $e) {
            $this->stats['errores']++;
            $this->log('ERROR', "Excepción al descargar", [
                'producto_id' => $producto->id,
                'url' => $urlExterna,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sanitizeFileName(string $name): string
    {
        // Reemplazar caracteres no válidos para nombres de archivo
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        return $name;
    }

    private function initImageDir(): void
    {
        $this->imageDir = storage_path('app/public/productos/hercules');

        if (!File::isDirectory($this->imageDir)) {
            File::makeDirectory($this->imageDir, 0755, true);
        }
    }

    private function initLog(bool $dryRun): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $suffix = $dryRun ? '_dry-run' : '';
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
        $timestamp = now()->format('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        File::append($this->logFile, $line);
    }
}
