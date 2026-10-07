<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etiqueta;
use App\Models\Menu;
use App\Models\Producto;
use App\Services\ArbolDeMenus;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:menus.ver')->only(['index', 'show', 'valoresEtiqueta', 'contarProductos']);
        $this->middleware('permiso:menus.gestionar')->only(['create', 'store', 'edit', 'update', 'destroy', 'reordenar', 'mover']);
    }

    public function index()
    {
        $menus = Menu::raiz()
            ->orderBy('orden')
            ->orderBy('id')
            ->with('children.children.children')
            ->get();

        // Cuántos productos muestra cada menú, contados como los cuenta la tienda.
        // Los que sólo agrupan no muestran productos: no llevan número.
        $productosPorMenu = [];
        $this->recorrer($menus, function (Menu $menu) use (&$productosPorMenu) {
            if (!$menu->esContenedor()) {
                $productosPorMenu[$menu->id] = Producto::visiblesEnTienda()->delMenu($menu)->count();
            }
        });

        return view('admin.menus.index', compact('menus', 'productosPorMenu'));
    }

    /**
     * Cuántos productos mostraría un menú con lo elegido en el formulario, antes de
     * guardarlo. Incluye lo que hereda del menú de arriba.
     */
    public function contarProductos(Request $request)
    {
        $menu = $this->menuSinGuardar($request);

        // Agrupa (no muestra productos) o todavía no se eligió qué filtrar.
        if ($menu->esContenedor() || $this->faltaElegirEnlace($menu)) {
            return response()->json(['productos' => null]);
        }

        return response()->json(['productos' => Producto::visiblesEnTienda()->delMenu($menu)->count()]);
    }

    /** Un menú armado con los campos del formulario, sin guardar, para contar o sugerir. */
    private function menuSinGuardar(Request $request): Menu
    {
        $tipo = in_array($request->input('tipo_enlace'), [Menu::TIPO_PROVEEDOR, Menu::TIPO_ETIQUETA, Menu::TIPO_ESPECIFICACION], true)
            ? $request->input('tipo_enlace')
            : Menu::TIPO_NINGUNO;

        $menu = new Menu([
            'tipo_enlace'  => $tipo,
            'enlace_id'    => $request->filled('enlace_id') ? (int) $request->input('enlace_id') : null,
            'enlace_valor' => $request->input('enlace_valor'),
            'filtro_stock' => $request->input('filtro_stock', 'todos'),
            'parent_id'    => $request->filled('parent_id') ? (int) $request->input('parent_id') : null,
        ]);

        return $menu;
    }

    private function faltaElegirEnlace(Menu $menu): bool
    {
        return in_array($menu->tipo_enlace, [Menu::TIPO_PROVEEDOR, Menu::TIPO_ETIQUETA], true) && !$menu->enlace_id;
    }

    /** Aplica $accion a cada menú del árbol, de arriba hacia abajo. */
    private function recorrer($menus, callable $accion)
    {
        foreach ($menus as $menu) {
            $accion($menu);
            $this->recorrer($menu->children, $accion);
        }
    }

    public function create(Request $request)
    {
        $siguienteOrdenRaiz = (Menu::whereNull('parent_id')->max('orden') ?? -1) + 1;
        $siguienteOrdenPorPadre = Menu::whereNotNull('parent_id')
            ->selectRaw('parent_id, MAX(orden) as max_orden')
            ->groupBy('parent_id')
            ->pluck('max_orden', 'parent_id')
            ->map(function ($max) { return $max + 1; });

        // Con el + de una fila de la lista, el padre viene en la dirección.
        $padre = $request->filled('parent_id') ? Menu::find((int) $request->input('parent_id')) : null;
        if ($padre && $padre->nivel() > Menu::NIVEL_MAXIMO_PADRE) {
            $padre = null;
        }

        $menu = new Menu([
            'tipo_enlace'  => Menu::TIPO_NINGUNO,
            'activo'       => true,
            'parent_id'    => $padre ? $padre->id : null,
            'orden'        => $padre ? ($siguienteOrdenPorPadre[$padre->id] ?? 0) : $siguienteOrdenRaiz,
            'filtro_stock' => 'todos',
        ]);

        return view('admin.menus.create', array_merge(
            $this->datosFormulario($this->buildMenusOrdenados()),
            compact('menu', 'siguienteOrdenRaiz', 'siguienteOrdenPorPadre')
        ));
    }

    /** Lo que necesita el formulario de menú, igual en alta y edición. */
    private function datosFormulario($menusParent): array
    {
        $proveedores = Proveedor::where('activo', true)->with('etiquetas')->orderBy('nombre')->get();

        // Por cada posible padre, qué filtros hereda un submenú suyo.
        $filtrosHeredados = [];
        foreach ($menusParent as $padre) {
            $filtrosHeredados[$padre->id] = $padre->filtrosParaSubmenus();
        }

        return [
            'menusParent'           => $menusParent,
            'proveedores'           => $proveedores,
            'etiquetas'             => Etiqueta::orderBy('nombre')->get(),
            'etiquetasPorProveedor' => $this->mapEtiquetasAplicables($proveedores),
            'filtrosHeredados'      => $filtrosHeredados,
        ];
    }

    public function store(Request $request)
    {
        // Solo lowercase — sin transliteración, para que el regex rechace chars inválidos
        if ($request->filled('slug')) {
            $request->merge(['slug' => strtolower(trim($request->slug))]);
        }

        $validated = $request->validate([
            'nombre'           => 'required|string|max:255',
            'slug'             => 'nullable|string|max:255|regex:/^[a-z0-9-]+$/|unique:menus,slug',
            'meta_title'       => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'parent_id'        => 'nullable|exists:menus,id',
            'tipo_enlace'      => 'required|in:ninguno,proveedor,etiqueta,especificacion',
            'enlace_id'        => 'nullable|integer',
            'enlace_valor'     => 'nullable|string|max:255',
            'orden'            => 'integer|min:0',
            'activo'           => 'boolean',
            'filtro_stock'     => 'nullable|in:todos,con_stock,con_stock_y_encargue',
            'filtros_etiquetas'   => 'nullable|array',
            'filtros_etiquetas.*' => 'exists:etiquetas,id',
            'filtros_requeridos'  => 'nullable|boolean',
        ], [
            'slug.unique' => 'Este slug ya está en uso, elegí otro.',
            'slug.regex'  => 'El slug solo puede contener letras minúsculas, números y guiones.',
        ]);

        // Si no vino slug, el boot del modelo lo genera desde nombre
        if (empty($validated['slug'])) {
            unset($validated['slug']);
        }

        $validated['activo'] = $request->boolean('activo');
        $validated['filtro_stock'] = $request->input('filtro_stock', 'todos');
        $validated['orden'] = $request->input('orden', 0);
        $validated['filtros_etiquetas'] = $request->input('filtros_etiquetas', []);
        $validated['filtros_requeridos'] = $request->boolean('filtros_requeridos');

        $filtrosTodos = $request->input('filtros_todos', []);
        $filtrosConfig = [];
        foreach ($validated['filtros_etiquetas'] as $etiquetaId) {
            $filtrosConfig[(string) $etiquetaId] = isset($filtrosTodos[(string) $etiquetaId]);
        }
        $validated['filtros_config'] = !empty($filtrosConfig) ? $filtrosConfig : null;

        // Limpiar campos según el tipo
        if ($validated['tipo_enlace'] === 'ninguno') {
            $validated['enlace_id'] = null;
            $validated['enlace_valor'] = null;
            $validated['filtros_etiquetas'] = []; // Contenedores no pueden tener filtros
            $validated['filtros_requeridos'] = false;
            $validated['filtros_config'] = null;
        } elseif ($validated['tipo_enlace'] === 'especificacion') {
            $validated['enlace_id'] = null;
        }

        Menu::create($validated);

        return redirect()->route('admin.menus.index')
            ->with('success', 'Menú creado correctamente');
    }

    public function edit(Menu $menu)
    {
        $excluirIds = array_merge([$menu->id], $this->getDescendantIds($menu));

        return view('admin.menus.edit', array_merge(
            $this->datosFormulario($this->buildMenusOrdenados($excluirIds)),
            compact('menu')
        ));
    }

    public function update(Request $request, Menu $menu)
    {
        // Solo lowercase — sin transliteración, para que el regex rechace chars inválidos
        if ($request->filled('slug')) {
            $request->merge(['slug' => strtolower(trim($request->slug))]);
        }

        $validated = $request->validate([
            'nombre'           => 'required|string|max:255',
            'slug'             => 'nullable|string|max:255|regex:/^[a-z0-9-]+$/|unique:menus,slug,' . $menu->id,
            'meta_title'       => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'parent_id'        => 'nullable|exists:menus,id',
            'tipo_enlace'      => 'required|in:ninguno,proveedor,etiqueta,especificacion',
            'enlace_id'        => 'nullable|integer',
            'enlace_valor'     => 'nullable|string|max:255',
            'orden'            => 'integer|min:0',
            'activo'           => 'boolean',
            'filtro_stock'     => 'nullable|in:todos,con_stock,con_stock_y_encargue',
            'filtros_etiquetas'   => 'nullable|array',
            'filtros_etiquetas.*' => 'exists:etiquetas,id',
            'filtros_requeridos'  => 'nullable|boolean',
        ], [
            'slug.unique' => 'Este slug ya está en uso, elegí otro.',
            'slug.regex'  => 'El slug solo puede contener letras minúsculas, números y guiones.',
        ]);

        // Si vino vacío, mantener el slug actual
        if (empty($validated['slug'])) {
            $validated['slug'] = $menu->slug ?: Menu::generarSlugUnico($validated['nombre'], $menu->id);
        }

        // Evitar que un menú sea su propio padre o descendiente
        if ($validated['parent_id'] == $menu->id || in_array($validated['parent_id'], $this->getDescendantIds($menu))) {
            return back()->withErrors(['parent_id' => 'No puedes asignar este menú como padre']);
        }

        $validated['activo'] = $request->boolean('activo');
        $validated['filtro_stock'] = $request->input('filtro_stock', 'todos');
        $validated['filtros_etiquetas'] = $request->input('filtros_etiquetas', []);
        $validated['filtros_requeridos'] = $request->boolean('filtros_requeridos');

        $filtrosTodos = $request->input('filtros_todos', []);
        $filtrosConfig = [];
        foreach ($validated['filtros_etiquetas'] as $etiquetaId) {
            $filtrosConfig[(string) $etiquetaId] = isset($filtrosTodos[(string) $etiquetaId]);
        }
        $validated['filtros_config'] = !empty($filtrosConfig) ? $filtrosConfig : null;

        // Limpiar campos según el tipo
        if ($validated['tipo_enlace'] === 'ninguno') {
            $validated['enlace_id'] = null;
            $validated['enlace_valor'] = null;
            $validated['filtros_etiquetas'] = []; // Contenedores no pueden tener filtros
            $validated['filtros_requeridos'] = false;
            $validated['filtros_config'] = null;
        } elseif ($validated['tipo_enlace'] === 'especificacion') {
            $validated['enlace_id'] = null;
        }

        $menu->update($validated);

        return redirect()->route('admin.menus.index')
            ->with('success', 'Menú actualizado correctamente');
    }

    public function destroy(Menu $menu)
    {
        $menu->delete();

        return redirect()->route('admin.menus.index')
            ->with('success', 'Menú eliminado correctamente');
    }

    /**
     * Guarda el árbol después de arrastrar en la lista: orden y anidamiento de cada
     * menú. Rechaza ciclos y niveles de más (ver ArbolDeMenus).
     */
    public function reordenar(Request $request)
    {
        $request->validate([
            'items'             => 'required|array',
            'items.*.id'        => 'required|integer|exists:menus,id',
            'items.*.orden'     => 'required|integer|min:0',
            'items.*.parent_id' => 'nullable|integer|exists:menus,id',
        ]);

        (new ArbolDeMenus())->ordenar($request->input('items'));

        return response()->json(['success' => true]);
    }

    /**
     * Obtener valores de etiqueta para autocompletado
     */
    public function valoresEtiqueta(Request $request, Etiqueta $etiqueta)
    {
        $buscar = (string) $request->get('q', '');
        $padre  = $request->filled('parent_id') ? Menu::find((int) $request->input('parent_id')) : null;

        // Dentro de otro menú sólo se ofrecen los valores de sus productos, con
        // cuántos hay de cada uno ahí: dentro de "Notebook", las marcas de notebooks.
        $valores = $padre
            ? $etiqueta->valoresEnUsoEntre(Producto::visiblesEnTienda()->delMenu($padre), $buscar, 20)
            : $etiqueta->valoresEnUso($buscar, 20);

        return response()->json($valores->map->comoSugerencia()->values());
    }

    /**
     * Sube o baja un menú un lugar entre sus hermanos. Se renumera a todos los
     * hermanos (0, 1, 2…) porque hay menús viejos con la misma posición repetida, y
     * con posiciones iguales "subir" no tendría con quién intercambiar.
     */
    public function mover(Request $request, Menu $menu)
    {
        $request->validate(['direccion' => 'required|in:arriba,abajo']);

        $hermanos = Menu::where('parent_id', $menu->parent_id)->orderBy('orden')->orderBy('id')->get()->values();
        $actual   = $hermanos->search(function (Menu $m) use ($menu) { return $m->id === $menu->id; });
        $destino  = $request->input('direccion') === 'arriba' ? $actual - 1 : $actual + 1;

        if ($destino >= 0 && $destino < $hermanos->count()) {
            $orden = $hermanos->all();
            [$orden[$actual], $orden[$destino]] = [$orden[$destino], $orden[$actual]];

            foreach ($orden as $posicion => $hermano) {
                if ((int) $hermano->orden !== $posicion) {
                    $hermano->update(['orden' => $posicion]);
                }
            }
        }

        return redirect()->route('admin.menus.index')->with('menu_movido', $menu->id);
    }

    /**
     * Los menús que pueden ser "Dentro de", en el orden del árbol: hasta el nivel
     * Menu::NIVEL_MAXIMO_PADRE, así un submenú nuevo queda a lo sumo en el cuarto
     * nivel. Si un menú se excluye (en edición: él mismo y los suyos), se excluye con
     * todo lo que tiene adentro.
     */
    private function buildMenusOrdenados($excluirIds = [])
    {
        $resultado = collect();

        $agregar = function ($menus, int $nivel) use (&$agregar, $resultado, $excluirIds) {
            foreach ($menus as $menu) {
                if (in_array($menu->id, $excluirIds)) {
                    continue;
                }
                $resultado->push($menu);
                if ($nivel < Menu::NIVEL_MAXIMO_PADRE) {
                    $agregar($menu->children, $nivel + 1);
                }
            }
        };

        $agregar(Menu::raiz()->orderBy('orden')->orderBy('id')->with('children.children')->get(), 0);

        return $resultado;
    }

    private function mapEtiquetasAplicables($proveedores)
    {
        $map = [];
        foreach ($proveedores as $prov) {
            $todas = $prov->etiquetas;
            $aplicables = $todas->filter(function ($e) {
                return $e->pivot->obligatoria !== null;
            })->pluck('id')->values()->toArray();
            $map[$prov->id] = [
                'configured' => $todas->isNotEmpty(),
                'ids'        => $aplicables,
            ];
        }
        return $map;
    }

    /**
     * Obtener IDs de todos los descendientes de un menú
     */
    private function getDescendantIds(Menu $menu): array
    {
        $ids = [];
        foreach ($menu->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }
        return $ids;
    }
}
