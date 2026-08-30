<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Etiqueta;
use App\Models\Menu;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TiendaController extends Controller
{
    public function index(Request $request)
    {
        // Parámetros numéricos: si vienen con valor no numérico (ej: "undefined") → 404
        if ($request->filled('etiqueta') && !is_numeric($request->etiqueta)) {
            abort(404);
        }
        if ($request->filled('proveedor') && !is_numeric($request->proveedor)) {
            abort(404);
        }
        if ($request->filled('menu') && !is_numeric($request->menu)) {
            abort(404);
        }

        // Redirigir URLs legacy al slug si el menú ya tiene uno
        if ($request->filled('menu') && is_numeric($request->menu)) {
            $menuLegacy = Menu::find((int) $request->menu);
            if ($menuLegacy && !$menuLegacy->activo) {
                abort(404);
            }
            if ($menuLegacy && $menuLegacy->slug) {
                $queryParams = $request->except(['menu', 'etiqueta', 'etiqueta_valor', 'proveedor', 'especificacion']);
                $slugUrl = route('tienda.catalogo', $menuLegacy->slug);
                if (!empty($queryParams)) {
                    $slugUrl .= '?' . http_build_query($queryParams);
                }
                return redirect($slugUrl, 301);
            }
        }

        $query = Producto::with(['proveedor', 'etiquetas', 'especificaciones', 'moneda'])
            ->visiblesEnTienda();

        // Filtro por búsqueda
        if ($request->filled('buscar')) {
            $this->aplicarFiltroBusqueda($query, $request->buscar);
        }

        // Filtros directos desde URL (modo legacy / sin slug)
        if ($request->filled('etiqueta') && is_numeric($request->etiqueta)) {
            $etiquetaId  = (int) $request->etiqueta;
            $etiquetaValor = $request->etiqueta_valor;
            $query->whereHas('etiquetas', function ($q) use ($etiquetaId, $etiquetaValor) {
                $q->where('etiquetas.id', $etiquetaId);
                if ($etiquetaValor) {
                    $q->where('producto_etiqueta.valor', $etiquetaValor);
                }
            });
        }

        if ($request->filled('proveedor') && is_numeric($request->proveedor)) {
            $query->where('proveedor_id', (int) $request->proveedor);
        }

        if ($request->filled('especificacion')) {
            $val = $request->especificacion;
            $query->whereHas('especificaciones', function ($q) use ($val) {
                $q->where('valor', 'ilike', "%{$val}%");
            });
        }

        // Menú (solo para filtro_stock y contexto de filtros en cascada)
        $menuActual = null;
        if ($request->filled('menu')) {
            $menuActual = Menu::find((int) $request->menu);
            if (!$menuActual) {
                abort(404);
            }
        }

        $this->aplicarFiltroStock($query, $menuActual);

        [$filtrosAplicados, $filtrosIncompletos] = $this->aplicarFiltrosCascada($request, $query, $menuActual);

        $productos = $this->paginar($request, $query, $filtrosIncompletos);

        $etiquetas    = Etiqueta::where('visible_usuarios', true)->orderBy('nombre')->get();
        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('tienda.index', compact('productos', 'etiquetas', 'mostrarPrecios', 'menuActual', 'filtrosAplicados', 'filtrosIncompletos'));
    }

    public function catalogo(string $slug, Request $request)
    {
        $menuActual = Menu::where('slug', $slug)->where('activo', true)->firstOrFail();

        $query = Producto::with(['proveedor', 'etiquetas', 'especificaciones', 'moneda'])
            ->visiblesEnTienda();

        // Aplicar el scope base del menú (proveedor, etiqueta, especificación)
        $this->aplicarFiltroTipoMenu($query, $menuActual);

        $this->aplicarFiltroStock($query, $menuActual);

        if ($request->filled('buscar')) {
            $this->aplicarFiltroBusqueda($query, $request->buscar);
        }

        [$filtrosAplicados, $filtrosIncompletos] = $this->aplicarFiltrosCascada($request, $query, $menuActual);

        $productos = $this->paginar($request, $query, $filtrosIncompletos);

        $etiquetas    = Etiqueta::where('visible_usuarios', true)->orderBy('nombre')->get();
        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('tienda.index', compact('productos', 'etiquetas', 'mostrarPrecios', 'menuActual', 'filtrosAplicados', 'filtrosIncompletos'));
    }

    /**
     * Obtener valores disponibles para un filtro en cascada (AJAX)
     */
    public function filtrosValores(Request $request)
    {
        $request->validate([
            'menu_id'     => 'required|integer|exists:menus,id',
            'etiqueta_id' => 'required|integer|exists:etiquetas,id,visible_usuarios,1',
            'filtros'     => 'nullable|array',
        ]);

        $menu      = Menu::findOrFail($request->menu_id);
        $etiquetaId = $request->etiqueta_id;
        $filtros   = $request->input('filtros', []);

        $query = Producto::where('disponible', true);
        $this->aplicarFiltroTipoMenu($query, $menu);
        $this->aplicarFiltroStock($query, $menu);

        foreach ($filtros as $filtroEtiquetaId => $filtroValor) {
            if ($filtroValor && $filtroEtiquetaId != $etiquetaId) {
                $query->whereHas('etiquetas', function ($q) use ($filtroEtiquetaId, $filtroValor) {
                    $q->where('etiquetas.id', $filtroEtiquetaId)
                      ->where('producto_etiqueta.valor', $filtroValor);
                });
            }
        }

        $productoIds = $query->pluck('id');

        $valores = DB::table('producto_etiqueta')
            ->whereIn('producto_id', $productoIds)
            ->where('etiqueta_id', $etiquetaId)
            ->distinct()
            ->orderBy('valor')
            ->pluck('valor');

        return response()->json($valores);
    }

    /**
     * Obtener productos filtrados via AJAX (para filtros en cascada)
     */
    public function productosAjax(Request $request)
    {
        $query = Producto::with(['proveedor', 'etiquetas', 'especificaciones', 'moneda'])
            ->visiblesEnTienda();

        $menuActual = null;
        if ($request->filled('menu_id')) {
            $menuActual = Menu::find($request->menu_id);
            if ($menuActual) {
                $this->aplicarFiltroTipoMenu($query, $menuActual);
                $this->aplicarFiltroStock($query, $menuActual);
            }
        }

        if ($request->filled('proveedor')) {
            $query->where('proveedor_id', $request->proveedor);
        }

        if ($request->filled('etiqueta')) {
            $etiquetaId  = $request->etiqueta;
            $etiquetaValor = $request->etiqueta_valor;
            $query->whereHas('etiquetas', function ($q) use ($etiquetaId, $etiquetaValor) {
                $q->where('etiquetas.id', $etiquetaId);
                if ($etiquetaValor) {
                    $q->where('producto_etiqueta.valor', $etiquetaValor);
                }
            });
        }

        if ($request->filled('especificacion')) {
            $val = $request->especificacion;
            $query->whereHas('especificaciones', function ($q) use ($val) {
                $q->where('valor', 'ilike', "%{$val}%");
            });
        }

        $filtros = $request->input('filtros', []);
        foreach ($filtros as $etiquetaId => $valor) {
            if ($valor) {
                $query->whereHas('etiquetas', function ($q) use ($etiquetaId, $valor) {
                    $q->where('etiquetas.id', $etiquetaId)
                      ->where('producto_etiqueta.valor', $valor);
                });
            }
        }

        if ($request->filled('buscar')) {
            $this->aplicarFiltroBusqueda($query, $request->buscar);
        }

        $productos = $query->orderBy('descripcion')->paginate(12);
        $mostrarPrecios = Configuracion::mostrarPrecios();
        $menuEnSidebar  = Configuracion::menuEnSidebar();

        $html = view('tienda.partials.productos-grid', compact('productos', 'mostrarPrecios', 'menuEnSidebar'))->render();

        return response()->json([
            'html'     => $html,
            'total'    => $productos->total(),
            'hasPages' => $productos->hasPages(),
        ]);
    }

    /**
     * Forma corta /producto/{id}: siempre 301 a la canónica con slug.
     */
    public function showPorId(Producto $producto)
    {
        return $this->redirigirACanonica($producto);
    }

    /**
     * TODO(public_id): URLs viejas /producto/{uuid}, de cuando public_id era la
     * route key. Se elimina junto con la columna public_id.
     *
     * No usa route-model binding porque el binding resuelve por id, no por uuid.
     */
    public function showPorPublicId($publicId)
    {
        $producto = Producto::where('public_id', $publicId)->firstOrFail();

        return $this->redirigirACanonica($producto);
    }

    private function redirigirACanonica(Producto $producto)
    {
        $url = $producto->url();

        // Preservar la query string o se pierde la atribución de campañas (utm_*).
        if ($qs = request()->getQueryString()) {
            $url .= '?' . $qs;
        }

        return redirect($url, 301);
    }

    public function show($slug, Producto $producto)
    {
        // La URL resuelve por el id, así que el slug del primer segmento puede venir
        // viejo o directamente inventado. Si no es el canónico, 301 -- mismo criterio
        // que el redirect de menús legacy en index().
        if ($slug !== $producto->slugUrl()) {
            return $this->redirigirACanonica($producto);
        }

        $producto->load(['proveedor', 'etiquetas', 'especificaciones', 'moneda', 'imagenes']);
        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('tienda.show', compact('producto', 'mostrarPrecios'));
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    private function aplicarFiltroTipoMenu($query, Menu $menu)
    {
        if ($menu->tipo_enlace === Menu::TIPO_PROVEEDOR) {
            $query->where('proveedor_id', $menu->enlace_id);
        } elseif ($menu->tipo_enlace === Menu::TIPO_ETIQUETA) {
            $query->whereHas('etiquetas', function ($q) use ($menu) {
                $q->where('etiquetas.id', $menu->enlace_id);
                if ($menu->enlace_valor) {
                    $q->where('producto_etiqueta.valor', $menu->enlace_valor);
                }
            });
        } elseif ($menu->tipo_enlace === Menu::TIPO_ESPECIFICACION) {
            $val = $menu->enlace_valor;
            $query->whereHas('especificaciones', function ($q) use ($val) {
                $q->where('valor', 'ilike', "%{$val}%");
            });
        }
    }

    private function aplicarFiltroStock($query, ?Menu $menu)
    {
        if (!$menu) return;
        if ($menu->filtro_stock === 'con_stock') {
            $query->where('stock', '>', 0);
        } elseif ($menu->filtro_stock === 'con_stock_y_encargue') {
            $query->where(function ($q) {
                $q->where('stock', '>', 0)->orWhere('por_encargue', true);
            });
        }
    }

    private function aplicarFiltroBusqueda($query, string $buscar)
    {
        $query->where(function ($q) use ($buscar) {
            $q->where('descripcion', 'ilike', "%{$buscar}%")
              ->orWhere('id_proveedor', 'ilike', "%{$buscar}%")
              ->orWhereHas('etiquetas', function ($q2) use ($buscar) {
                  $q2->where('producto_etiqueta.valor', 'ilike', "%{$buscar}%");
              });
        });
    }

    private function aplicarFiltrosCascada(Request $request, $query, ?Menu $menuActual): array
    {
        $filtrosAplicados = [];
        foreach ($request->all() as $key => $value) {
            if (preg_match('/^f(\d+)$/', $key, $matches) && $value) {
                $etiquetaId = (int) $matches[1];
                $filtrosAplicados[$etiquetaId] = $value;
                $query->whereHas('etiquetas', function ($q) use ($etiquetaId, $value) {
                    $q->where('etiquetas.id', $etiquetaId)
                      ->where('producto_etiqueta.valor', $value);
                });
            }
        }

        $filtrosIncompletos = false;
        if ($menuActual && $menuActual->filtros_requeridos && $menuActual->tieneFiltros()) {
            foreach ($menuActual->filtros_etiquetas ?? [] as $etiquetaId) {
                if (empty($filtrosAplicados[$etiquetaId])) {
                    $filtrosIncompletos = true;
                    break;
                }
            }
        }

        return [$filtrosAplicados, $filtrosIncompletos];
    }

    private function paginar(Request $request, $query, bool $filtrosIncompletos)
    {
        if ($filtrosIncompletos) {
            return new LengthAwarePaginator([], 0, 12, 1, [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]);
        }

        $productos = $query->orderBy('descripcion')->paginate(12);

        if ($productos->currentPage() > max($productos->lastPage(), 1)) {
            abort(404);
        }

        return $productos;
    }
}
