<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\ProductoEspecificacion;
use App\Models\StockMovimiento;
use App\Models\Proveedor;
use App\Models\Variante;
use App\Services\AsignacionDeCategorias;
use App\Services\CaracteristicasPorCategoria;
use App\Services\GaleriaProducto;
use App\Services\GuardadoDeProducto;
use App\Support\DatosDeProducto;
use App\Support\FiltroDeProductos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Support\Paginas;

class ProductoController extends Controller
{
    /**
     * Tope de sugerencias en los desplegables del formulario. Con 50 se pueden
     * recorrer con la vista las etiquetas que tienen pocos valores (Fabricante
     * tiene 11) sin traer las que tienen miles (Modelo tiene 2.297), donde de
     * todos modos hay que escribir para encontrar algo.
     */
    const MAX_SUGERENCIAS = 50;

    /** @var GuardadoDeProducto */
    private $guardado;

    public function __construct(GuardadoDeProducto $guardado)
    {
        $this->guardado = $guardado;

        $this->middleware('permiso:productos.ver')->only(['index', 'show', 'buscarParaPedido', 'buscarEspecificacionClaves', 'buscarEspecificacionValores', 'historial']);
        $this->middleware('permiso:productos.crear')->only(['create', 'store']);
        $this->middleware('permiso:productos.editar')->only(['edit', 'update', 'ajustarStock', 'categoriasEnMasa']);
        $this->middleware('permiso:productos.eliminar')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $filtro = new FiltroDeProductos($request->query());
        $productos = Paginas::enRango($filtro->consulta()
            ->with(['proveedor', 'moneda', 'categorias:categorias.id'])
            ->withCount('variantes')
            ->orderBy('descripcion')
            // Muchos productos pueden llamarse igual: sin esto, al pasar de página
            // alguno podía repetirse o no aparecer nunca.
            ->orderBy('productos.id')
            ->paginate(15)
            ->withQueryString());
        // Para filtrar, todos: los productos de un proveedor inactivo siguen estando.
        $proveedores = Proveedor::orderBy('nombre')->get();

        // Los caminos se arman una vez con el árbol en memoria: con el de cada
        // producto serían varias consultas por fila.
        $categorias = Categoria::enOrdenDeArbol();
        $caminos = $categorias->mapWithKeys(function (Categoria $c) { return [$c->id => $c->camino()]; })->all();
        $sinCategoria = $categorias->isNotEmpty() ? Producto::sinCategoria()->count() : 0;

        $caracteristicas = Etiqueta::orderBy('nombre')->get(['id', 'nombre']);

        return view('admin.productos.index', compact('productos', 'proveedores', 'filtro', 'categorias', 'caminos', 'sinCategoria', 'caracteristicas'));
    }

    /**
     * Agregar o quitar categorías a varios productos: los marcados en la página, o
     * todos los que da el filtro (para catálogos de miles, sin recorrerlos a mano).
     */
    public function categoriasEnMasa(Request $request, AsignacionDeCategorias $asignacion)
    {
        $datos = $request->validate([
            'accion'       => 'required|in:asignar,quitar',
            'alcance'      => 'required|in:seleccion,filtro',
            'categorias'   => 'required|array|min:1',
            'categorias.*' => 'integer|exists:categorias,id',
            'productos'    => 'required_if:alcance,seleccion|array',
            'productos.*'  => 'integer',
            'filtro'       => 'nullable|array',
        ], [
            'categorias.required' => 'Elegí al menos una categoría.',
            'productos.required_if' => 'Marcá al menos un producto.',
        ]);

        $filtro = new FiltroDeProductos($datos['filtro'] ?? []);
        $productos = $datos['alcance'] === 'filtro'
            ? $filtro->consulta()
            : Producto::whereIn('productos.id', $datos['productos']);

        $nombres = Categoria::whereIn('id', $datos['categorias'])->orderBy('nombre')->get()
            ->map(function (Categoria $c) { return '«' . str_replace(' > ', ' › ', $c->camino()) . '»'; })->implode(', ');

        if ($datos['accion'] === 'asignar') {
            $total = (clone $productos)->count();
            $creadas = $asignacion->asignar($productos, $datos['categorias']);
            $mensaje = $creadas === 0
                ? "Esos productos ya estaban en {$nombres}."
                : "Se agregó {$nombres} a " . $this->cantidadDeProductos($total) . '.';
        } else {
            $afectados = (clone $productos)->whereIn('productos.id', function ($sub) use ($datos) {
                $sub->select('producto_id')->from('categoria_producto')->whereIn('categoria_id', $datos['categorias']);
            })->count();
            $asignacion->quitar($productos, $datos['categorias']);
            $mensaje = $afectados === 0
                ? "Ninguno de esos productos estaba en {$nombres}."
                : "Se quitó {$nombres} de " . $this->cantidadDeProductos($afectados) . '.';
        }

        return redirect()->route('admin.productos.index', $filtro->valores() + $request->only('page'))
            ->with('success', $mensaje);
    }

