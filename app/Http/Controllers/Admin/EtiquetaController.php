<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etiqueta;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class EtiquetaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:etiquetas.ver')->only(['index', 'show', 'buscarValores']);
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

        $etiquetas = $query->orderBy('nombre')->paginate(15);

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

        $valores = $etiqueta->valores()
            ->buscar($buscar)
            ->withCount('asignaciones')
            ->orderBy('valor')
            ->paginate(self::VALORES_POR_PAGINA)
            ->withQueryString();

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
        $proveedores = Proveedor::where('activo', true)->orderBy('nombre')->get();
        return view('admin.etiquetas.create', compact('proveedores'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre'           => 'required|string|max:255|unique:etiquetas,nombre',
            'visible_usuarios' => 'boolean',
        ]);

        $validated['visible_usuarios'] = $request->has('visible_usuarios');

        $etiqueta = Etiqueta::create($validated);

        $this->sincronizarProveedores($etiqueta, $request->input('proveedores_config', []));

        return redirect()->route('admin.etiquetas.index')
            ->with('success', 'Etiqueta creada correctamente');
    }

    public function edit(Etiqueta $etiqueta)
    {
        $etiqueta->load('proveedores');
        $proveedores = Proveedor::where('activo', true)->orderBy('nombre')->get();
        return view('admin.etiquetas.edit', compact('etiqueta', 'proveedores'));
    }

    public function update(Request $request, Etiqueta $etiqueta)
    {
        $validated = $request->validate([
            'nombre'           => 'required|string|max:255|unique:etiquetas,nombre,' . $etiqueta->id,
            'visible_usuarios' => 'boolean',
        ]);

        $validated['visible_usuarios'] = $request->has('visible_usuarios');

        $etiqueta->update($validated);

        $this->sincronizarProveedores($etiqueta, $request->input('proveedores_config', []));

        return redirect()->route('admin.etiquetas.index')
            ->with('success', 'Etiqueta actualizada correctamente');
    }

    public function destroy(Etiqueta $etiqueta)
    {
        $etiqueta->delete();

        return redirect()->route('admin.etiquetas.index')
            ->with('success', 'Etiqueta eliminada correctamente');
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

        return response()->json($valores);
    }

    private function sincronizarProveedores(Etiqueta $etiqueta, array $proveedoresConfig)
    {
        $syncData = [];
        foreach ($proveedoresConfig as $proveedorId => $config) {
            if ($config === 'no') {
                // Store null to distinguish "configured as no aplica" from "never configured"
                $syncData[(int) $proveedorId] = ['obligatoria' => null];
            } else {
                $syncData[(int) $proveedorId] = ['obligatoria' => $config === 'obligatoria'];
            }
        }
        $etiqueta->proveedores()->sync($syncData);
    }
}
