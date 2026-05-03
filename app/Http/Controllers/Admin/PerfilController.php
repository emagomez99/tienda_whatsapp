<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perfil;
use App\Models\Permiso;
use Illuminate\Http\Request;

class PerfilController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:perfiles.ver')->only(['index', 'show']);
        $this->middleware('permiso:perfiles.gestionar')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index()
    {
        $perfiles = Perfil::withCount('usuarios')->orderBy('nombre')->get();
        return view('admin.perfiles.index', compact('perfiles'));
    }

    public function create()
    {
        $catalogo = Permiso::catalogo();
        $permisos = Permiso::all()->keyBy('nombre');
        return view('admin.perfiles.create', compact('catalogo', 'permisos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'       => 'required|string|max:255|unique:perfiles,nombre',
            'descripcion'  => 'nullable|string|max:500',
            'es_superadmin'=> 'boolean',
            'activo'       => 'boolean',
            'permisos'     => 'nullable|array',
            'permisos.*'   => 'exists:permisos,id',
        ]);

        $perfil = Perfil::create([
            'nombre'        => $request->nombre,
            'descripcion'   => $request->descripcion,
            'es_superadmin' => $request->boolean('es_superadmin'),
            'activo'        => $request->boolean('activo', true),
        ]);

        if ($request->filled('permisos')) {
            $perfil->permisos()->sync($request->permisos);
        }

        return redirect()->route('admin.perfiles.index')
            ->with('success', 'Perfil creado correctamente');
    }

    public function edit(Perfil $perfil)
    {
        $perfil->load('permisos', 'usuarios');
        $catalogo = Permiso::catalogo();
        $permisos = Permiso::all()->keyBy('nombre');
        $permisosActivos = $perfil->permisos->pluck('id')->toArray();

        return view('admin.perfiles.edit', compact('perfil', 'catalogo', 'permisos', 'permisosActivos'));
    }

    public function update(Request $request, Perfil $perfil)
    {
        $request->validate([
            'nombre'        => 'required|string|max:255|unique:perfiles,nombre,' . $perfil->id,
            'descripcion'   => 'nullable|string|max:500',
            'es_superadmin' => 'boolean',
            'activo'        => 'boolean',
            'permisos'      => 'nullable|array',
            'permisos.*'    => 'exists:permisos,id',
        ]);

        $perfil->update([
            'nombre'        => $request->nombre,
            'descripcion'   => $request->descripcion,
            'es_superadmin' => $request->boolean('es_superadmin'),
            'activo'        => $request->boolean('activo'),
        ]);

        $perfil->permisos()->sync($request->permisos ?? []);

        return redirect()->route('admin.perfiles.index')
            ->with('success', 'Perfil actualizado correctamente');
    }

    public function destroy(Perfil $perfil)
    {
        if ($perfil->usuarios()->exists()) {
            return redirect()->route('admin.perfiles.index')
                ->with('error', 'No se puede eliminar un perfil con usuarios asignados');
        }

        $perfil->delete();

        return redirect()->route('admin.perfiles.index')
            ->with('success', 'Perfil eliminado correctamente');
    }
}
