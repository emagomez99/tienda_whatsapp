<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etiqueta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EtiquetaController extends Controller
{
    public function index(Request $request)
    {
        $query = Etiqueta::withCount('productos');

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where('nombre', 'like', "%{$buscar}%");
        }

        $etiquetas = $query->orderBy('nombre')->paginate(15);

        return view('admin.etiquetas.index', compact('etiquetas'));
    }

    public function create()
    {
        return view('admin.etiquetas.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:etiquetas,nombre',
            'visible_usuarios' => 'boolean',
        ]);

        $validated['visible_usuarios'] = $request->has('visible_usuarios');

        Etiqueta::create($validated);

        return redirect()->route('admin.etiquetas.index')
            ->with('success', 'Etiqueta creada correctamente');
    }

    public function edit(Etiqueta $etiqueta)
    {
        return view('admin.etiquetas.edit', compact('etiqueta'));
    }

    public function update(Request $request, Etiqueta $etiqueta)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:etiquetas,nombre,' . $etiqueta->id,
            'visible_usuarios' => 'boolean',
        ]);

        $validated['visible_usuarios'] = $request->has('visible_usuarios');

        $etiqueta->update($validated);

        return redirect()->route('admin.etiquetas.index')
            ->with('success', 'Etiqueta actualizada correctamente');
    }

    public function destroy(Etiqueta $etiqueta)
    {
        $etiqueta->delete();

        return redirect()->route('admin.etiquetas.index')
            ->with('success', 'Etiqueta eliminada correctamente');
    }

    public function buscarValores(Request $request, Etiqueta $etiqueta)
    {
        $buscar = $request->get('q', '');

        $valores = DB::table('producto_etiqueta')
            ->where('etiqueta_id', $etiqueta->id)
            ->where('valor', 'like', "%{$buscar}%")
            ->distinct()
            ->pluck('valor')
            ->take(10);

        return response()->json($valores);
    }
}
