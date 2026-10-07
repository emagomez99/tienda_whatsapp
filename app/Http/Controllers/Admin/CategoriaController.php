<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\EtiquetaValor;
use App\Models\Menu;
use App\Models\Producto;
use App\Services\ArbolDeCategorias;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin › Categorías: el árbol de "qué es" cada producto.
 * Ver docs/plan-categorias-y-caracteristicas.md.
 */
class CategoriaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:categorias.ver')->only(['index']);
        $this->middleware('permiso:categorias.gestionar')->except(['index']);
    }

    public function index()
    {
        $categorias = Categoria::whereNull('parent_id')
            ->orderBy('orden')
            ->orderBy('id')
            ->with('children.children')
            ->get();

        // Cuántos productos tiene cada una, contando los de sus subcategorías (un
        // producto en "Ropa › Remeras" también es de "Ropa").
        $productosPorCategoria = [];
        $this->recorrer($categorias, function (Categoria $categoria) use (&$productosPorCategoria) {
            $productosPorCategoria[$categoria->id] = Producto::enCategoria($categoria)->count();
        });

        $sinCategoria = Producto::sinCategoria()->count();

        return view('admin.categorias.index', compact('categorias', 'productosPorCategoria', 'sinCategoria'));
    }

    public function create(Request $request)
    {
        // Con el + de una fila, la madre viene en la dirección.
        $padre = $request->filled('parent_id') ? Categoria::find((int) $request->input('parent_id')) : null;
        if ($padre && !$padre->admiteSubcategorias()) {
            $padre = null;
        }

        $categoria = new Categoria(['visible' => true, 'parent_id' => $padre ? $padre->id : null]);

        return view('admin.categorias.create', [
            'categoria' => $categoria,
            'madres'    => $this->madresPosibles(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $datos['orden'] = (int) Categoria::where('parent_id', $datos['parent_id'])->max('orden') + 1;

        $categoria = Categoria::create($datos);

        return redirect()->route('admin.categorias.index')
            ->with('success', "Se creó la categoría «{$categoria->camino()}».")
            ->with('categoria_movida', $categoria->id);
    }

    /**
     * Crear una categoría sin salir del producto (panel "Elegir categorías"). Mismas
     * reglas que el alta normal; responde lo que el panel necesita para mostrarla.
     */
    public function crearRapida(Request $request)
    {
        $datos = $this->validar($request->merge(['visible' => $request->input('visible', 1)]));
        $datos['orden'] = (int) Categoria::where('parent_id', $datos['parent_id'])->max('orden') + 1;

        $categoria = Categoria::create($datos);

        return response()->json([
            'id'     => $categoria->id,
            'nombre' => $categoria->nombre,
            'camino' => $categoria->camino(),
            'nivel'  => $categoria->nivel(),
            'padre'  => $categoria->parent_id,
        ], 201);
    }

    public function edit(Categoria $categoria)
    {
        return view('admin.categorias.edit', [
            'categoria' => $categoria,
            'madres'    => $this->madresPosibles($categoria),
        ]);
    }

    public function update(Request $request, Categoria $categoria)
    {
        $categoria->update($this->validar($request, $categoria));

        return redirect()->route('admin.categorias.index')
            ->with('success', "Se guardó «{$categoria->camino()}».")
            ->with('categoria_movida', $categoria->id);
    }

    /**
     * Eliminar: los productos no se borran, sólo dejan de estar en esta categoría.
     * Con subcategorías no se puede: primero hay que moverlas o eliminarlas, así
     * nadie borra un árbol entero sin darse cuenta. Tampoco si un menú la usa: el
     * menú quedaría sin productos sin que nadie lo note.
     */
    public function destroy(Categoria $categoria)
    {
        if ($categoria->children()->exists()) {
            return back()->with('error', "«{$categoria->nombre}» tiene subcategorías: primero movelas o eliminalas.");
        }

        $menus = Menu::where('tipo_enlace', Menu::TIPO_CATEGORIA)->where('enlace_id', $categoria->id)->orderBy('nombre')->pluck('nombre');
        if ($menus->isNotEmpty()) {
            $uno = $menus->count() === 1;
            return back()->with('error', "«{$categoria->nombre}» la usa " . ($uno ? 'el menú' : 'los menús') . ' «' . $menus->implode('», «') . '»: primero ' . ($uno ? 'cambialo o eliminalo.' : 'cambialos o eliminalos.'));
        }

        $nombre = $categoria->camino();
        $categoria->productos()->detach();
        $categoria->delete();

        return redirect()->route('admin.categorias.index')->with('success', "Se eliminó «{$nombre}».");
    }

    public function cambiarVisibilidad(Categoria $categoria)
    {
        $categoria->update(['visible' => !$categoria->marcadaVisible()]);

        return back()->with('success', $categoria->marcadaVisible()
            ? "«{$categoria->nombre}» ahora se muestra en la tienda."
            : "«{$categoria->nombre}» ya no se muestra en la tienda" . ($categoria->children()->exists() ? ', y tampoco sus subcategorías.' : '.'));
    }

    /** Guarda el árbol después de arrastrar (ver ArbolOrdenable). */
    public function reordenar(Request $request)
    {
        $request->validate([
            'items'             => 'required|array',
            'items.*.id'        => 'required|integer|exists:categorias,id',
            'items.*.orden'     => 'required|integer|min:0',
            'items.*.parent_id' => 'nullable|integer|exists:categorias,id',
        ]);

        (new ArbolDeCategorias())->ordenar($request->input('items'));

        return response()->json(['success' => true]);
    }

    public function mover(Request $request, Categoria $categoria)
    {
        $request->validate(['direccion' => 'required|in:arriba,abajo']);

        (new ArbolDeCategorias())->moverEntreHermanos($categoria, $request->input('direccion'));

        return redirect()->route('admin.categorias.index')->with('categoria_movida', $categoria->id);
    }

    /**
     * @throws ValidationException
     */
    private function validar(Request $request, Categoria $categoria = null): array
    {
        $datos = $request->validate([
            'nombre'           => 'required|string|max:255',
            'parent_id'        => 'nullable|integer|exists:categorias,id',
            'meta_title'       => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
        ], [
            'nombre.required' => 'Poné un nombre a la categoría.',
        ]);

        $datos['parent_id'] = $datos['parent_id'] ?? null;
        $datos['visible']   = $request->boolean('visible');
        $madre = $datos['parent_id'] ? Categoria::find($datos['parent_id']) : null;

        if ($madre && $categoria) {
            $propias = Categoria::idsConDescendientes([$categoria->id]);
            if (in_array($madre->id, $propias, true)) {
                throw ValidationException::withMessages(['parent_id' => 'Una categoría no puede quedar dentro de sí misma ni de una de sus subcategorías.']);
            }
        }

        // Que no pase de 3 niveles, contando lo que la categoría ya tiene adentro.
        $altura = $categoria ? $this->altura($categoria) : 0;
        if ($madre && $madre->nivel() + 1 + $altura > Categoria::NIVEL_MAXIMO) {
            throw ValidationException::withMessages(['parent_id' => 'Así quedaría a más de ' . (Categoria::NIVEL_MAXIMO + 1) . ' niveles: elegí una categoría de más arriba.']);
        }

        // Dos hermanas no pueden llamarse igual.
        $repetida = Categoria::where('parent_id', $datos['parent_id'])
            ->where('normalizado', EtiquetaValor::normalizar($datos['nombre']))
            ->when($categoria, function ($q) use ($categoria) { $q->where('id', '!=', $categoria->id); })
            ->exists();
        if ($repetida) {
            throw ValidationException::withMessages(['nombre' => 'Ya hay una categoría «' . $datos['nombre'] . '» ' . ($madre ? 'dentro de «' . $madre->nombre . '»' : 'en el primer nivel') . '.']);
        }

        return $datos;
    }

    /** Cuántos niveles tiene una categoría hacia adentro (0 si no tiene subcategorías). */
    private function altura(Categoria $categoria): int
    {
        $max = 0;
        foreach ($categoria->children as $hija) {
            $max = max($max, 1 + $this->altura($hija));
        }
        return $max;
    }

    /**
     * Las categorías que pueden ser madre, en el orden del árbol: las que admiten
     * subcategorías y no son la propia ni una de las suyas.
     */
    private function madresPosibles(Categoria $excepto = null)
    {
        $excluir = $excepto && $excepto->exists ? Categoria::idsConDescendientes([$excepto->id]) : [];
        $resultado = collect();

        $agregar = function ($categorias) use (&$agregar, $resultado, $excluir) {
            foreach ($categorias as $categoria) {
                if (in_array($categoria->id, $excluir, true) || !$categoria->admiteSubcategorias()) {
                    continue;
                }
                $resultado->push($categoria);
                $agregar($categoria->children);
            }
        };

        $agregar(Categoria::whereNull('parent_id')->orderBy('orden')->orderBy('id')->with('children')->get());

        return $resultado;
    }

    private function recorrer($categorias, callable $accion)
    {
        foreach ($categorias as $categoria) {
            $accion($categoria);
            $this->recorrer($categoria->children, $accion);
        }
    }
}
