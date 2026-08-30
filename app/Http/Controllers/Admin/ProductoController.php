<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\ProductoEspecificacion;
use App\Models\ProductoImagen;
use App\Models\StockMovimiento;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    /**
     * Tope de sugerencias en los desplegables del formulario. Con 50 se pueden
     * recorrer con la vista las etiquetas que tienen pocos valores (Fabricante
     * tiene 11) sin traer las que tienen miles (Modelo tiene 2.297), donde de
     * todos modos hay que escribir para encontrar algo.
     */
    const MAX_SUGERENCIAS = 50;

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

        if ($request->filled('stock')) {
            if ($request->stock === 'con') {
                $query->where('stock', '>', 0);
            } elseif ($request->stock === 'sin') {
                $query->where(function ($q) {
                    $q->where('stock', 0)->orWhereNull('stock');
                });
            }
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
        $imagenesAdicionalesActivas = Configuracion::imagenesAdicionalesActivas();
        $maxImagenesAdicionales     = Configuracion::maxImagenesAdicionales();

        return view('admin.productos.create', compact(
            'proveedores', 'etiquetas', 'monedas',
            'etiquetasObligatorias', 'etiquetasAplicables', 'monedaDefaultId',
            'imagenesAdicionalesActivas', 'maxImagenesAdicionales'
        ));
    }

    public function store(Request $request)
    {
        // Solo lowercase -- sin transliteración, para que el regex rechace chars inválidos
        if ($request->filled('slug')) {
            $request->merge(['slug' => strtolower(trim($request->slug))]);
        }

        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'id_proveedor' => 'nullable|string|max:255',
            'descripcion' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|regex:/^[a-z0-9-]+$/',
            'detalle' => 'nullable|string',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'precio' => 'required|numeric|min:0',
            'moneda_id' => 'nullable|exists:monedas,id',
            'disponible' => 'boolean',
            'stock' => 'required|integer|min:0',
            'por_encargue' => 'boolean',
            'imagen_archivo'         => 'nullable|image|max:2048',
            'imagen_url'             => 'nullable|url|max:500',
            'imagenes_nuevas'        => 'nullable|array',
            'imagenes_nuevas.*'      => 'image|max:2048',
            'imagenes_urls_nuevas'   => 'nullable|array',
            'imagenes_urls_nuevas.*' => 'nullable|url|max:2048',
            'etiquetas' => 'nullable|array',
            'etiquetas.*.etiqueta_id' => 'nullable|exists:etiquetas,id',
            'etiquetas.*.valor' => 'nullable|string|max:255',
            'especificaciones' => 'nullable|array',
            'especificaciones.*.clave' => 'nullable|string|max:255',
            'especificaciones.*.valor' => 'nullable|string|max:255',
        ], [
            'slug.regex' => 'La dirección web solo puede tener letras minúsculas, números y guiones.',
        ]);

        // Switch "Generar automáticamente" prendido (o ausente, ej. una API externa):
        // ignoramos cualquier slug que haya llegado y dejamos que el boot() del modelo
        // lo arme solo a partir de la descripción.
        if ($request->boolean('autogenerar_slug', true)) {
            unset($validated['slug']);
        }

        $validated['disponible'] = $request->boolean('disponible');
        $validated['por_encargue'] = $request->boolean('por_encargue');

        if (!empty($validated['detalle'])) {
            $validated['detalle'] = strip_tags($validated['detalle'], '<p><br><b><strong><i><em><u><ul><ol><li><a><h1><h2><h3><h4><blockquote><pre><code><img><table><thead><tbody><tr><th><td>');
        }

        // Imagen principal (archivo tiene prioridad sobre URL)
        if ($request->hasFile('imagen_archivo')) {
            $validated['url_imagen'] = $request->file('imagen_archivo')->store(tenant('id') . '/productos', 'public');
        } elseif ($request->filled('imagen_url')) {
            $validated['url_imagen'] = $request->imagen_url;
        }
        unset($validated['imagen_archivo'], $validated['imagen_url']);

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

        // Imágenes adicionales
        if (Configuracion::imagenesAdicionalesActivas()) {
            $max = Configuracion::maxImagenesAdicionales();
            $actuales = 0;

            if ($request->hasFile('imagenes_nuevas')) {
                foreach ($request->file('imagenes_nuevas') as $archivo) {
                    if ($actuales >= $max) break;
                    ProductoImagen::create([
                        'producto_id' => $producto->id,
                        'url'         => $archivo->store(tenant('id') . '/productos', 'public'),
                        'orden'       => 0,
                    ]);
                    $actuales++;
                }
            }

            if (!empty($validated['imagenes_urls_nuevas'])) {
                foreach ($validated['imagenes_urls_nuevas'] as $urlNueva) {
                    if (!$urlNueva || $actuales >= $max) continue;
                    ProductoImagen::create([
                        'producto_id' => $producto->id,
                        'url'         => $urlNueva,
                        'orden'       => 0,
                    ]);
                    $actuales++;
                }
            }
        }

        return redirect()->route('admin.productos.index')
            ->with('success', 'Producto creado correctamente');
    }

    public function edit(Producto $producto)
    {
        $producto->load(['etiquetas', 'especificaciones', 'imagenes']);
        $proveedores = Proveedor::where('activo', true)->with('etiquetas')->orderBy('nombre')->get();
        $etiquetas = Etiqueta::orderBy('nombre')->get();
        $monedas = Moneda::where('activa', true)->orderBy('nombre')->get();
        $etiquetasObligatorias = $this->mapEtiquetasObligatorias($proveedores);
        $etiquetasAplicables = $this->mapEtiquetasAplicables($proveedores);
        $imagenesAdicionalesActivas = Configuracion::imagenesAdicionalesActivas();
        $maxImagenesAdicionales     = Configuracion::maxImagenesAdicionales();

        return view('admin.productos.edit', compact(
            'producto', 'proveedores', 'etiquetas', 'monedas',
            'etiquetasObligatorias', 'etiquetasAplicables',
            'imagenesAdicionalesActivas', 'maxImagenesAdicionales'
        ));
    }

    public function update(Request $request, Producto $producto)
    {
        // Solo lowercase -- sin transliteración, para que el regex rechace chars inválidos
        if ($request->filled('slug')) {
            $request->merge(['slug' => strtolower(trim($request->slug))]);
        }

        $validated = $request->validate([
            'proveedor_id'         => 'required|exists:proveedores,id',
            'id_proveedor'         => 'nullable|string|max:255',
            'descripcion'          => 'required|string|max:255',
            'slug'                 => 'nullable|string|max:100|regex:/^[a-z0-9-]+$/',
            'detalle'              => 'nullable|string',
            'meta_title'           => 'nullable|string|max:60',
            'meta_description'     => 'nullable|string|max:160',
            'precio'               => 'required|numeric|min:0',
            'moneda_id'            => 'nullable|exists:monedas,id',
            'disponible'           => 'boolean',
            'por_encargue'         => 'boolean',
            'imagen_archivo'       => 'nullable|image|max:2048',
            'imagen_url'           => 'nullable|url|max:500',
            'eliminar_imagen'      => 'nullable|boolean',
            'hacer_portada_id'     => 'nullable|integer|exists:producto_imagenes,id',
            'imagenes_nuevas'      => 'nullable|array',
            'imagenes_nuevas.*'    => 'image|max:2048',
            'imagen_url_nueva'       => 'nullable|url|max:500',
            'imagenes_urls_nuevas'   => 'nullable|array',
            'imagenes_urls_nuevas.*' => 'nullable|url|max:2048',
            'imagenes_eliminar'    => 'nullable|array',
            'imagenes_eliminar.*'  => 'integer',
            'etiquetas'            => 'nullable|array',
            'etiquetas.*.etiqueta_id' => 'nullable|exists:etiquetas,id',
            'etiquetas.*.valor'    => 'nullable|string|max:255',
            'especificaciones'     => 'nullable|array',
            'especificaciones.*.clave' => 'nullable|string|max:255',
            'especificaciones.*.valor' => 'nullable|string|max:255',
        ], [
            'slug.regex' => 'La dirección web solo puede tener letras minúsculas, números y guiones.',
        ]);

        // Cambiar el slug es seguro: la URL resuelve por id, así que las direcciones
        // ya publicadas siguen funcionando (redirigen 301 a la nueva).
        if ($request->boolean('autogenerar_slug')) {
            $validated['slug'] = Producto::generarSlug($validated['descripcion']);
        } elseif (empty($validated['slug'])) {
            $validated['slug'] = $producto->slug ?: Producto::generarSlug($validated['descripcion']);
        }

        $validated['disponible']   = $request->boolean('disponible');
        $validated['por_encargue'] = $request->boolean('por_encargue');
        unset($validated['stock']);

        if (!empty($validated['detalle'])) {
            $validated['detalle'] = strip_tags($validated['detalle'], '<p><br><b><strong><i><em><u><ul><ol><li><a><h1><h2><h3><h4><blockquote><pre><code><img><table><thead><tbody><tr><th><td>');
        }

        // --- Imagen principal ---
        if ($request->boolean('eliminar_imagen')) {
            $this->eliminarImagenLocal($producto);
            $validated['url_imagen'] = null;
        } elseif ($request->hasFile('imagen_archivo')) {
            $this->eliminarImagenLocal($producto);
            $validated['url_imagen'] = $request->file('imagen_archivo')->store(tenant('id') . '/productos', 'public');
        } elseif ($request->filled('imagen_url')) {
            $this->eliminarImagenLocal($producto);
            $validated['url_imagen'] = $request->imagen_url;
        }

        // Promover imagen adicional a principal (swap)
        if ($request->filled('hacer_portada_id') && !$request->hasFile('imagen_archivo') && !$request->filled('imagen_url') && !$request->boolean('eliminar_imagen')) {
            $nueva = ProductoImagen::where('id', $request->hacer_portada_id)
                ->where('producto_id', $producto->id)
                ->first();
            if ($nueva) {
                if ($producto->url_imagen) {
                    ProductoImagen::create(['producto_id' => $producto->id, 'url' => $producto->url_imagen, 'orden' => 0]);
                }
                $validated['url_imagen'] = $nueva->url;
                $nueva->delete();
            }
        }

        // --- Imágenes adicionales ---
        if (!empty($validated['imagenes_eliminar'])) {
            $aEliminar = ProductoImagen::where('producto_id', $producto->id)
                ->whereIn('id', $validated['imagenes_eliminar'])->get();
            foreach ($aEliminar as $img) {
                if (!$img->esExterna()) {
                    Storage::disk('public')->delete($img->url);
                }
                $img->delete();
            }
        }

        if (Configuracion::imagenesAdicionalesActivas()) {
            $max = Configuracion::maxImagenesAdicionales();
            $actuales = $producto->imagenes()->count();

            if ($request->hasFile('imagenes_nuevas')) {
                foreach ($request->file('imagenes_nuevas') as $archivo) {
                    if ($actuales >= $max) break;
                    ProductoImagen::create([
                        'producto_id' => $producto->id,
                        'url'         => $archivo->store(tenant('id') . '/productos', 'public'),
                        'orden'       => 0,
                    ]);
                    $actuales++;
                }
            }

            if ($request->filled('imagen_url_nueva') && $actuales < $max) {
                ProductoImagen::create([
                    'producto_id' => $producto->id,
                    'url'         => $request->imagen_url_nueva,
                    'orden'       => 0,
                ]);
                $actuales++;
            }

            if (!empty($validated['imagenes_urls_nuevas'])) {
                foreach ($validated['imagenes_urls_nuevas'] as $urlNueva) {
                    if (!$urlNueva || $actuales >= $max) continue;
                    ProductoImagen::create([
                        'producto_id' => $producto->id,
                        'url'         => $urlNueva,
                        'orden'       => 0,
                    ]);
                    $actuales++;
                }
            }
        }

        unset($validated['imagen_archivo'], $validated['imagen_url'], $validated['eliminar_imagen'],
              $validated['hacer_portada_id'], $validated['imagenes_nuevas'],
              $validated['imagen_url_nueva'], $validated['imagenes_urls_nuevas'], $validated['imagenes_eliminar']);

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
        foreach ($producto->imagenes()->where('url', 'not ilike', 'http%')->get() as $img) {
            Storage::disk('public')->delete($img->url);
        }
        $producto->delete();

        return redirect()->route('admin.productos.index')
            ->with('success', 'Producto eliminado correctamente');
    }

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
        // El formulario pide tipo (ingreso/egreso) y una cantidad siempre positiva;
        // el signo se resuelve acá. Antes se cargaba la variación con signo, lo que
        // obligaba al usuario a traducir su intención a una convención de signos.
        // El motivo es obligatorio: el historial de stock es un registro de auditoría
        // y un movimiento sin explicación no se puede reconstruir después.
        $validated = $request->validate([
            'tipo'        => 'required|in:ingreso,egreso',
            'cantidad'    => 'required|integer|min:1',
            'descripcion' => 'required|string|max:255',
        ], [
            'cantidad.min'        => 'La cantidad tiene que ser mayor a cero.',
            'cantidad.required'   => 'Indicá la cantidad a ajustar.',
            'tipo.required'       => 'Elegí si es un ingreso o un egreso.',
            'descripcion.required' => 'Indicá el motivo del ajuste.',
        ]);

        $variacion = $validated['tipo'] === 'egreso'
            ? -$validated['cantidad']
            : $validated['cantidad'];

        if ($producto->stock + $variacion < 0) {
            return back()->withErrors([
                'cantidad' => 'No se puede egresar ' . $validated['cantidad'] . ': el stock actual es ' . $producto->stock . '.',
            ])->withInput();
        }

        $producto->registrarMovimiento(
            $variacion,
            StockMovimiento::TIPO_AJUSTE_MANUAL,
            $validated['descripcion'],
            null,
            auth()->id()
        );

        $destino = $request->input('_redirect') === 'historial'
            ? route('admin.productos.historial', $producto)
            : route('admin.productos.edit', $producto);

        return redirect($destino)
            ->with('success', 'Stock ajustado correctamente. Stock actual: ' . $producto->stock);
    }

    /**
     * Vista previa de la dirección web a partir del nombre del producto.
     *
     * Existe para que el formulario no tenga que reimplementar Str::slug() en JS:
     * una transliteración propia diverge de la de PHP en los símbolos poco comunes
     * (Ø, €, …) y terminaría mostrando una dirección distinta de la que se guarda.
     */
    public function previewSlug(Request $request)
    {
        return response()->json([
            'slug' => Producto::generarSlug($request->get('descripcion', '')),
        ]);
    }

    /**
     * Claves de especificación ya usadas, para el desplegable del formulario.
     *
     * El corte va en la consulta y no con ->take() sobre la colección: así no se
     * traen todas las claves distintas de la base para descartar casi todas en PHP.
     */
    public function buscarEspecificacionClaves(Request $request)
    {
        $buscar = trim($request->get('q', ''));

        $query = ProductoEspecificacion::query()->select('clave')->distinct();

        if ($buscar !== '') {
            $query->where('clave', 'ilike', "%{$buscar}%");
        }

        $claves = $query->orderBy('clave')->limit(self::MAX_SUGERENCIAS)->pluck('clave');

        return response()->json($claves);
    }

    public function buscarEspecificacionValores(Request $request)
    {
        $buscar = trim($request->get('q', ''));
        $clave  = trim($request->get('clave', ''));

        $query = ProductoEspecificacion::query()->select('valor')->distinct();

        if ($buscar !== '') {
            $query->where('valor', 'ilike', "%{$buscar}%");
        }

        if ($clave !== '') {
            $query->where('clave', $clave);
        }

        $valores = $query->orderBy('valor')->limit(self::MAX_SUGERENCIAS)->pluck('valor');

        return response()->json($valores);
    }

    public function buscarParaPedido(Request $request)
    {
        $q = $request->get('q', '');

        $productos = Producto::with('moneda')
            ->where('disponible', true)
            ->where(function ($query) use ($q) {
                $query->where('descripcion', 'ilike', "%{$q}%")
                      ->orWhere('id_proveedor', 'ilike', "%{$q}%");
            })
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
                'moneda_id'   => $p->moneda_id,
                'simbolo'     => $p->moneda ? $p->moneda->simbolo : '$',
            ];
        }));
    }
}
