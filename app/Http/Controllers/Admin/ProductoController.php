<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\ProductoEspecificacion;
use App\Models\StockMovimiento;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:productos.ver')->only(['index', 'show', 'buscarParaPedido', 'buscarEspecificacionClaves', 'buscarEspecificacionValores', 'historial']);
        $this->middleware('permiso:productos.crear')->only(['create', 'store']);
        $this->middleware('permiso:productos.editar')->only(['edit', 'update', 'ajustarStock']);
        $this->middleware('permiso:productos.eliminar')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Producto::with(['proveedor', 'moneda']);

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('descripcion', 'ilike', "%{$buscar}%")
                  ->orWhere('id_proveedor', 'ilike', "%{$buscar}%");
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
        $proveedores = Proveedor::where('activo', true)->with('etiquetas')->orderBy('nombre')->get();
        $etiquetas = Etiqueta::orderBy('nombre')->get();
        $monedas = Moneda::where('activa', true)->orderBy('nombre')->get();
        $etiquetasObligatorias = $this->mapEtiquetasObligatorias($proveedores);
        $etiquetasAplicables = $this->mapEtiquetasAplicables($proveedores);
        $monedaDefaultId = \App\Models\Configuracion::monedaDefaultId();

        return view('admin.productos.create', compact('proveedores', 'etiquetas', 'monedas', 'etiquetasObligatorias', 'etiquetasAplicables', 'monedaDefaultId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'id_proveedor' => 'nullable|string|max:255',
            'descripcion' => 'required|string|max:255',
            'detalle' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'moneda_id' => 'nullable|exists:monedas,id',
            'disponible' => 'boolean',
            'stock' => 'required|integer|min:0',
            'por_encargue' => 'boolean',
            'imagen_archivo' => 'nullable|image|max:2048',
            'imagen_url' => 'nullable|url|max:500',
            'etiquetas' => 'nullable|array',
            'etiquetas.*.etiqueta_id' => 'nullable|exists:etiquetas,id',
            'etiquetas.*.valor' => 'nullable|string|max:255',
            'especificaciones' => 'nullable|array',
            'especificaciones.*.clave' => 'nullable|string|max:255',
            'especificaciones.*.valor' => 'nullable|string|max:255',
        ]);

        $validated['disponible'] = $request->boolean('disponible');
        $validated['por_encargue'] = $request->boolean('por_encargue');

        // Manejar imagen (archivo tiene prioridad sobre URL)
        if ($request->hasFile('imagen_archivo')) {
            $validated['url_imagen'] = $request->file('imagen_archivo')->store(tenant('id') . '/productos', 'public');
        } elseif ($request->filled('imagen_url')) {
            $validated['url_imagen'] = $request->imagen_url;
        }

        $this->validarEtiquetasObligatorias($request, $validated['proveedor_id']);

        // El stock se gestiona mediante movimientos; creamos el producto con stock=0
        $stockInicial = (int) $validated['stock'];
        $validated['stock'] = 0;

        $producto = Producto::create($validated);

        if ($stockInicial > 0) {
            $producto->registrarMovimiento(
                $stockInicial,
                StockMovimiento::TIPO_AJUSTE_INICIAL,
                'Stock inicial al crear el producto',
                null,
                auth()->id()
            );
        }

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
        $proveedores = Proveedor::where('activo', true)->with('etiquetas')->orderBy('nombre')->get();
        $etiquetas = Etiqueta::orderBy('nombre')->get();
        $monedas = Moneda::where('activa', true)->orderBy('nombre')->get();
        $etiquetasObligatorias = $this->mapEtiquetasObligatorias($proveedores);
        $etiquetasAplicables = $this->mapEtiquetasAplicables($proveedores);

        return view('admin.productos.edit', compact('producto', 'proveedores', 'etiquetas', 'monedas', 'etiquetasObligatorias', 'etiquetasAplicables'));
    }

    public function update(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'id_proveedor' => 'nullable|string|max:255',
            'descripcion' => 'required|string|max:255',
            'detalle' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'moneda_id' => 'nullable|exists:monedas,id',
            'disponible' => 'boolean',
            'por_encargue' => 'boolean',
            'imagen_archivo' => 'nullable|image|max:2048',
            'imagen_url' => 'nullable|url|max:500',
            'eliminar_imagen' => 'boolean',
            'etiquetas' => 'nullable|array',
            'etiquetas.*.etiqueta_id' => 'nullable|exists:etiquetas,id',
            'etiquetas.*.valor' => 'nullable|string|max:255',
            'especificaciones' => 'nullable|array',
            'especificaciones.*.clave' => 'nullable|string|max:255',
            'especificaciones.*.valor' => 'nullable|string|max:255',
        ]);

        $validated['disponible'] = $request->boolean('disponible');
        $validated['por_encargue'] = $request->boolean('por_encargue');
        unset($validated['stock']); // stock se gestiona exclusivamente via movimientos

        // Manejar eliminación de imagen
        if ($request->boolean('eliminar_imagen')) {
            $this->eliminarImagenLocal($producto);
            $validated['url_imagen'] = null;
        }
        // Manejar nueva imagen (archivo tiene prioridad sobre URL)
        elseif ($request->hasFile('imagen_archivo')) {
            $this->eliminarImagenLocal($producto);
            $validated['url_imagen'] = $request->file('imagen_archivo')->store(tenant('id') . '/productos', 'public');
        } elseif ($request->filled('imagen_url')) {
            $this->eliminarImagenLocal($producto);
            $validated['url_imagen'] = $request->imagen_url;
        }

        $this->validarEtiquetasObligatorias($request, $validated['proveedor_id']);

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

        $backParams = $request->input('_back', '');
        $backUrl = route('admin.productos.index') . ($backParams ? '?' . $backParams : '');
        return redirect($backUrl)->with('success', 'Producto actualizado correctamente');
    }

    public function destroy(Producto $producto)
    {
        $this->eliminarImagenLocal($producto);
        $producto->delete();

        return redirect()->route('admin.productos.index')
            ->with('success', 'Producto eliminado correctamente');
    }

    /**
     * Elimina la imagen local si existe (no elimina URLs externas)
     */
    private function eliminarImagenLocal(Producto $producto): void
    {
        if ($producto->url_imagen && strpos($producto->url_imagen, 'http') !== 0) {
            Storage::disk('public')->delete($producto->url_imagen);
        }
    }

    private function mapEtiquetasObligatorias($proveedores)
    {
        $map = [];
        foreach ($proveedores as $prov) {
            $map[$prov->id] = $prov->etiquetas
                ->filter(function ($e) { return $e->pivot->obligatoria; })
                ->map(function ($e) { return ['id' => $e->id, 'nombre' => $e->nombre]; })
                ->values()
                ->toArray();
        }
        return $map;
    }

    private function mapEtiquetasAplicables($proveedores)
    {
        $map = [];
        foreach ($proveedores as $prov) {
            $map[$prov->id] = $prov->etiquetas->filter(function ($e) {
                return $e->pivot->obligatoria !== null;
            })->pluck('id')->values()->toArray();
        }
        return $map;
    }

    private function validarEtiquetasObligatorias(Request $request, $proveedorId)
    {
        $proveedor = Proveedor::with('etiquetasObligatorias')->find($proveedorId);
        if (!$proveedor || $proveedor->etiquetasObligatorias->isEmpty()) return;

        $enviadas = collect($request->input('etiquetas', []))
            ->filter(function ($e) { return !empty($e['etiqueta_id']) && trim($e['valor'] ?? '') !== ''; })
            ->pluck('valor', 'etiqueta_id');

        $faltantes = [];
        foreach ($proveedor->etiquetasObligatorias as $etiqueta) {
            if (!$enviadas->has($etiqueta->id)) {
                $faltantes[] = $etiqueta->nombre;
            }
        }

        if (!empty($faltantes)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'etiquetas' => 'Faltan etiquetas obligatorias para este proveedor: ' . implode(', ', $faltantes) . '.',
            ]);
        }
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

    public function historial(Producto $producto)
    {
        $movimientos = $producto->movimientos()
            ->with(['pedido', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $totales = [
            'entradas' => $producto->movimientos()->where('variacion', '>', 0)->sum('variacion'),
            'salidas'  => abs($producto->movimientos()->where('variacion', '<', 0)->sum('variacion')),
        ];

        return view('admin.productos.historial', compact('producto', 'movimientos', 'totales'));
    }

    public function ajustarStock(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'variacion'   => 'required|integer|not_in:0',
            'descripcion' => 'nullable|string|max:255',
        ]);

        if ($producto->stock + $validated['variacion'] < 0) {
            return back()->withErrors([
                'variacion' => 'El ajuste resultaría en stock negativo (' . ($producto->stock + $validated['variacion']) . ').',
            ]);
        }

        $producto->registrarMovimiento(
            $validated['variacion'],
            StockMovimiento::TIPO_AJUSTE_MANUAL,
            $validated['descripcion'] ?: null,
            null,
            auth()->id()
        );

        $destino = $request->input('_redirect') === 'historial'
            ? route('admin.productos.historial', $producto)
            : route('admin.productos.edit', $producto);

        return redirect($destino)
            ->with('success', 'Stock ajustado correctamente. Stock actual: ' . $producto->stock);
    }

    public function buscarEspecificacionClaves(Request $request)
    {
        $buscar = $request->get('q', '');

        $claves = ProductoEspecificacion::where('clave', 'ilike', "%{$buscar}%")
            ->distinct()
            ->pluck('clave')
            ->take(10);

        return response()->json($claves);
    }

    public function buscarEspecificacionValores(Request $request)
    {
        $buscar = $request->get('q', '');
        $clave = $request->get('clave', '');

        $query = ProductoEspecificacion::where('valor', 'ilike', "%{$buscar}%");

        if (!empty($clave)) {
            $query->where('clave', $clave);
        }

        $valores = $query->distinct()
            ->pluck('valor')
            ->take(10);

        return response()->json($valores);
    }

    public function buscarParaPedido(Request $request)
    {
        $q = $request->get('q', '');

        $productos = Producto::where('disponible', true)
            ->where(function ($query) use ($q) {
                $query->where('descripcion', 'ilike', "%{$q}%")
                      ->orWhere('id_proveedor', 'ilike', "%{$q}%");
            })
            ->select('id', 'descripcion', 'id_proveedor', 'precio', 'stock', 'por_encargue')
            ->orderBy('descripcion')
            ->limit(20)
            ->get();

        return response()->json($productos->map(function ($p) {
            return [
                'id'          => $p->id,
                'descripcion' => $p->descripcion,
                'codigo'      => $p->id_proveedor,
                'precio'      => $p->precio,
                'stock'       => $p->por_encargue ? null : $p->stock,
            ];
        }));
    }
}
