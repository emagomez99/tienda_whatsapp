<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:usuarios.ver')->only(['index', 'show']);
        $this->middleware('permiso:usuarios.crear')->only(['create', 'store']);
        $this->middleware('permiso:usuarios.editar')->only(['edit', 'update']);
        $this->middleware('permiso:usuarios.eliminar')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = User::with('perfil');

        // Usuarios no-superadmin solo ven usuarios no-superadmin
        if (!auth()->user()->esSuperAdmin()) {
            $query->where(function ($q) {
                $q->whereNull('perfil_id')
                  ->orWhereHas('perfil', function ($q2) {
                      $q2->where('es_superadmin', false);
                  });
            });
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('name', 'ilike', "%{$buscar}%")
                  ->orWhere('email', 'ilike', "%{$buscar}%");
            });
        }

        $usuarios = $query->orderBy('name')->paginate(15);

        return view('admin.usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $perfiles = Perfil::where('activo', true)->orderBy('nombre')->get();
        return view('admin.usuarios.create', compact('perfiles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'is_admin'  => 'boolean',
            'activo'    => 'boolean',
            'perfil_id' => 'nullable|exists:perfiles,id',
        ]);

        $validated['password']  = Hash::make($validated['password']);
        $validated['is_admin']  = $request->boolean('is_admin');
        $validated['activo']    = $request->boolean('activo');
        $validated['perfil_id'] = $request->filled('perfil_id') ? $request->perfil_id : null;

        User::create($validated);

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario creado correctamente');
    }

    public function edit(User $usuario)
    {
        $this->abortSiSuperadminAjeno($usuario);
        $perfiles = Perfil::where('activo', true)->orderBy('nombre')->get();
        return view('admin.usuarios.edit', compact('usuario', 'perfiles'));
    }

    public function update(Request $request, User $usuario)
    {
        $this->abortSiSuperadminAjeno($usuario);
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => ['required', 'email', Rule::unique('users')->ignore($usuario->id)],
            'password'  => 'nullable|string|min:8|confirmed',
            'is_admin'  => 'boolean',
            'activo'    => 'boolean',
            'perfil_id' => 'nullable|exists:perfiles,id',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['is_admin']  = $request->boolean('is_admin');
        $validated['activo']    = $request->boolean('activo');
        $validated['perfil_id'] = $request->filled('perfil_id') ? $request->perfil_id : null;

        $usuario->update($validated);

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario actualizado correctamente');
    }

    public function destroy(User $usuario)
    {
        $this->abortSiSuperadminAjeno($usuario);

        if ($usuario->id === auth()->id()) {
            return redirect()->route('admin.usuarios.index')
                ->with('error', 'No puedes eliminar tu propio usuario');
        }

        $usuario->delete();

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario eliminado correctamente');
    }

    private function abortSiSuperadminAjeno(User $usuario)
    {
        if ($usuario->esSuperAdmin() && !auth()->user()->esSuperAdmin()) {
            abort(403);
        }
    }
}
