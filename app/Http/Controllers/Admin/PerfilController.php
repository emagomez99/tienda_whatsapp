<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perfil;
use App\Models\Permiso;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            'es_superadmin' => $this->puedeTodo() && $request->boolean('es_superadmin'),
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

        // Desactivar un perfil deja afuera a sus usuarios: que nadie se deje afuera a
        // sí mismo ni al perfil que administra todo.
        if (!$request->boolean('activo')) {
            if ($perfil->id === auth()->user()->perfil_id) {
                throw ValidationException::withMessages(['activo' => 'No podés desactivar tu propio perfil: te quedarías sin poder entrar.']);
            }
            if ($perfil->es_superadmin || $request->boolean('es_superadmin')) {
                throw ValidationException::withMessages(['activo' => 'El perfil que administra todo no se puede desactivar.']);
            }
        }

        $perfil->update([
            'nombre'        => $request->nombre,
            'descripcion'   => $request->descripcion,
            'es_superadmin' => $this->puedeTodo() ? $request->boolean('es_superadmin') : $perfil->es_superadmin,
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

    /**
     * Sólo quien ya puede todo decide qué perfil puede todo: si no, alcanzaba con el
     * permiso de gestionar perfiles para darse acceso total.
     */
    private function puedeTodo(): bool
    {
        $usuario = auth()->user();

        return $usuario->esSuperAdmin() || ($usuario->is_admin && !$usuario->perfil_id);
    }
}