    /**
     * El código no puede ser el de otro producto: es lo que usa el Excel para saber
     * cuál actualizar. El mensaje dice cuál lo tiene, para ir a verlo.
     */
    /** El formato de las variantes del formulario; las reglas de negocio, en VariantesDeProducto. */
    const REGLAS_VARIANTES = [
        'variantes'            => 'nullable|array|max:300',
        'variantes.*.id'       => 'nullable|integer',
        'variantes.*.valores'  => 'required|array|min:1',
        'variantes.*.valores.*' => 'nullable|string|max:255',
        'variantes.*.codigo'   => 'nullable|string|max:100',
        'variantes.*.precio'   => 'nullable|numeric|min:0|max:999999999999',
        'variantes.*.stock'    => 'nullable|integer|min:0|max:1000000',
        'variantes.*.activa'   => 'nullable|boolean',
    ];

    const MENSAJES_VARIANTES = [
        'variantes.*.precio.numeric' => 'El precio de una variante tiene que ser un número.',
        'variantes.*.stock.integer'  => 'El stock de una variante tiene que ser un número entero.',
        'variantes.*.stock.min'      => 'El stock de una variante no puede ser negativo.',
    ];

    private function codigoLibre(Producto $actual = null)
    {
        return function ($atributo, $valor, $fallar) use ($actual) {
            if (trim((string) $valor) === '') {
                return;
            }
            $otro = Producto::conCodigo((string) $valor)
                ->when($actual, function ($q) use ($actual) { $q->where('id', '!=', $actual->id); })
                ->first(['id', 'descripcion']);
            if ($otro) {
                $fallar("Ese código ya lo tiene «{$otro->descripcion}». Cada producto necesita un código distinto.");
                return;
            }
            $variante = Variante::whereRaw('lower(trim(codigo)) = ?', [mb_strtolower(trim((string) $valor), 'UTF-8')])
                ->with('producto:id,descripcion')->first();
            if ($variante) {
                $fallar("Ese código ya lo tiene una variante de «{$variante->producto->descripcion}».");
            }
        };
    }

    private function cantidadDeProductos(int $n): string
    {
        return number_format($n, 0, ',', '.') . ($n === 1 ? ' producto' : ' productos');
    }

