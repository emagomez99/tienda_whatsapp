<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Etiqueta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Support\Paginas;

class EtiquetaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:etiquetas.ver')->only(['index', 'show', 'buscarValores', 'estadoValor']);
        $this->middleware('permiso:etiquetas.crear')->only(['create', 'store']);
        $this->middleware('permiso:etiquetas.editar')->only(['edit', 'update', 'cambiarVisibilidad']);
        $this->middleware('permiso:etiquetas.eliminar')->only(['destroy']);
    }

    /**
     * Valores que se muestran debajo de cada etiqueta en el árbol. Los demás se ven
     * en la página de la etiqueta: Modelo, en oleomc, tiene más de 2.000.
     */
    const VALORES_EN_ARBOL = 30;

    const VALORES_POR_PAGINA = 50;

    /**
     * Árbol de etiquetas con sus valores.
     *
     * La búsqueda encuentra tanto etiquetas como valores. Si coincide el nombre de la
     * etiqueta se muestran todos sus valores; si coincide sólo algún valor, se
     * muestran nada más los que coinciden, que es lo que se estaba buscando.
     */
    public function index(Request $request)
    {
        $buscar = trim((string) $request->get('buscar', ''));

        $query = Etiqueta::withCount(['productos', 'valores']);

        if ($buscar !== '') {
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'ilike', "%{$buscar}%")
                  ->orWhereHas('valores', function ($v) use ($buscar) {
                      $v->buscar($buscar);
                  });
            });
        }

        // Filtros del panel: dónde se pide, si se ve en la tienda, si admite varios valores.
        foreach (['global' => 'global', 'visible' => 'visible_usuarios', 'varios' => 'varios_valores'] as $parametro => $columna) {
            if (in_array($request->query($parametro), ['0', '1'], true)) {
                $query->where($columna, (bool) $request->query($parametro));
            }
        }

        $etiquetas = Paginas::enRango($query->orderBy('nombre')->paginate(15)->withQueryString());

        $valoresPorEtiqueta = [];
        foreach ($etiquetas as $etiqueta) {
            $coincideNombre = $buscar === '' || mb_stripos($etiqueta->nombre, $buscar) !== false;

            $valoresPorEtiqueta[$etiqueta->id] = $etiqueta->valores()
                ->buscar($coincideNombre ? '' : $buscar)
                ->withCount('asignaciones')
                ->orderBy('valor')
                ->limit(self::VALORES_EN_ARBOL)
                ->get();
        }

        return view('admin.etiquetas.index', compact('etiquetas', 'valoresPorEtiqueta', 'buscar'));
    }

    /** Todos los valores de una etiqueta, con búsqueda y paginados. */
    public function show(Request $request, Etiqueta $etiqueta)
    {
        $buscar = trim((string) $request->get('buscar', ''));

        $valores = Paginas::enRango($etiqueta->valores()
            ->buscar($buscar)
            ->when(in_array($request->query('visible'), ['0', '1'], true), function ($q) use ($request) {
                $q->where('visible', (bool) $request->query('visible'));
            })
            ->when($request->query('uso') === 'con', function ($q) { $q->has('asignaciones'); })
            ->when($request->query('uso') === 'sin', function ($q) { $q->doesntHave('asignaciones'); })
            ->withCount('asignaciones')
            ->orderBy('valor')
            ->paginate(self::VALORES_POR_PAGINA)
            ->withQueryString());

        return view('admin.etiquetas.show', compact('etiqueta', 'valores', 'buscar'));
    }

    public function cambiarVisibilidad(Etiqueta $etiqueta)
    {
        $etiqueta->update(['visible_usuarios' => !$etiqueta->visible_usuarios]);

        return back()->with('success', $etiqueta->visible_usuarios
            ? "«{$etiqueta->nombre}» ahora se muestra en la tienda."
            : "«{$etiqueta->nombre}» ya no se muestra en la tienda.");
    }

    public function create()
    {
        return view('admin.etiquetas.create', ['etiqueta' => new Etiqueta()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre'           => 'required|string|max:255|unique:etiquetas,nombre',
            'visible_usuarios' => 'boolean',
            'global'           => 'boolean',
            'obligatoria_global' => 'boolean',
        ]);

        Etiqueta::create($this->conInterruptores($validated, $request));

        return redirect()->route('admin.etiquetas.index')
            ->with('success', 'Característica creada correctamente');
    }

    public function edit(Etiqueta $etiqueta)
    {
        // Las categorías que la piden, en el orden del árbol y con su camino.
        $obligatoriaEn = DB::table('categoria_caracteristica')->where('etiqueta_id', $etiqueta->id)->pluck('obligatoria', 'categoria_id')->all();
        $categoriasQueLaPiden = Categoria::enOrdenDeArbol()
            ->filter(function (Categoria $c) use ($obligatoriaEn) { return array_key_exists($c->id, $obligatoriaEn); })
            ->values();

        return view('admin.etiquetas.edit', compact('etiqueta', 'categoriasQueLaPiden', 'obligatoriaEn'));
    }

    public function update(Request $request, Etiqueta $etiqueta)
    {
        $validated = $request->validate([
            'nombre'           => 'required|string|max:255|unique:etiquetas,nombre,' . $etiqueta->id,
            'visible_usuarios' => 'boolean',
            'global'           => 'boolean',
            'obligatoria_global' => 'boolean',
        ]);

        $datos = $this->conInterruptores($validated, $request);

        // Volver a "un solo valor" dejaría productos con dos: primero hay que elegir uno.
        if ($etiqueta->varios_valores && !$datos['varios_valores']) {
            $conVarios = $etiqueta->productosConVariosValores();
            if ($conVarios > 0) {
                $cuales = '«' . implode('», «', $etiqueta->nombresConVariosValores()) . '»' . ($conVarios > 3 ? ' y otros' : '');
                throw ValidationException::withMessages(['varios_valores' => $conVarios === 1
                    ? "No se cambió: {$cuales} tiene más de un valor. Dejale uno solo y volvé a intentar."
                    : "No se cambió: {$conVarios} productos tienen más de un valor ({$cuales}). Dejales uno solo y volvé a intentar."]);
            }
        }

        $etiqueta->update($datos);

        return redirect()->route('admin.etiquetas.index')
            ->with('success', 'Característica actualizada correctamente');
    }

    /** Los interruptores del formulario; obligatoria sólo tiene sentido si es global. */
    private function conInterruptores(array $validated, Request $request): array
    {
        $validated['visible_usuarios'] = $request->boolean('visible_usuarios');
        $validated['global'] = $request->boolean('global');
        $validated['obligatoria_global'] = $validated['global'] && $request->boolean('obligatoria_global');
        $validated['varios_valores'] = $request->boolean('varios_valores');

        return $validated;
    }

    public function destroy(Etiqueta $etiqueta)
    {
        // Las variantes (Talle M) se eligen por esta característica: sin ella
        // quedarían sin nombre, con su stock, y nadie podría elegirlas.
        $conVariantes = $etiqueta->productosConVariantes();
        if (!empty($conVariantes)) {
            return redirect()->route('admin.etiquetas.index')->with('error',
                "No se borró «{$etiqueta->nombre}»: la usan las variantes de «" . implode('», «', $conVariantes) . '»'
                . (count($conVariantes) >= 3 ? ' y quizás otros' : '') . '. Primero sacá esas variantes del producto.');
        }

        $etiqueta->delete();

        return redirect()->route('admin.etiquetas.index')
            ->with('success', 'Característica eliminada correctamente');
    }

    /**
     * Valores ya usados para esta etiqueta, para el desplegable del formulario.
     *
     * Con q vacío devuelve los primeros valores en orden alfabético, que es lo que
     * permite abrir la lista y mirar qué hay sin tener que adivinar cómo empieza.
     * El corte va en la consulta y no con ->take() sobre la colección: la etiqueta
     * Modelo tiene 2.297 valores distintos y traerlos todos para descartar casi
     * todos en PHP no tiene sentido.
     */
    public function buscarValores(Request $request, Etiqueta $etiqueta)
    {
        $valores = $etiqueta->valoresEnUso((string) $request->get('q', ''), ProductoController::MAX_SUGERENCIAS);

        return response()->json($valores->map->comoSugerencia()->values());
    }

    /** Si lo escrito ya es un valor de la etiqueta o es nuevo (ver EstadoValorEtiqueta). */
    public function estadoValor(Request $request, Etiqueta $etiqueta)
    {
        return response()->json($etiqueta->estadoDeValor((string) $request->get('valor', '')));
    }
}
