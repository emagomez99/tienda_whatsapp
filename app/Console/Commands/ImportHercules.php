<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Models\ProductoEspecificacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportHercules extends Command
{
    protected $signature = 'import:hercules
                            {--fabricante= : Fabricante específico (Atlas, Case)}
                            {--dry-run : Simular sin guardar en la base de datos}
                            {--refresh : Forzar descarga ignorando caché}
                            {--cache-only : Solo usar caché, no hacer requests}';

    protected $description = 'Importa productos desde herculesus.com';

    private const PROVEEDOR_NOMBRE = 'Hercules';
    private const PROVEEDOR_ID = 3;
    private const MONEDA_ID = 1;
    private const ETIQUETA_FABRICANTE = 1;
    private const ETIQUETA_APLICACION = 2;
    private const ETIQUETA_MODELO = 3;

    private const FABRICANTES = ['Atlas', 'Case'];

    private $stats = [
        'productos_nuevos' => 0,
        'productos_existentes' => 0,
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
        $fabricanteFilter = $this->option('fabricante');
        $dryRun = $this->option('dry-run');
        $this->forceRefresh = $this->option('refresh');
        $this->cacheOnly = $this->option('cache-only');

        $fabricantes = $fabricanteFilter
            ? [ucfirst(strtolower($fabricanteFilter))]
            : self::FABRICANTES;

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

        $this->info('Iniciando importación de Hercules...');
        $this->info("Log: {$this->logFile}");
        $this->info("Cache: {$this->cacheDir}");

        foreach ($fabricantes as $fabricante) {
            if (!in_array($fabricante, self::FABRICANTES)) {
                $this->error("Fabricante no válido: {$fabricante}");
                $this->log('ERROR', "Fabricante no válido: {$fabricante}");
                continue;
            }

            $this->procesarFabricante($fabricante, $dryRun);
        }

        $this->newLine();
        $this->info('=== Resumen de importación ===');
        $this->info("Productos nuevos: {$this->stats['productos_nuevos']}");
        $this->info("Productos existentes (omitidos): {$this->stats['productos_existentes']}");
        $this->info("Errores: {$this->stats['errores']}");
        $this->info("Cache hits: {$this->stats['cache_hits']}");
        $this->info("Cache misses (descargados): {$this->stats['cache_misses']}");

        // Log resumen final
        $this->log('INFO', '=== RESUMEN FINAL ===');
        $this->log('INFO', "Productos nuevos: {$this->stats['productos_nuevos']}");
        $this->log('INFO', "Productos existentes (omitidos): {$this->stats['productos_existentes']}");
        $this->log('INFO', "Errores: {$this->stats['errores']}");
        $this->log('INFO', "Cache hits: {$this->stats['cache_hits']}");
        $this->log('INFO', "Cache misses: {$this->stats['cache_misses']}");
        $this->log('INFO', '=== FIN DE IMPORTACIÓN ===');

        return Command::SUCCESS;
    }

    private function initCache(): void
    {
        $this->cacheDir = storage_path('app/cache/hercules');

        if (!File::isDirectory($this->cacheDir)) {
            File::makeDirectory($this->cacheDir, 0755, true);
        }
    }

    private function sanitizeFileName(string $name): string
    {
        // Convertir a minúsculas y reemplazar caracteres no válidos
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9_-]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name); // Múltiples _ a uno solo
        $name = trim($name, '_');
        return $name;
    }

    private function getCacheFilePath(string $tipo): string
    {
        // Crear nombre de archivo legible basado en el tipo
        // Ejemplo: "aplicaciones:Atlas" -> "hercules_atlas_aplicaciones.html"
        // Ejemplo: "productos:Atlas:CylinderKits:RodGlands" -> "hercules_atlas_cylinderkits_rodglands_productos.html"

        $parts = explode(':', $tipo);
        $tipoRequest = $this->sanitizeFileName(array_shift($parts)); // aplicaciones, modelos, productos

        $nameParts = ['hercules'];
        foreach ($parts as $part) {
            $nameParts[] = $this->sanitizeFileName($part);
        }
        $nameParts[] = $tipoRequest;

        $fileName = implode('_', $nameParts) . '.html';

        return $this->cacheDir . '/' . $fileName;
    }

    private function fetchWithCache(string $url, string $tipo = 'request'): ?string
    {
        $cacheFile = $this->getCacheFilePath($tipo);

        // Verificar si existe en caché y no estamos forzando refresh
        if (!$this->forceRefresh && File::exists($cacheFile)) {
            $this->stats['cache_hits']++;
            $this->log('CACHE', "Hit: {$tipo}", ['url' => $url]);
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
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
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
            $this->log('CACHE', "Miss (descargado): {$tipo}", ['url' => $url]);

            // Pequeña pausa para no saturar el servidor
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
        $this->logFile = storage_path("logs/import_" . strtolower(self::PROVEEDOR_NOMBRE) . "_{$timestamp}{$suffix}.log");

        // Crear encabezado del log
        $header = [
            '========================================',
            'IMPORTACIÓN DE PRODUCTOS - ' . self::PROVEEDOR_NOMBRE,
            '========================================',
            'Fecha: ' . now()->format('Y-m-d H:i:s'),
            'Modo: ' . ($dryRun ? 'DRY-RUN (simulación)' : 'PRODUCCIÓN'),
            'Refresh: ' . ($this->forceRefresh ? 'SI' : 'NO'),
            'Cache-only: ' . ($this->cacheOnly ? 'SI' : 'NO'),
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

    private function procesarFabricante(string $fabricante, bool $dryRun): void
    {
        $this->newLine();
        $this->info("📦 Procesando fabricante: {$fabricante}");
        $this->log('INFO', "--- Iniciando fabricante: {$fabricante} ---");

        $aplicaciones = $this->obtenerAplicaciones($fabricante);

        if (empty($aplicaciones)) {
            $this->warn("  No se encontraron aplicaciones para {$fabricante}");
            $this->log('WARN', "Sin aplicaciones para fabricante", ['fabricante' => $fabricante]);
            return;
        }

        $this->info("  Encontradas " . count($aplicaciones) . " aplicaciones");
        $this->log('INFO', "Aplicaciones encontradas: " . count($aplicaciones), ['fabricante' => $fabricante]);

        $progressBar = $this->output->createProgressBar(count($aplicaciones));
        $progressBar->start();

        foreach ($aplicaciones as $aplicacion) {
            $this->procesarAplicacion($fabricante, $aplicacion, $dryRun);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->log('INFO', "--- Fin fabricante: {$fabricante} ---");
    }

    private function obtenerAplicaciones(string $fabricante): array
    {
        $url = "https://herculesus.com/search_by_model/application2.php?category=" . urlencode($fabricante);

        $html = $this->fetchWithCache($url, "aplicaciones:{$fabricante}");

        if ($html === null) {
            $this->error("Error al obtener aplicaciones para {$fabricante}");
            return [];
        }

        preg_match_all('/<option[^>]*value="([^"]+)"[^>]*>/', $html, $matches);

        // Filtrar valores vacíos, "Select Application" y opciones de "All Applications" (contienen _^)
        return array_filter($matches[1], function ($value) {
            return !empty($value)
                && $value !== 'Select Application'
                && strpos($value, '_^') === false;
        });
    }

    private function procesarAplicacion(string $fabricante, string $aplicacion, bool $dryRun): void
    {
        $modelos = $this->obtenerModelos($fabricante, $aplicacion);

        foreach ($modelos as $modelo) {
            $this->procesarModelo($fabricante, $aplicacion, $modelo, $dryRun);
        }
    }

    private function obtenerModelos(string $fabricante, string $aplicacion): array
    {
        $url = "https://herculesus.com/search_by_model/model.php?" . http_build_query([
            'category2' => $aplicacion,
            'manufacturer' => $fabricante,
        ]);

        $html = $this->fetchWithCache($url, "modelos:{$fabricante}:{$aplicacion}");

        if ($html === null) {
            return [];
        }

        preg_match_all('/<option[^>]*value="([^"]+)"[^>]*>/', $html, $matches);

        return array_filter($matches[1], function ($value) {
            return !empty($value) && $value !== 'Select Model';
        });
    }

    private function procesarModelo(string $fabricante, string $aplicacion, string $modelo, bool $dryRun): void
    {
        $productos = $this->obtenerProductos($fabricante, $aplicacion, $modelo);

        foreach ($productos as $producto) {
            $this->guardarProducto($producto, $fabricante, $aplicacion, $modelo, $dryRun);
        }
    }

    private function obtenerProductos(string $fabricante, string $aplicacion, string $modelo): array
    {
        $baseParams = [
            'category3' => $modelo,
            'application' => $aplicacion,
            'manufacturer2' => $fabricante,
        ];

        // Obtener primera página
        $url = "https://herculesus.com/search_by_model/result_kit.php?" . http_build_query($baseParams);
        $html = $this->fetchWithCache($url, "productos:{$fabricante}:{$aplicacion}:{$modelo}");

        if ($html === null) {
            return [];
        }

        $todosProductos = $this->parsearProductos($html);

        // Detectar paginación - buscar el número máximo de página
        preg_match_all('/runPagination\([\'"](\d+)[\'"]/', $html, $pageMatches);
        $maxPage = !empty($pageMatches[1]) ? max(array_map('intval', $pageMatches[1])) : 1;

        // Descargar páginas adicionales si existen (usar pageLoc, no page)
        if ($maxPage > 1) {
            for ($page = 2; $page <= $maxPage; $page++) {
                $pageParams = array_merge($baseParams, ['pageLoc' => $page]);
                $pageUrl = "https://herculesus.com/search_by_model/result_kit.php?" . http_build_query($pageParams);
                $pageHtml = $this->fetchWithCache($pageUrl, "productos:{$fabricante}:{$aplicacion}:{$modelo}:pageLoc{$page}");

                if ($pageHtml !== null) {
                    $productosPage = $this->parsearProductos($pageHtml);
                    $todosProductos = array_merge($todosProductos, $productosPage);
                }
            }
        }

        return $todosProductos;
    }

    private function parsearProductos(string $html): array
    {
        $productos = [];
        $productosVistos = [];

        // Buscar elementos <input> con title (código), src (imagen) y onclick (id del producto)
        // Formato: <input ... title="ATL-APK20-000" ... src="/images/thumbnails/RGAL-600x600-1.jpg" ... onclick="doitProduct('13543');" />
        // Nota: códigos pueden tener 2 partes (JIC-D42872) o 3 partes (ATL-APK20-000)
        preg_match_all(
            '/<input[^>]*title="([A-Z0-9]+-[A-Z0-9]+(?:-[A-Z0-9]+)?)"[^>]*src="([^"]+)"[^>]*onclick="doitProduct\([\'"](\d+)[\'"]\)[^"]*"/i',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        // Extraer todos los precios (List Price) asociados a cada código de producto
        // Formato: <b>CODIGO</b>...<b>List Price:</b> <span...>$XX.XX</span>
        // Nota: códigos pueden tener 2 partes (JIC-D42872) o 3 partes (ATL-APK20-000)
        $preciosPorCodigo = [];
        preg_match_all(
            '/<b>([A-Z0-9]+-[A-Z0-9]+(?:-[A-Z0-9]+)?)<\/b>.*?List Price:.*?\$([\d,]+\.\d{2})/si',
            $html,
            $precioMatches,
            PREG_SET_ORDER
        );

        foreach ($precioMatches as $match) {
            $preciosPorCodigo[$match[1]] = floatval(str_replace(',', '', $match[2]));
        }

        foreach ($matches as $match) {
            $descripcion = $match[1];
            $srcImagen = $match[2];
            $idProveedor = $match[3];

            // Evitar duplicados (el mismo producto aparece múltiples veces en el HTML)
            if (isset($productosVistos[$idProveedor])) {
                continue;
            }
            $productosVistos[$idProveedor] = true;

            $precio = $preciosPorCodigo[$descripcion] ?? 0;

            // Construir URL completa de imagen
            // src puede ser: /images/thumbnails/XXX.jpg o /images/parts/XXX.jpg
            // Convertimos thumbnails a parts para mejor calidad
            $urlImagen = $srcImagen;
            if (strpos($urlImagen, '/') === 0) {
                $urlImagen = str_replace('/images/thumbnails/', '/images/parts/', $urlImagen);
                $urlImagen = 'https://herculesus.com' . $urlImagen;
            }

            $productos[] = [
                'id_proveedor' => $idProveedor,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'url_imagen' => $urlImagen,
            ];
        }

        return $productos;
    }

    private function guardarProducto(array $data, string $fabricante, string $aplicacion, string $modelo, bool $dryRun): void
    {
        // Verificar si ya existe
        $existe = Producto::where('proveedor_id', self::PROVEEDOR_ID)
            ->where('id_proveedor', $data['id_proveedor'])
            ->exists();

        if ($existe) {
            $this->stats['productos_existentes']++;
            $this->log('SKIP', "Producto existente (omitido)", [
                'id_proveedor' => $data['id_proveedor'],
                'descripcion' => $data['descripcion']
            ]);
            return;
        }

        if ($dryRun) {
            $this->stats['productos_nuevos']++;
            $this->log('DRY-RUN', "Producto detectado (no guardado)", [
                'id_proveedor' => $data['id_proveedor'],
                'descripcion' => $data['descripcion'],
                'precio' => $data['precio'],
                'fabricante' => $fabricante,
                'aplicacion' => $aplicacion,
                'modelo' => $modelo
            ]);
            return;
        }

        try {
            DB::transaction(function () use ($data, $fabricante, $aplicacion, $modelo) {
                $producto = Producto::create([
                    'proveedor_id' => self::PROVEEDOR_ID,
                    'id_proveedor' => $data['id_proveedor'],
                    'descripcion' => $data['descripcion'],
                    'precio' => $data['precio'],
                    'moneda_id' => self::MONEDA_ID,
                    'disponible' => true,
                    'stock' => 1,
                    'por_encargue' => true,
                    'url_imagen' => $data['url_imagen'],
                ]);

                // Asignar etiquetas
                $producto->etiquetas()->attach([
                    self::ETIQUETA_FABRICANTE => ['valor' => $fabricante],
                    self::ETIQUETA_APLICACION => ['valor' => $aplicacion],
                    self::ETIQUETA_MODELO => ['valor' => $modelo],
                ]);
            });

            $this->stats['productos_nuevos']++;
            $this->log('OK', "Producto creado", [
                'id_proveedor' => $data['id_proveedor'],
                'descripcion' => $data['descripcion'],
                'precio' => $data['precio'],
                'fabricante' => $fabricante,
                'aplicacion' => $aplicacion,
                'modelo' => $modelo
            ]);

        } catch (\Exception $e) {
            $this->stats['errores']++;
            $this->log('ERROR', "Error al guardar producto", [
                'id_proveedor' => $data['id_proveedor'],
                'descripcion' => $data['descripcion'],
                'error' => $e->getMessage()
            ]);
        }
    }
}