    public function create()
    {
        $proveedores = Proveedor::where('activo', true)->orderBy('nombre')->get();
        $etiquetas = Etiqueta::orderBy('nombre')->get();
        $monedas = Moneda::where('activa', true)->orderBy('nombre')->get();
        $caracteristicasPorCategoria = (new CaracteristicasPorCategoria())->mapaParaFormulario();
        $monedaDefaultId = Moneda::idPorDefecto();
        $categorias = Categoria::enOrdenDeArbol();

        return view('admin.productos.create', compact(
            'proveedores', 'etiquetas', 'monedas', 'categorias', 'caracteristicasPorCategoria', 'monedaDefaultId'
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
            'id_proveedor' => ['nullable', 'string', 'max:255', $this->codigoLibre()],
            'descripcion' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|regex:/^[a-z0-9-]+$/',
            'detalle' => 'nullable|string',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'disponible' => 'boolean',
            'stock' => 'required|integer|min:0|max:1000000',
            'por_encargue' => 'boolean',
            'categorias' => 'nullable|array',
            'categorias.*' => 'integer|exists:categorias,id',
            'etiquetas' => 'nullable|array',
            'etiquetas.*.etiqueta_id' => 'nullable|exists:etiquetas,id',
            'etiquetas.*.valor' => 'nullable|string|max:255',
            'especificaciones' => 'nullable|array',
            'especificaciones.*.clave' => 'nullable|string|max:255',
            'especificaciones.*.valor' => 'nullable|string|max:255',
        ] + $this->reglasDePrecio($request) + GaleriaProducto::reglas() + self::REGLAS_VARIANTES, [
            'slug.regex' => 'La dirección web solo puede tener letras minúsculas, números y guiones.',
        ] + $this->mensajesDePrecio() + GaleriaProducto::mensajes() + self::MENSAJES_VARIANTES);

        // Switch "Generar automáticamente" prendido (o ausente, ej. una API externa):
        // ignoramos cualquier slug que haya llegado y dejamos que el boot() del modelo
        // lo arme solo a partir de la descripción.
        if ($request->boolean('autogenerar_slug', true)) {
            unset($validated['slug']);
        }

        $datos = $this->datosDelFormulario($request, $validated)
            ->conStockInicial((int) $validated['stock']);

        $this->guardado->crear($datos, auth()->id());

        return redirect()->route('admin.productos.index')
            ->with('success', 'Producto creado correctamente');
    }

    public function edit(Producto $producto)
    {
        $producto->load(['categorias', 'etiquetas', 'especificaciones', 'imagenes']);
        $proveedores = Proveedor::where('activo', true)->orderBy('nombre')->get();
        $etiquetas = Etiqueta::orderBy('nombre')->get();
        $monedas = Moneda::where('activa', true)->orderBy('nombre')->get();
        $caracteristicasPorCategoria = (new CaracteristicasPorCategoria())->mapaParaFormulario();
        $monedaDefaultId = Moneda::idPorDefecto();
        $categorias = Categoria::enOrdenDeArbol();

        return view('admin.productos.edit', compact(
            'producto', 'proveedores', 'etiquetas', 'monedas', 'monedaDefaultId', 'categorias', 'caracteristicasPorCategoria'
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
            'id_proveedor'         => ['nullable', 'string', 'max:255', $this->codigoLibre($producto)],
            'descripcion'          => 'required|string|max:255',
            'slug'                 => 'nullable|string|max:100|regex:/^[a-z0-9-]+$/',
            'detalle'              => 'nullable|string',
            'meta_title'           => 'nullable|string|max:60',
            'meta_description'     => 'nullable|string|max:160',
            'disponible'           => 'boolean',
            'por_encargue'         => 'boolean',
            'categorias'           => 'nullable|array',
            'categorias.*'         => 'integer|exists:categorias,id',
            'etiquetas'            => 'nullable|array',
            'etiquetas.*.etiqueta_id' => 'nullable|exists:etiquetas,id',
            'etiquetas.*.valor'    => 'nullable|string|max:255',
            'especificaciones'     => 'nullable|array',
            'especificaciones.*.clave' => 'nullable|string|max:255',
            'especificaciones.*.valor' => 'nullable|string|max:255',
        ] + $this->reglasDePrecio($request) + GaleriaProducto::reglas() + self::REGLAS_VARIANTES, [
            'slug.regex' => 'La dirección web solo puede tener letras minúsculas, números y guiones.',
        ] + $this->mensajesDePrecio() + GaleriaProducto::mensajes() + self::MENSAJES_VARIANTES);

        // Cambiar el slug es seguro: la URL resuelve por id, así que las direcciones
        // ya publicadas siguen funcionando (redirigen 301 a la nueva).
        if ($request->boolean('autogenerar_slug')) {
            $validated['slug'] = Producto::generarSlug($validated['descripcion']);
        } elseif (empty($validated['slug'])) {
            $validated['slug'] = $producto->slug ?: Producto::generarSlug($validated['descripcion']);
        }

        $this->guardado->actualizar($producto, $this->datosDelFormulario($request, $validated), auth()->id());

        // Vuelve a donde estaba: al pedido desde el que entró a editar, o al listado
        // con sus filtros y su página.
        $backParams = $request->input('_back', '');
        $backUrl = self::vueltaAlPanel($request->input('_back_url'))
            ?: route('admin.productos.index') . ($backParams ? '?' . $backParams : '');

        return redirect($backUrl)->with('success', 'Producto actualizado correctamente');
    }

