<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\ProductoEspecificacion;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $query = Producto::with(['proveedor', 'etiquetas', 'especificaciones', 'moneda']);

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('descripcion', 'like', "%{$buscar}%")
                  ->orWhere('id_proveedor', 'like', "%{$buscar}%");
            });
        }

        if ($request->filled('proveedor')) {
            $query->where('proveedor_id', $request->proveedor);
        }

        if ($request->filled('disponible')) {
            $query->where('disponible', $request->disponible);
        }

        $productos = $query->orderBy('descripcion')->paginate(15);
        $proveedores = Proveedor::where('activo', true)->orderBy('nombre')->get();

        return view('admin.productos.index', compact('productos', 'proveedores'));
    }

    public function create()
    {
        $proveedores = Proveedor::where('activo', true)->orderBy('nombre')->get();
        $etiquetas = Etiqueta::orderBy('nombre')->get();
        $monedas = Moneda::where('activa', true)->orderBy('nombre')->get();

        return view('admin.productos.create', compact('proveedores', 'etiquetas', 'monedas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'id_proveedor' => 'nullable|string|max:255',
            'descripcion' => 'required|string|max:255',
            'precio' => 'required|numeric|min:0',
            'moneda_id' => 'nullable|exists:monedas,id',
            'disponible' => 'boolean',
            'stock' => 'required|integer|min:0',
            'por_encargue' => 'boolean',
            'url_imagen' => 'nullable|image|max:2048',
            'etiquetas' => 'nullable|array',
            'etiquetas.*.etiqueta_id' => 'required|exists:etiquetas,id',
            'etiquetas.*.valor' => 'required|string|max:255',
            'especificaciones' => 'nullable|array',
            'especificaciones.*.clave' => 'nullable|string|max:255',
            'especificaciones.*.valor' => 'nullable|string|max:255',
        ]);

        $validated['disponible'] = $request->boolean('disponible');
        $validated['por_encargue'] = $request->boolean('por_encargue');

        // Manejar imagen
        if ($request->hasFile('url_imagen')) {
            $validated['url_imagen'] = $request->file('url_imagen')->store('productos', 'public');
        }

        $producto = Producto::create($validated);

        // Asignar etiquetas con valores
        if ($request->filled('etiquetas')) {
            $this->sincronizarEtiquetas($producto, $request->etiquetas);
        }

        // Crear especificaciones
        if ($request->filled('especificaciones')) {
            foreach ($request->especificaciones as $espec) {
                if (!empty($espec['clave']) && !empty($espec['valor'])) {
                    ProductoEspecificacion::create([
                        'producto_id' => $producto->id,
                        'clave' => $espec['clave'],
                        'valor' => $espec['valor'],
                    ]);
                }
            }
        }

        return redirect()->route('admin.productos.index')
            ->with('success', 'Producto creado correctamente');
    }

    public function edit(Producto $producto)
    {
        $producto->load(['etiquetas', 'especificaciones']);
        $proveedores = Proveedor::where('activo', true)->orderBy('nombre')->get();
        $etiquetas = Etiqueta::orderBy('nombre')->get();
        $monedas = Moneda::where('activa', true)->orderBy('nombre')->get();

        return view('admin.productos.edit', compact('producto', 'proveedores', 'etiquetas', 'monedas'));
    }

    public function update(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'id_proveedor' => 'nullable|string|max:255',
            'descripcion' => 'required|string|max:255',
            'precio' => 'required|numeric|min:0',
            'moneda_id' => 'nullable|exists:monedas,id',
            'disponible' => 'boolean',
            'stock' => 'required|integer|min:0',
            'por_encargue' => 'boolean',
            'url_imagen' => 'nullable|image|max:2048',
            'etiquetas' => 'nullable|array',
            'etiquetas.*.etiqueta_id' => 'required|exists:etiquetas,id',
            'etiquetas.*.valor' => 'required|string|max:255',
            'especificaciones' => 'nullable|array',
            'especificaciones.*.clave' => 'nullable|string|max:255',
            'especificaciones.*.valor' => 'nullable|string|max:255',
        ]);

        $validated['disponible'] = $request->boolean('disponible');
        $validated['por_encargue'] = $request->boolean('por_encargue');

        // Manejar imagen
        if ($request->hasFile('url_imagen')) {
            // Eliminar imagen anterior
            if ($producto->url_imagen) {
                Storage::disk('public')->delete($producto->url_imagen);
            }
            $validated['url_imagen'] = $request->file('url_imagen')->store('productos', 'public');
        }

        $producto->update($validated);

        // Sincronizar etiquetas con valores
        $this->sincronizarEtiquetas($producto, $request->input('etiquetas', []));

        // Actualizar especificaciones
        $producto->especificaciones()->delete();
        if ($request->filled('especificaciones')) {
            foreach ($request->especificaciones as $espec) {
                if (!empty($espec['clave']) && !empty($espec['valor'])) {
                    ProductoEspecificacion::create([
                        'producto_id' => $producto->id,
                        'clave' => $espec['clave'],
                        'valor' => $espec['valor'],
                    ]);
                }
            }
        }

        return redirect()->route('admin.productos.index')
            ->with('success', 'Producto actualizado correctamente');
    }

    public function destroy(Producto $producto)
    {
        // Eliminar imagen
        if ($producto->url_imagen) {
            Storage::disk('public')->delete($producto->url_imagen);
        }

        $producto->delete();

        return redirect()->route('admin.productos.index')
            ->with('success', 'Producto eliminado correctamente');
    }

    private function sincronizarEtiquetas(Producto $producto, array $etiquetas)
    {
        $syncData = [];

        foreach ($etiquetas as $etiquetaData) {
            if (!empty($etiquetaData['etiqueta_id']) && !empty($etiquetaData['valor'])) {
                $syncData[$etiquetaData['etiqueta_id']] = ['valor' => $etiquetaData['valor']];
            }
        }

        $producto->etiquetas()->sync($syncData);
    }

    public function buscarEspecificacionClaves(Request $request)
    {
        $buscar = $request->get('q', '');

        $claves = ProductoEspecificacion::where('clave', 'like', "%{$buscar}%")
            ->distinct()
            ->pluck('clave')
            ->take(10);

        return response()->json($claves);
    }

    public function buscarEspecificacionValores(Request $request)
    {
        $buscar = $request->get('q', '');
        $clave = $request->get('clave', '');

        $query = ProductoEspecificacion::where('valor', 'like', "%{$buscar}%");

        if (!empty($clave)) {
            $query->where('clave', $clave);
        }

        $valores = $query->distinct()
            ->pluck('valor')
            ->take(10);

        return response()->json($valores);
    }
}
