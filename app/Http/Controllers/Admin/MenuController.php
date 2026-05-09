<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etiqueta;
use App\Models\Menu;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:menus.ver')->only(['index', 'show', 'valoresEtiqueta']);
        $this->middleware('permiso:menus.gestionar')->only(['create', 'store', 'edit', 'update', 'destroy', 'reordenar']);
    }

    public function index()
    {
        $menus = Menu::raiz()
            ->orderBy('orden')
            ->with(['children' => function ($query) {
                $query->orderBy('orden')->with('children');
            }])
            ->get();

        return view('admin.menus.index', compact('menus'));
    }

    public function create()
    {
        $menusParent = $this->buildMenusOrdenados();
        $proveedores = Proveedor::where('activo', true)->with('etiquetas')->orderBy('nombre')->get();
        $etiquetas = Etiqueta::orderBy('nombre')->get();
        $etiquetasPorProveedor = $this->mapEtiquetasAplicables($proveedores);

        return view('admin.menus.create', compact('menusParent', 'proveedores', 'etiquetas', 'etiquetasPorProveedor'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:menus,id',
            'tipo_enlace' => 'required|in:ninguno,proveedor,etiqueta,especificacion',
            'enlace_id' => 'nullable|integer',
            'enlace_valor' => 'nullable|string|max:255',
            'orden' => 'integer|min:0',
            'activo' => 'boolean',
            'filtro_stock' => 'nullable|in:todos,con_stock,con_stock_y_encargue',
            'filtros_etiquetas' => 'nullable|array',
            'filtros_etiquetas.*' => 'exists:etiquetas,id',
            'filtros_requeridos' => 'nullable|boolean',
        ]);

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
        $menusParent = $this->buildMenusOrdenados($excluirIds);
        $proveedores = Proveedor::where('activo', true)->with('etiquetas')->orderBy('nombre')->get();
        $etiquetas = Etiqueta::orderBy('nombre')->get();
        $etiquetasPorProveedor = $this->mapEtiquetasAplicables($proveedores);

        return view('admin.menus.edit', compact('menu', 'menusParent', 'proveedores', 'etiquetas', 'etiquetasPorProveedor'));
    }

    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:menus,id',
            'tipo_enlace' => 'required|in:ninguno,proveedor,etiqueta,especificacion',
            'enlace_id' => 'nullable|integer',
            'enlace_valor' => 'nullable|string|max:255',
            'orden' => 'integer|min:0',
            'activo' => 'boolean',
            'filtro_stock' => 'nullable|in:todos,con_stock,con_stock_y_encargue',
            'filtros_etiquetas' => 'nullable|array',
            'filtros_etiquetas.*' => 'exists:etiquetas,id',
            'filtros_requeridos' => 'nullable|boolean',
        ]);

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
     * Reordenar menús via AJAX
     */
    public function reordenar(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:menus,id',
            'items.*.orden' => 'required|integer',
            'items.*.parent_id' => 'nullable|exists:menus,id',
        ]);

        foreach ($request->items as $item) {
            Menu::where('id', $item['id'])->update([
                'orden' => $item['orden'],
                'parent_id' => $item['parent_id'],
            ]);
        }

        Menu::limpiarCache();

        return response()->json(['success' => true]);
    }

    /**
     * Obtener valores de etiqueta para autocompletado
     */
    public function valoresEtiqueta(Request $request, Etiqueta $etiqueta)
    {
        $buscar = $request->get('q', '');

        $valores = \DB::table('producto_etiqueta')
            ->where('etiqueta_id', $etiqueta->id)
            ->where('valor', 'ilike', "%{$buscar}%")
            ->distinct()
            ->pluck('valor')
            ->take(20);

        return response()->json($valores);
    }

    private function buildMenusOrdenados($excluirIds = [])
    {
        $raices = Menu::raiz()
            ->when(!empty($excluirIds), function ($q) use ($excluirIds) {
                $q->whereNotIn('id', $excluirIds);
            })
            ->orderBy('orden')
            ->orderBy('nombre')
            ->with(['children' => function ($q) {
                $q->orderBy('orden')->orderBy('nombre');
            }])
            ->get();

        $resultado = collect();
        foreach ($raices as $padre) {
            $resultado->push($padre);
            foreach ($padre->children as $hijo) {
                if (empty($excluirIds) || !in_array($hijo->id, $excluirIds)) {
                    $resultado->push($hijo);
                }
            }
        }
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