    /**
     * La dirección a la que volver, sólo si es una pantalla de este panel: viene en la
     * URL, y sin revisarla servía para armar un enlace a cualquier lado.
     */
    public static function vueltaAlPanel($url): ?string
    {
        return is_string($url) && strpos($url, url('/admin') . '/') === 0 ? $url : null;
    }

    /**
     * El formulario traducido a DatosDeProducto. El formulario manda siempre las
     * etiquetas y las especificaciones (aunque vengan vacías, porque se quitaron
     * todas), así que las reemplaza. La galería sólo si vino el campo
     * galeria_enviada: un pedido que no viene del formulario no tiene por qué
     * borrar las imágenes.
     */
    private function datosDelFormulario(Request $request, array $validated): DatosDeProducto
    {
        $validated['disponible']   = $request->boolean('disponible');
        $validated['por_encargue'] = $request->boolean('por_encargue');

        $atributos = array_diff_key($validated, array_flip([
            'stock', 'categorias', 'etiquetas', 'especificaciones', 'galeria', 'galeria_archivos', 'variantes',
        ]));

        // Una fila por valor: una característica con varios (Talle: S, M, L) manda varias.
        $etiquetas = [];
        foreach ($request->input('etiquetas', []) as $fila) {
            if (!empty($fila['etiqueta_id'])) {
                $etiquetas[$fila['etiqueta_id']][] = $fila['valor'] ?? '';
            }
        }

        // El formulario siempre tiene la sección de categorías: si no llega ninguna
        // es porque se quitaron todas.
        $datos = (new DatosDeProducto($atributos))
            ->conCategorias($request->input('categorias', []))
            ->conEtiquetas($etiquetas)
            ->conEspecificaciones($request->input('especificaciones', []));

        if ($request->boolean('galeria_enviada')) {
            $datos = $datos->conGaleria($request->input('galeria', []), $request->file('galeria_archivos', []));
        }

        // Como la galería: sólo si vino la sección. Sin propiedades, llega vacía (sin variantes).
        if ($request->boolean('variantes_enviadas')) {
            $datos = $datos->conVariantes($request->input('variantes', []));
        }

        return $datos;
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

    /**
     * Reglas del bloque de precio. Son las mismas en el alta y en la edición, y
     * dependen del modo elegido: en modo manual el precio lo carga el usuario, en
     * modo margen sale de la compra y lo que llegue en `precio` se descarta.
     */
    private function reglasDePrecio(Request $request)
    {
        // Una request que no manda el modo -- un formulario viejo, un import por
        // HTTP -- se interpreta como "precio fijo", que es como funcionaba esto
        // antes de que hubiera modos. Se completa acá, del mismo modo que el slug
        // más arriba, para que las reglas de abajo puedan darlo por presente.
        if (!$request->filled('modo_precio_venta')) {
            $request->merge(['modo_precio_venta' => Producto::MODO_PRECIO_MANUAL]);
        }

        $modo = $request->input('modo_precio_venta');

        // Sin moneda de venta el precio no significa nada: no se sabe si son pesos o
        // dólares. Con precio en cero (producto sin precio publicado) sí se puede
        // dejar vacía, que es como se cargan los catálogos que no muestran precios.
        $monedaVentaObligatoria = Rule::requiredIf(function () use ($request, $modo) {
            return $modo === Producto::MODO_PRECIO_MARGEN || (float) $request->input('precio') > 0;
        });

        return [
            'modo_precio_venta' => 'required|in:' . Producto::MODO_PRECIO_MANUAL . ',' . Producto::MODO_PRECIO_MARGEN,
            'precio'            => 'nullable|numeric|min:0|max:999999999999|required_if:modo_precio_venta,' . Producto::MODO_PRECIO_MANUAL,
            'moneda_id'         => ['nullable', 'exists:monedas,id', $monedaVentaObligatoria],
            'moneda_compra_id'  => 'nullable|exists:monedas,id|required_if:modo_precio_venta,' . Producto::MODO_PRECIO_MARGEN,
            'precio_compra'     => 'nullable|numeric|min:0|max:999999999999|required_if:modo_precio_venta,' . Producto::MODO_PRECIO_MARGEN,
            'margen_ganancia'   => 'nullable|numeric|min:0|max:99999.99|required_if:modo_precio_venta,' . Producto::MODO_PRECIO_MARGEN,
        ];
    }

    private function mensajesDePrecio()
    {
        return [
            'modo_precio_venta.required'  => 'Elegí si el precio de venta es fijo o se calcula por margen.',
            'modo_precio_venta.in'        => 'Elegí si el precio de venta es fijo o se calcula por margen.',
            'precio.required_if'          => 'Cargá el precio de venta, o pasá a "Por margen" para que lo calcule el sistema.',
            'precio.numeric'              => 'El precio de venta tiene que ser un número.',
            'precio.min'                  => 'El precio de venta no puede ser negativo.',
            'moneda_id.required'          => 'Elegí la moneda de venta: sin ella el precio no dice nada.',
            'moneda_compra_id.required_if' => 'Para calcular el precio por margen hace falta saber en qué moneda comprás.',
            'precio_compra.required_if'   => 'Para calcular el precio por margen hace falta el precio de compra.',
            'precio_compra.numeric'       => 'El precio de compra tiene que ser un número.',
            'precio_compra.min'           => 'El precio de compra no puede ser negativo.',
            'margen_ganancia.required_if' => 'Indicá el margen de ganancia, en porcentaje.',
            'margen_ganancia.numeric'     => 'El margen de ganancia tiene que ser un número.',
            'margen_ganancia.min'         => 'El margen de ganancia no puede ser negativo.',
            'margen_ganancia.max'         => 'El margen de ganancia es demasiado grande.',
        ];
    }

    private function eliminarImagenLocal(Producto $producto): void
    {
        if ($producto->url_imagen && strpos($producto->url_imagen, 'http') !== 0) {
            Storage::disk('public')->delete($producto->url_imagen);
        }
    }

    public function historial(Producto $producto)
    {
        $movimientos = Paginas::enRango($producto->movimientos()
            ->with(['pedido', 'user', 'variante.valores.etiqueta'])
            ->orderBy('created_at', 'desc')
            // Varios movimientos del mismo guardado tienen la misma hora: sin esto salían
            // mezclados ("vuelve al producto" debajo de lo que lo causó) y la paginación
            // podía repetir o saltear alguno.
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->withQueryString());

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
            'cantidad'    => 'required|integer|min:1|max:1000000',
            'descripcion' => 'required|string|max:255',
        ], [
            'cantidad.min'        => 'La cantidad tiene que ser mayor a cero.',
            'cantidad.required'   => 'Indicá la cantidad a ajustar.',
            'tipo.required'       => 'Elegí si es un ingreso o un egreso.',
            'descripcion.required' => 'Indicá el motivo del ajuste.',
        ]);

