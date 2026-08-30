<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etiqueta;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EtiquetaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:etiquetas.ver')->only(['index', 'show', 'buscarValores']);
        $this->middleware('permiso:etiquetas.crear')->only(['create', 'store']);
        $this->middleware('permiso:etiquetas.editar')->only(['edit', 'update']);
        $this->middleware('permiso:etiquetas.eliminar')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Etiqueta::withCount('productos');

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where('nombre', 'ilike', "%{$buscar}%");
        }

        $etiquetas = $query->orderBy('nombre')->paginate(15);

        return view('admin.etiquetas.index', compact('etiquetas'));
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
        $buscar = trim($request->get('q', ''));

        $query = DB::table('producto_etiqueta')
            ->select('valor')
            ->distinct()
            ->where('etiqueta_id', $etiqueta->id)
            ->where('valor', '!=', '');

        if ($buscar !== '') {
            $query->where('valor', 'ilike', "%{$buscar}%");
        }

        $valores = $query->orderBy('valor')
            ->limit(ProductoController::MAX_SUGERENCIAS)
            ->pluck('valor');

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
