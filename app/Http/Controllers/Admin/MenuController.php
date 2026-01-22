<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etiqueta;
use App\Models\Menu;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class MenuController extends Controller
{
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
        $menusParent = Menu::orderBy('nombre')->get();
        $proveedores = Proveedor::where('activo', true)->orderBy('nombre')->get();
        $categorias = Etiqueta::orderBy('nombre')->get();

        return view('admin.menus.create', compact('menusParent', 'proveedores', 'categorias'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:menus,id',
            'tipo_enlace' => 'required|in:ninguno,proveedor,categoria,especificacion',
            'enlace_id' => 'nullable|integer',
            'enlace_valor' => 'nullable|string|max:255',
            'orden' => 'integer|min:0',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->boolean('activo');
        $validated['orden'] = $request->input('orden', 0);

        // Limpiar campos según el tipo
        if ($validated['tipo_enlace'] === 'ninguno') {
            $validated['enlace_id'] = null;
            $validated['enlace_valor'] = null;
        } elseif ($validated['tipo_enlace'] === 'especificacion') {
            $validated['enlace_id'] = null;
        }

        Menu::create($validated);

        return redirect()->route('admin.menus.index')
            ->with('success', 'Menú creado correctamente');
    }

    public function edit(Menu $menu)
    {
        $menusParent = Menu::where('id', '!=', $menu->id)
            ->whereNotIn('id', $this->getDescendantIds($menu))
            ->orderBy('nombre')
            ->get();
        $proveedores = Proveedor::where('activo', true)->orderBy('nombre')->get();
        $categorias = Etiqueta::orderBy('nombre')->get();

        return view('admin.menus.edit', compact('menu', 'menusParent', 'proveedores', 'categorias'));
    }

    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:menus,id',
            'tipo_enlace' => 'required|in:ninguno,proveedor,categoria,especificacion',
            'enlace_id' => 'nullable|integer',
            'enlace_valor' => 'nullable|string|max:255',
            'orden' => 'integer|min:0',
            'activo' => 'boolean',
        ]);

        // Evitar que un menú sea su propio padre o descendiente
        if ($validated['parent_id'] == $menu->id || in_array($validated['parent_id'], $this->getDescendantIds($menu))) {
            return back()->withErrors(['parent_id' => 'No puedes asignar este menú como padre']);
        }

        $validated['activo'] = $request->boolean('activo');

        // Limpiar campos según el tipo
        if ($validated['tipo_enlace'] === 'ninguno') {
            $validated['enlace_id'] = null;
            $validated['enlace_valor'] = null;
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
     * Obtener valores de categoría para autocompletado
     */
    public function valoresCategoria(Request $request, Etiqueta $categoria)
    {
        $buscar = $request->get('q', '');

        $valores = \DB::table('producto_etiqueta')
            ->where('etiqueta_id', $categoria->id)
            ->where('valor', 'like', "%{$buscar}%")
            ->distinct()
            ->pluck('valor')
            ->take(20);

        return response()->json($valores);
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
