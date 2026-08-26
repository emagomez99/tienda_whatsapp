<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Menu;
use App\Models\Producto;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    // Por debajo del límite de Google (50.000 URLs / 50MB por archivo), para que
    // cada sitemap individual sea liviano de generar, cachear y regenerar.
    const PRODUCTOS_POR_PAGINA_SITEMAP = 20000;

    /**
     * Índice de sitemaps: uno de páginas (home + menús) y uno o más de productos,
     * paginados para no crecer sin límite en un solo archivo.
     */
    public function sitemapIndex()
    {
        $xml = Cache::remember($this->cacheKey('sitemap_index'), 3600, function () {
            return $this->generarIndice();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function sitemapPaginas()
    {
        $xml = Cache::remember($this->cacheKey('sitemap_paginas'), 3600, function () {
            return $this->generarSitemapPaginas();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function sitemapProductos($pagina)
    {
        $pagina = max(1, (int) $pagina);

        $xml = Cache::remember($this->cacheKey('sitemap_productos_' . $pagina), 3600, function () use ($pagina) {
            return $this->generarSitemapProductos($pagina);
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function robots()
    {
        $lineas = ['User-agent: *'];

        if (Configuracion::robotsIndex()) {
            $lineas[] = 'Disallow: /admin';
            $lineas[] = 'Disallow: /login';
            $lineas[] = 'Disallow: /carrito';
            $lineas[] = '';
            $lineas[] = 'Sitemap: ' . route('tienda.sitemap');
        } else {
            // La tienda está marcada como "no indexar" (ej. en construcción)
            $lineas[] = 'Disallow: /';
        }

        return response(implode("\n", $lineas), 200)->header('Content-Type', 'text/plain');
    }

    private function generarIndice()
    {
        $totalProductos = Producto::visiblesEnTienda()->count();
        $totalPaginas = max(1, (int) ceil($totalProductos / self::PRODUCTOS_POR_PAGINA_SITEMAP));

        $sitemaps = [
            ['loc' => route('tienda.sitemap.paginas'), 'lastmod' => now()->toAtomString()],
        ];

        for ($pagina = 1; $pagina <= $totalPaginas; $pagina++) {
            $sitemaps[] = [
                'loc' => route('tienda.sitemap.productos', $pagina),
                'lastmod' => now()->toAtomString(),
            ];
        }

        return view('sitemap.index', compact('sitemaps'))->render();
    }

    private function generarSitemapPaginas()
    {
        $urls = [];

        $urls[] = [
            'loc' => route('tienda.index'),
            'lastmod' => now()->toAtomString(),
            'priority' => '1.0',
        ];

        Menu::where('activo', true)->whereNotNull('slug')->orderBy('id')
            ->get(['id', 'slug', 'updated_at'])
            ->each(function ($menu) use (&$urls) {
                $urls[] = [
                    'loc' => route('tienda.catalogo', $menu->slug),
                    'lastmod' => $menu->updated_at ? $menu->updated_at->toAtomString() : now()->toAtomString(),
                    'priority' => '0.8',
                ];
            });

        return view('sitemap.xml', compact('urls'))->render();
    }

    /**
     * Genera un solo "lote" de productos (self::PRODUCTOS_POR_PAGINA_SITEMAP como máximo),
     * consultando únicamente las columnas necesarias para no cargar el catálogo completo en memoria.
     */
    private function generarSitemapProductos($pagina)
    {
        $offset = ($pagina - 1) * self::PRODUCTOS_POR_PAGINA_SITEMAP;

        $urls = Producto::visiblesEnTienda()
            ->orderBy('id')
            ->skip($offset)
            ->take(self::PRODUCTOS_POR_PAGINA_SITEMAP)
            ->get(['id', 'public_id', 'updated_at'])
            ->map(function ($producto) {
                return [
                    'loc' => route('tienda.show', $producto),
                    'lastmod' => $producto->updated_at ? $producto->updated_at->toAtomString() : now()->toAtomString(),
                    'priority' => '0.6',
                ];
            })
            ->all();

        return view('sitemap.xml', compact('urls'))->render();
    }

    private function cacheKey($nombre)
    {
        $tenantId = (function_exists('tenancy') && tenancy()->initialized) ? tenant('id') : 'central';
        return $nombre . '_' . $tenantId;
    }
}
