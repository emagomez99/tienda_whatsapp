<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Etiqueta;
use App\Models\Producto;
use Illuminate\Http\Request;

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

        // Filtro por etiqueta (legacy)
        if ($request->filled('etiqueta')) {
            $query->whereHas('etiquetas', function ($q) use ($request) {
                $q->where('etiquetas.id', $request->etiqueta);
            });
        }

        // Filtro por proveedor
        if ($request->filled('proveedor')) {
            $query->where('proveedor_id', $request->proveedor);
        }

        // Filtro por categoría (desde menú dinámico)
        if ($request->filled('categoria')) {
            $categoriaId = $request->categoria;
            $categoriaValor = $request->categoria_valor;

            $query->whereHas('etiquetas', function ($q) use ($categoriaId, $categoriaValor) {
                $q->where('etiquetas.id', $categoriaId);
                if ($categoriaValor) {
                    $q->where('producto_etiqueta.valor', $categoriaValor);
                }
            });
        }

        // Filtro por especificación (desde menú dinámico)
        if ($request->filled('especificacion')) {
            $especificacionValor = $request->especificacion;
            $query->whereHas('especificaciones', function ($q) use ($especificacionValor) {
                $q->where('valor', 'like', "%{$especificacionValor}%");
            });
        }

        $productos = $query->orderBy('descripcion')->paginate(12);
        $etiquetas = Etiqueta::where('visible_usuarios', true)->orderBy('nombre')->get();
        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('tienda.index', compact('productos', 'etiquetas', 'mostrarPrecios'));
    }

    public function show(Producto $producto)
    {
        $producto->load(['proveedor', 'etiquetas', 'especificaciones', 'moneda']);
        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('tienda.show', compact('producto', 'mostrarPrecios'));
    }
}