        if ($producto->tieneVariantes()) {
            return back()->with('error', 'Este producto tiene variantes: el stock se cambia en cada una, en la sección Variantes.');
        }

        $variacion = $validated['tipo'] === 'egreso'
            ? -$validated['cantidad']
            : $validated['cantidad'];

        if ($producto->stock + $variacion < 0) {
            return back()->withErrors([
                'cantidad' => 'No se puede egresar ' . $validated['cantidad'] . ': el stock actual es ' . $producto->stock . '.',
            ])->withInput();
        }

        // El control de arriba es con el stock de cuando se abrió el producto: si
        // mientras tanto se vendió, registrarMovimiento (con el stock bloqueado) lo
        // rechaza, y eso es un aviso, no un error 500.
        try {
            $producto->registrarMovimiento(
                $variacion,
                StockMovimiento::TIPO_AJUSTE_MANUAL,
                $validated['descripcion'],
                null,
                auth()->id()
            );
        } catch (\UnderflowException $e) {
            $actual = (int) Producto::whereKey($producto->id)->value('stock');

            return back()->withErrors([
                'cantidad' => 'No se puede egresar ' . $validated['cantidad'] . ': el stock actual es ' . $actual . '.',
            ])->withInput();
        }

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

        $query = ProductoEspecificacion::query()
            ->select('clave as texto', DB::raw('count(*) as usos'))
            ->groupBy('clave');

