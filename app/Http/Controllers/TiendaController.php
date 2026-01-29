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
        $query = Producto::with(['proveedor', 'etiquetas', 'especificaciones', 'moneda'])
            ->where('disponible', true);

        // Filtro por disponibilidad de stock
        $mostrarProductosSinStock = Configuracion::mostrarProductosSinStock();
        if (!$mostrarProductosSinStock) {
            $query->where(function ($q) {
                $q->where('stock', '>', 0)
                  ->orWhere('por_encargue', true);
            });
        }

        // Filtro por búsqueda
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('descripcion', 'like', "%{$buscar}%")
                  ->orWhere('id_proveedor', 'like', "%{$buscar}%");
            });
        }

        // Filtro por etiqueta (con valor opcional)
        if ($request->filled('etiqueta')) {
            $etiquetaId = $request->etiqueta;
            $etiquetaValor = $request->etiqueta_valor;

            $query->whereHas('etiquetas', function ($q) use ($etiquetaId, $etiquetaValor) {
                $q->where('etiquetas.id', $etiquetaId);
                if ($etiquetaValor) {
                    $q->where('producto_etiqueta.valor', $etiquetaValor);
                }
            });
        }

        // Filtro por proveedor
        if ($request->filled('proveedor')) {
            $query->where('proveedor_id', $request->proveedor);
        }

        // Filtro por especificación (desde menú dinámico)
        if ($request->filled('especificacion')) {
            $especificacionValor = $request->especificacion;
            $query->whereHas('especificaciones', function ($q) use ($especificacionValor) {
                $q->where('valor', 'like', "%{$especificacionValor}%");
            });
        }

        // Filtros en cascada desde menú
        $menuActual = null;
        if ($request->filled('menu')) {
            $menuActual = Menu::find($request->menu);
        }

        // Aplicar filtros de etiquetas en cascada (f1, f2, f3, etc.)
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

        // Verificar si el menú requiere todos los filtros completos
        $filtrosIncompletos = false;
        if ($menuActual && $menuActual->filtros_requeridos && $menuActual->tieneFiltros()) {
            $etiquetasFiltro = $menuActual->filtros_etiquetas ?? [];
            foreach ($etiquetasFiltro as $etiquetaId) {
                if (!isset($filtrosAplicados[$etiquetaId]) || empty($filtrosAplicados[$etiquetaId])) {
                    $filtrosIncompletos = true;
                    break;
                }
            }
        }

        // Si los filtros son requeridos y están incompletos, no mostrar productos
        if ($filtrosIncompletos) {
            $productos = new LengthAwarePaginator([], 0, 12, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        } else {
            $productos = $query->orderBy('descripcion')->paginate(12);
        }

        $etiquetas = Etiqueta::where('visible_usuarios', true)->orderBy('nombre')->get();
        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('tienda.index', compact('productos', 'etiquetas', 'mostrarPrecios', 'menuActual', 'filtrosAplicados', 'filtrosIncompletos'));
    }

    /**
     * Obtener valores disponibles para un filtro en cascada (AJAX)
     */
    public function filtrosValores(Request $request)
    {
        $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'etiqueta_id' => 'required|exists:etiquetas,id',
            'filtros' => 'nullable|array',
        ]);

        $menu = Menu::findOrFail($request->menu_id);
        $etiquetaId = $request->etiqueta_id;
        $filtros = $request->input('filtros', []);

        // Construir query base según el tipo de menú
        $query = Producto::where('disponible', true);

        if ($menu->tipo_enlace === Menu::TIPO_PROVEEDOR) {
            $query->where('proveedor_id', $menu->enlace_id);
        } elseif ($menu->tipo_enlace === Menu::TIPO_ETIQUETA) {
            $query->whereHas('etiquetas', function ($q) use ($menu) {
                $q->where('etiquetas.id', $menu->enlace_id);
                if ($menu->enlace_valor) {
                    $q->where('producto_etiqueta.valor', $menu->enlace_valor);
                }
            });
        }

        // Aplicar filtros anteriores en la cascada
        foreach ($filtros as $filtroEtiquetaId => $filtroValor) {
            if ($filtroValor && $filtroEtiquetaId != $etiquetaId) {
                $query->whereHas('etiquetas', function ($q) use ($filtroEtiquetaId, $filtroValor) {
                    $q->where('etiquetas.id', $filtroEtiquetaId)
                      ->where('producto_etiqueta.valor', $filtroValor);
                });
            }
        }

        // Obtener IDs de productos que cumplen los filtros
        $productoIds = $query->pluck('id');

        // Obtener valores disponibles para la etiqueta solicitada
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
            ->where('disponible', true);

        // Filtro por disponibilidad de stock
        $mostrarProductosSinStock = Configuracion::mostrarProductosSinStock();
        if (!$mostrarProductosSinStock) {
            $query->where(function ($q) {
                $q->where('stock', '>', 0)
                  ->orWhere('por_encargue', true);
            });
        }

        // Filtro por menú
        $menuActual = null;
        if ($request->filled('menu_id')) {
            $menuActual = Menu::find($request->menu_id);

            if ($menuActual) {
                if ($menuActual->tipo_enlace === Menu::TIPO_PROVEEDOR) {
                    $query->where('proveedor_id', $menuActual->enlace_id);
                } elseif ($menuActual->tipo_enlace === Menu::TIPO_ETIQUETA) {
                    $query->whereHas('etiquetas', function ($q) use ($menuActual) {
                        $q->where('etiquetas.id', $menuActual->enlace_id);
                        if ($menuActual->enlace_valor) {
                            $q->where('producto_etiqueta.valor', $menuActual->enlace_valor);
                        }
                    });
                }
            }
        }

        // Filtro por proveedor (directo)
        if ($request->filled('proveedor')) {
            $query->where('proveedor_id', $request->proveedor);
        }

        // Filtro por etiqueta (directo)
        if ($request->filled('etiqueta')) {
            $etiquetaId = $request->etiqueta;
            $etiquetaValor = $request->etiqueta_valor;
            $query->whereHas('etiquetas', function ($q) use ($etiquetaId, $etiquetaValor) {
                $q->where('etiquetas.id', $etiquetaId);
                if ($etiquetaValor) {
                    $q->where('producto_etiqueta.valor', $etiquetaValor);
                }
            });
        }

        // Filtro por especificación
        if ($request->filled('especificacion')) {
            $especificacionValor = $request->especificacion;
            $query->whereHas('especificaciones', function ($q) use ($especificacionValor) {
                $q->where('valor', 'like', "%{$especificacionValor}%");
            });
        }

        // Aplicar filtros de etiquetas en cascada
        $filtros = $request->input('filtros', []);
        foreach ($filtros as $etiquetaId => $valor) {
            if ($valor) {
                $query->whereHas('etiquetas', function ($q) use ($etiquetaId, $valor) {
                    $q->where('etiquetas.id', $etiquetaId)
                      ->where('producto_etiqueta.valor', $valor);
                });
            }
        }

        // Filtro por búsqueda (dentro de productos ya filtrados)
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('descripcion', 'like', "%{$buscar}%")
                  ->orWhere('id_proveedor', 'like', "%{$buscar}%");
            });
        }

        $productos = $query->orderBy('descripcion')->paginate(12);
        $mostrarPrecios = Configuracion::mostrarPrecios();
        $menuEnSidebar = Configuracion::menuEnSidebar();

        // Retornar HTML parcial
        $html = view('tienda.partials.productos-grid', compact('productos', 'mostrarPrecios', 'menuEnSidebar'))->render();

        return response()->json([
            'html' => $html,
            'total' => $productos->total(),
            'hasPages' => $productos->hasPages(),
        ]);
    }

    public function show(Producto $producto)
    {
        $producto->load(['proveedor', 'etiquetas', 'especificaciones', 'moneda']);
        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('tienda.show', compact('producto', 'mostrarPrecios'));
    }
}
