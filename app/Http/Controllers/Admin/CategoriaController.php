<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Menu;
use App\Models\Producto;
use App\Services\ArbolDeCategorias;
use App\Services\CaracteristicasPorCategoria;
use App\Support\BusquedaDeTexto;
use App\Support\OrdenDeProductos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->with(['caracteristicas', 'children.caracteristicas', 'children.children.caracteristicas'])
            ->get();

        // Cuántos productos tiene cada una, contando los de sus subcategorías (un
        // producto en "Ropa › Remeras" también es de "Ropa").
        $productosPorCategoria = [];
        $this->recorrer($categorias, function (Categoria $categoria) use (&$productosPorCategoria) {
            $productosPorCategoria[$categoria->id] = Producto::enCategoria($categoria)->count();
        });

        $sinCategoria = Producto::sinCategoria()->count();

        // Las vacías no aparecen en el menú de la tienda: se avisa en su fila.
        $conProductos = Categoria::idsConProductos();

        // Lo que se pide a todos los productos, en la fila de arriba del árbol.
        $globales = Etiqueta::where('global', true)->orderBy('nombre')->get();

        return view('admin.categorias.index', compact('categorias', 'productosPorCategoria', 'sinCategoria', 'conProductos', 'globales'));
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
        ] + $this->datosCaracteristicas($categoria));
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $datos['orden'] = (int) Categoria::where('parent_id', $datos['parent_id'])->max('orden') + 1;

        $categoria = DB::transaction(function () use ($datos, $request) {
            $categoria = Categoria::create($datos);
            $this->guardarCaracteristicas($categoria, $request);
            return $categoria;
        });

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
            // parent_id viene del pedido como texto: el panel compara ids como números.
            'padre'  => $categoria->parent_id ? (int) $categoria->parent_id : null,
        ], 201);
    }

    public function edit(Categoria $categoria)
    {
        return view('admin.categorias.edit', [
            'categoria'  => $categoria,
            'madres'     => $this->madresPosibles($categoria),
            'destacados' => $categoria->destacados()->get(['productos.id', 'productos.descripcion', 'productos.id_proveedor']),
        ] + $this->datosCaracteristicas($categoria));
    }

    public function update(Request $request, Categoria $categoria)
    {
        $datos = $this->validar($request, $categoria);
        DB::transaction(function () use ($categoria, $datos, $request) {
            $categoria->update($datos);
            $this->guardarCaracteristicas($categoria, $request);
            $this->guardarDestacados($categoria, $request);
        });

        return redirect()->route('admin.categorias.index')
            ->with('success', "Se guardó «{$categoria->camino()}».")
            ->with('categoria_movida', $categoria->id);
    }

    /**
     * Los productos que se pueden destacar en una categoría: los suyos y los de sus
     * subcategorías, que son los que se ven en su página.
     */
    public function productosParaDestacar(Request $request, Categoria $categoria)
    {
        $query = Producto::enCategoria($categoria);
        foreach (BusquedaDeTexto::palabras((string) $request->input('q', '')) as $palabra) {
            $patron = BusquedaDeTexto::patron($palabra);
            $query->where(function ($q) use ($patron) {
                BusquedaDeTexto::contiene($q, 'descripcion', $patron);
                BusquedaDeTexto::contiene($q, 'id_proveedor', $patron);
            });
        }

        return response()->json($query->orderBy('descripcion')->limit(20)->get(['id', 'descripcion', 'id_proveedor'])
            ->map(function (Producto $p) {
                return ['id' => $p->id, 'descripcion' => $p->descripcion, 'codigo' => $p->id_proveedor];
            }));
    }

    /**
     * Los destacados sólo cuentan con el orden "a mano"; con otro orden se dejan como
     * están, por si se vuelve a elegir. Un producto que no es de la categoría no se
     * puede destacar en ella.
     */
    private function guardarDestacados(Categoria $categoria, Request $request): void
    {
        if (!$request->has('destacados_enviados')) {
            return;
        }

        $pedidos = array_map('intval', (array) $request->input('destacados', []));
        $validos = Producto::enCategoria($categoria)->whereIn('id', $pedidos)->pluck('id')->all();
        $categoria->destacar(array_values(array_filter($pedidos, function ($id) use ($validos) {
            return in_array($id, $validos, true);
        })));
    }

    /**
     * "Todos los productos": lo que se pide al cargar cualquier producto, tenga la
     * categoría que tenga (las características globales). Se edita con el mismo
     * editor que una categoría, para que todo lo que se pide se configure en
     * Categorías. Ver docs/plan-diseno-panel.md, punto 1.
     */
    public function editarTodos()
    {
        $globales = Etiqueta::where('global', true)->orderBy('nombre')->get();

        return view('admin.categorias.todos', [
            'todasLasCaracteristicas' => Etiqueta::orderBy('nombre')->get(['id', 'nombre']),
            'caracteristicasPropias'  => $globales->map(function (Etiqueta $e) {
                return ['etiqueta_id' => $e->id, 'nombre' => $e->nombre, 'obligatoria' => (bool) $e->obligatoria_global];
            })->values()->all(),
            'heredablesPorMadre'      => [],
            'caracteristicasGlobales' => [],
        ]);
    }

    public function actualizarTodos(Request $request)
    {
        DB::transaction(function () use ($request) {
            $lista = $this->caracteristicasDelFormulario($request);

            // Las que dejaron de pedirse a todos vuelven a ser de las categorías que las elijan.
            Etiqueta::where('global', true)->whereNotIn('id', array_keys($lista) ?: [0])
                ->update(['global' => false, 'obligatoria_global' => false]);

            foreach ($lista as $id => $obligatoria) {
                Etiqueta::where('id', $id)->update(['global' => true, 'obligatoria_global' => $obligatoria]);
            }
        });

        return redirect()->route('admin.categorias.index')->with('success', 'Se guardó lo que se pide en todos los productos.');
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
            'orden_productos'  => 'nullable|in:' . implode(',', array_keys(OrdenDeProductos::OPCIONES)),
            'destacados'       => 'nullable|array|max:100',
            'destacados.*'     => 'integer',
        ], [
            'nombre.required' => 'Poné un nombre a la categoría.',
        ]);

        $datos['parent_id'] = $datos['parent_id'] ?? null;
        $datos['orden_productos'] = $datos['orden_productos'] ?? OrdenDeProductos::NOMBRE;
        unset($datos['destacados']);   // se guardan aparte (guardarDestacados)
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

    /**
     * Lo que necesita la sección "Características que pide": todas las que existen
     * (para agregar), las propias de la categoría y, para cada madre posible, lo que
     * heredaría de ella (cambia si se elige otro "Dentro de").
     */
    private function datosCaracteristicas(Categoria $categoria): array
    {
        $porCategoria = new CaracteristicasPorCategoria();
        $mapa = $porCategoria->mapaParaFormulario();

        $propias = $categoria->exists
            ? $categoria->caracteristicas->map(function (Etiqueta $e) {
                return ['etiqueta_id' => $e->id, 'nombre' => $e->nombre, 'obligatoria' => (bool) $e->pivot->obligatoria];
            })->values()->all()
            : [];

        return [
            'todasLasCaracteristicas' => Etiqueta::orderBy('nombre')->get(['id', 'nombre']),
            'caracteristicasPropias'  => $propias,
            'heredablesPorMadre'      => $mapa['porCategoria'],
            'caracteristicasGlobales' => $mapa['globales'],
        ];
    }

    /**
     * Las características propias, tal como vinieron del formulario y en ese orden.
     * Una escrita que no existe se crea; si ya hay una con ese nombre ("talle" y
     * "Talle"), se usa esa en vez de duplicarla.
     */
    private function guardarCaracteristicas(Categoria $categoria, Request $request): void
    {
        $sync = [];
        foreach ($this->caracteristicasDelFormulario($request) as $id => $obligatoria) {
            $sync[$id] = ['obligatoria' => $obligatoria, 'orden' => count($sync)];
        }

        $categoria->caracteristicas()->sync($sync);
    }

    /**
     * La lista de "Datos que se piden" tal como vino del formulario, en su orden:
     * etiqueta_id => obligatoria. El formulario manda siempre la lista completa (sin
     * filas = no se pide nada). Una escrita que no existe se crea; si ya hay una con
     * ese nombre ("talle" y "Talle"), se usa esa en vez de duplicarla.
     *
     * @return array<int, bool>
     */
    private function caracteristicasDelFormulario(Request $request): array
    {
        $request->validate([
            'caracteristicas'               => 'nullable|array',
            'caracteristicas.*.etiqueta_id' => 'nullable|integer|exists:etiquetas,id',
            'caracteristicas.*.nueva'       => 'nullable|string|max:255',
            'caracteristicas.*.obligatoria' => 'nullable|boolean',
        ]);

        $lista = [];
        foreach (array_values((array) $request->input('caracteristicas')) as $fila) {
            $id = $fila['etiqueta_id'] ?? null;
            if (!$id && trim((string) ($fila['nueva'] ?? '')) !== '') {
                $id = $this->etiquetaPorNombre($fila['nueva'])->id;
            }
            if ($id && !isset($lista[(int) $id])) {
                $lista[(int) $id] = !empty($fila['obligatoria']);
            }
        }

        return $lista;
    }

    private function etiquetaPorNombre(string $nombre): Etiqueta
    {
        $limpio = EtiquetaValor::limpiar($nombre);
        $existente = Etiqueta::all()->first(function (Etiqueta $e) use ($limpio) {
            return EtiquetaValor::normalizar($e->nombre) === EtiquetaValor::normalizar($limpio);
        });

        return $existente ?: Etiqueta::create(['nombre' => $limpio, 'visible_usuarios' => true]);
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