        if ($buscar !== '') {
            $query->where('clave', 'ilike', "%{$buscar}%");
        }

        return response()->json($this->comoSugerencias($query->orderBy('clave')->limit(self::MAX_SUGERENCIAS)->get()));
    }

    /**
     * Mismo formato que las sugerencias de etiquetas (EtiquetaValor::comoSugerencia):
     * el texto y cuántos productos lo usan, para distinguir el usual de uno cargado
     * una vez.
     */
    private function comoSugerencias($filas)
    {
        return $filas->map(function ($fila) {
            $usos = (int) $fila->usos;

            return ['valor' => $fila->texto, 'detalle' => $usos . ($usos === 1 ? ' producto' : ' productos')];
        })->values();
    }

    public function buscarEspecificacionValores(Request $request)
    {
        $buscar = trim($request->get('q', ''));
        $clave  = trim($request->get('clave', ''));

        $query = ProductoEspecificacion::query()
            ->select('valor as texto', DB::raw('count(*) as usos'))
            ->groupBy('valor');

        if ($buscar !== '') {
            $query->where('valor', 'ilike', "%{$buscar}%");
        }

        if ($clave !== '') {
            $query->where('clave', $clave);
        }

        return response()->json($this->comoSugerencias($query->orderBy('valor')->limit(self::MAX_SUGERENCIAS)->get()));
    }

    public function buscarParaPedido(Request $request)
    {
        $q = $request->get('q', '');

        $productos = Producto::with(['moneda', 'variantes' => function ($v) { $v->where('activa', true)->with('valores'); }])
            ->where('disponible', true)
            ->where(function ($query) use ($q) {
                $query->where('descripcion', 'ilike', "%{$q}%")
                      ->orWhere('id_proveedor', 'ilike', "%{$q}%")
                      ->orWhereHas('variantes', function ($v) use ($q) { $v->where('codigo', 'ilike', "%{$q}%"); });
            })
            ->orderBy('descripcion')
            ->limit(20)
            ->get();

        // Un producto con variantes aparece una vez por cada una (Remera (M), Remera (L)):
        // el pedido es de un talle, con su stock y su precio.
        $resultados = [];
        foreach ($productos as $p) {
            $base = [
                'id'          => $p->id,
                'variante_id' => null,
                'descripcion' => $p->descripcion,
                'codigo'      => $p->id_proveedor,
                'precio'      => $p->precio,
                'stock'       => $p->por_encargue ? null : $p->stock,
                'moneda_id'   => $p->moneda_id,
                'simbolo'     => $p->moneda ? $p->moneda->simbolo : '$',
            ];
            if ($p->variantes->isEmpty()) {
                $resultados[] = $base;
                continue;
            }
            foreach ($p->variantes as $variante) {
                $variante->setRelation('producto', $p);
                $resultados[] = array_merge($base, [
                    'variante_id' => $variante->id,
                    'descripcion' => $variante->descripcionCompleta(),
                    'codigo'      => $variante->codigo ?: $p->id_proveedor,
                    'precio'      => $variante->precioFinal(),
                    'stock'       => $variante->stockMaximo(),
                ]);
            }
        }

        return response()->json($resultados);
    }
}
