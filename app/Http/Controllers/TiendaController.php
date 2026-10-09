<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Etiqueta;
use App\Models\Menu;
use App\Models\Producto;
use App\Support\BusquedaDeTexto;
use App\Support\OrdenDeProductos;
use App\Support\RefinadoDelCatalogo;
use App\Support\SelectorDeVariantes;
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

        $query = Producto::paraLaGrilla()
            ->visiblesEnTienda();

        // Filtro por búsqueda
        if ($request->filled('buscar')) {
            $this->aplicarFiltroBusqueda($query, $request->buscar);
        }

        // Filtros directos desde URL (modo legacy / sin slug)
        if ($request->filled('etiqueta') && is_numeric($request->etiqueta)) {
            $query->conEtiqueta((int) $request->etiqueta, $request->etiqueta_valor);
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

        $query->segunDisponibilidadDelMenu($menuActual);

        [$filtrosAplicados, $filtrosIncompletos] = $this->aplicarFiltrosCascada($request, $query, $menuActual);

        $productos = $this->paginar($request, $query, $filtrosIncompletos, OrdenDeProductos::porNombre());

        $etiquetas    = Etiqueta::where('visible_usuarios', true)->orderBy('nombre')->get();
        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('tienda.index', compact('productos', 'etiquetas', 'mostrarPrecios', 'menuActual', 'filtrosAplicados', 'filtrosIncompletos'));
    }

    public function catalogo(string $slug, Request $request)
    {
        $menuActual = Menu::where('slug', $slug)->where('activo', true)->firstOrFail();

        $query = Producto::paraLaGrilla()
            ->visiblesEnTienda();

        $query->delMenu($menuActual);

        if ($request->filled('buscar')) {
            $this->aplicarFiltroBusqueda($query, $request->buscar);
        }

        [$filtrosAplicados, $filtrosIncompletos] = $this->aplicarFiltrosCascada($request, $query, $menuActual);

        $productos = $this->paginar($request, $query, $filtrosIncompletos, OrdenDeProductos::deMenu($menuActual));

        $etiquetas    = Etiqueta::where('visible_usuarios', true)->orderBy('nombre')->get();
        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('tienda.index', compact('productos', 'etiquetas', 'mostrarPrecios', 'menuActual', 'filtrosAplicados', 'filtrosIncompletos'));
    }

    /**
     * La página de una categoría: sus productos y los de sus subcategorías. Una
     * categoría oculta (o dentro de una oculta) no existe para el cliente: 404.
     */
    public function categoria(string $slug, Request $request)
    {
        $categoriaActual = Categoria::where('slug', $slug)->firstOrFail();
        if (!$categoriaActual->esVisibleEnTienda()) {
            abort(404);
        }

        $query = Producto::paraLaGrilla()
            ->visiblesEnTienda()
            ->enCategoria($categoriaActual);

        if ($request->filled('buscar')) {
            $this->aplicarFiltroBusqueda($query, $request->buscar);
        }

        $productos = $this->paginar($request, $query, false, OrdenDeProductos::deCategoria($categoriaActual));

        // Los accesos a las de adentro: visibles (la madre ya lo es, alcanza con su
        // propia marca) y con productos, como en el menú: no se ofrece un estante vacío.
        $conProductos = Categoria::idsConProductos();
        $subcategorias = $categoriaActual->children->filter(function (Categoria $c) use ($conProductos) {
            return $c->marcadaVisible() && isset($conProductos[$c->id]);
        })->values();

        $menuActual = null;
        $filtrosAplicados = [];
        $filtrosIncompletos = false;
        $etiquetas = Etiqueta::where('visible_usuarios', true)->orderBy('nombre')->get();
        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('tienda.index', compact(
            'productos', 'etiquetas', 'mostrarPrecios', 'menuActual', 'filtrosAplicados', 'filtrosIncompletos',
            'categoriaActual', 'subcategorias'
        ));
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
        $query->delMenu($menu);

        foreach ($filtros as $filtroEtiquetaId => $filtroValor) {
            if ($filtroValor && $filtroEtiquetaId != $etiquetaId) {
                $query->conEtiqueta($filtroEtiquetaId, $filtroValor);
            }
        }

        // Los productos van como subconsulta y no como lista de ids: en "Kits por
        // Modelo" de oleomc son 30.900, que traídos a PHP y devueltos como parámetros
        // hacían tardar este pedido un segundo.
        $productos = $query->select('productos.id');

        // Los valores ocultos desde el panel no se ofrecen como opción.
        $valores = DB::table('etiqueta_valores')
            ->where('etiqueta_id', $etiquetaId)
            ->where('visible', true)
            ->whereIn('id', function ($q) use ($productos, $etiquetaId) {
                $q->select('etiqueta_valor_id')
                  ->from('producto_etiqueta')
                  ->where('etiqueta_id', $etiquetaId)
                  ->whereIn('producto_id', $productos);
            })
            ->orderBy('valor')
            ->pluck('valor');

        return response()->json($valores);
    }

    /**
     * Obtener productos filtrados via AJAX (para filtros en cascada)
     */
    public function productosAjax(Request $request)
    {
        $query = Producto::paraLaGrilla()
            ->visiblesEnTienda();

        $menuActual = null;
        $orden = OrdenDeProductos::porNombre();
        if ($request->filled('menu_id')) {
            $menuActual = Menu::find($request->menu_id);
            if ($menuActual) {
                $query->delMenu($menuActual);
                $orden = OrdenDeProductos::deMenu($menuActual);
            }
        }

        if ($request->filled('categoria_id')) {
            $categoria = Categoria::find((int) $request->categoria_id);
            if (!$categoria || !$categoria->esVisibleEnTienda()) {
                abort(404);
            }
            $query->enCategoria($categoria);
            $orden = OrdenDeProductos::deCategoria($categoria);
        }

        if ($request->filled('proveedor')) {
            $query->where('proveedor_id', $request->proveedor);
        }

        if ($request->filled('etiqueta')) {
            $query->conEtiqueta($request->etiqueta, $request->etiqueta_valor);
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
                $query->conEtiqueta($etiquetaId, $valor);
            }
        }

        if ($request->filled('buscar')) {
            $this->aplicarFiltroBusqueda($query, $request->buscar);
        }

        $refinado = RefinadoDelCatalogo::desde($request);
        $refinado->aplicar($query);
        $productos = $refinado->orden($orden)->aplicar($query)->paginate(12);
        Producto::cargarResumenDeVariantes($productos->getCollection());
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

        // "Mostrar en tienda" apagado: no existe para el público, aunque tenga el
        // enlace. Quien lo edita en el panel sí lo ve, para revisarlo antes de mostrarlo.
        if (!$producto->disponible && !optional(auth()->user())->puede('productos.ver')) {
            abort(404);
        }

        $producto->load(['proveedor', 'etiquetasPublicas', 'especificaciones', 'moneda', 'imagenes']);
        $mostrarPrecios = Configuracion::mostrarPrecios();

        // Inicio › Bebidas › Gaseosas › el producto: en qué estante está.
        $estante = $producto->estanteParaMigas();
        $migas = $estante ? array_reverse($estante->linaje()) : [];

        // Con variantes, el cliente elige una (Talle M) antes de agregarlo.
        $selector = SelectorDeVariantes::para($producto);

        return view('tienda.show', compact('producto', 'mostrarPrecios', 'migas', 'selector'));
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    /**
     * Cada palabra tiene que estar en el nombre, el código o un dato visible del
     * producto, sin importar tildes ni orden (ver BusquedaDeTexto).
     */
    private function aplicarFiltroBusqueda($query, string $buscar)
    {
        foreach (BusquedaDeTexto::palabras($buscar) as $palabra) {
            $patron = BusquedaDeTexto::patron($palabra);
            $query->where(function ($q) use ($patron) {
                BusquedaDeTexto::contiene($q, 'descripcion', $patron);
                BusquedaDeTexto::contiene($q, 'id_proveedor', $patron);
                // Sólo lo que el cliente ve: buscar por un dato interno (un valor o una
                // característica ocultos) no tiene que traer productos que no lo muestran.
                //
                // El texto se busca en la lista de valores (etiqueta_valores, ~2.400 en
                // oleomc) y no fila por fila en producto_etiqueta (~93.000): es el mismo
                // texto (ProductoEtiqueta lo copia al guardarse) y cada búsqueda de la
                // tienda pasa de ~180 ms a ~60 ms.
                $q->orWhereIn('productos.id', function ($conValor) use ($patron) {
                    $conValor->select('producto_etiqueta.producto_id')->from('producto_etiqueta')
                        ->whereIn('producto_etiqueta.etiqueta_valor_id', function ($valores) use ($patron) {
                            $valores->select('etiqueta_valores.id')->from('etiqueta_valores')
                                ->join('etiquetas', 'etiquetas.id', '=', 'etiqueta_valores.etiqueta_id')
                                ->where('etiquetas.visible_usuarios', true)
                                ->where('etiqueta_valores.visible', true);
                            BusquedaDeTexto::contiene($valores, 'etiqueta_valores.valor', $patron, 'and');
                        });
                });
            });
        }
    }

    private function aplicarFiltrosCascada(Request $request, $query, ?Menu $menuActual): array
    {
        $filtrosAplicados = [];
        foreach ($request->all() as $key => $value) {
            if (preg_match('/^f(\d+)$/', $key, $matches) && $value) {
                $etiquetaId = (int) $matches[1];
                $filtrosAplicados[$etiquetaId] = $value;
                $query->conEtiqueta($etiquetaId, $value);
            }
        }

        $filtrosIncompletos = false;
        // Sólo cuentan los filtros que el cliente ve (Menu::getEtiquetasFiltro).
        if ($menuActual && $menuActual->filtros_requeridos && $menuActual->tieneFiltros()) {
            foreach ($menuActual->getEtiquetasFiltro()->pluck('id') as $etiquetaId) {
                if (empty($filtrosAplicados[$etiquetaId])) {
                    $filtrosIncompletos = true;
                    break;
                }
            }
        }

        return [$filtrosAplicados, $filtrosIncompletos];
    }

    private function paginar(Request $request, $query, bool $filtrosIncompletos, OrdenDeProductos $orden)
    {
        // Lo que eligió el cliente: rango de precio, "solo con stock" y "Ordenar por".
        $refinado = RefinadoDelCatalogo::desde($request);
        $refinado->aplicar($query);
        $orden = $refinado->orden($orden);

        if ($filtrosIncompletos) {
            return new LengthAwarePaginator([], 0, 12, 1, [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]);
        }

        $productos = $orden->aplicar($query)->paginate(12);

        if ($productos->currentPage() > max($productos->lastPage(), 1)) {
            abort(404);
        }

        // Variantes activas y "Desde $…" de toda la página, en una consulta.
        Producto::cargarResumenDeVariantes($productos->getCollection());

        return $productos;
    }
}
