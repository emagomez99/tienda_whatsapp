<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $query = Proveedor::withCount('productos');

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('contacto', 'like', "%{$buscar}%")
                  ->orWhere('email', 'like', "%{$buscar}%");
            });
        }

        $proveedores = $query->orderBy('nombre')->paginate(15);

        return view('admin.proveedores.index', compact('proveedores'));
    }

    public function create()
    {
        return view('admin.proveedores.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'contacto' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'notas' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->boolean('activo');

        Proveedor::create($validated);

        return redirect()->route('admin.proveedores.index')
            ->with('success', 'Proveedor creado correctamente');
    }

    public function edit(Proveedor $proveedor)
    {
        return view('admin.proveedores.edit', compact('proveedor'));
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'contacto' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'notas' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->boolean('activo');

        $proveedor->update($validated);

        return redirect()->route('admin.proveedores.index')
            ->with('success', 'Proveedor actualizado correctamente');
    }

    public function destroy(Proveedor $proveedor)
    {
        if ($proveedor->productos()->count() > 0) {
            return redirect()->route('admin.proveedores.index')
                ->with('error', 'No se puede eliminar el proveedor porque tiene productos asociados');
        }

        $proveedor->delete();

        return redirect()->route('admin.proveedores.index')
            ->with('success', 'Proveedor eliminado correctamente');
    }
}
