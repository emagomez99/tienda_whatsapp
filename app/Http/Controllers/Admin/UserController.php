<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Support\Paginas;

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

        if (in_array($request->query('activo'), ['0', '1'], true)) {
            $query->where('activo', (bool) $request->query('activo'));
        }
        // "sin" = administradores sin perfil (pueden todo).
        if ($request->query('perfil') === 'sin') {
            $query->whereNull('perfil_id');
        } elseif (ctype_digit((string) $request->query('perfil'))) {
            $query->where('perfil_id', (int) $request->query('perfil'));
        }

        $usuarios = Paginas::enRango($query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString());
        $perfiles = \App\Models\Perfil::orderBy('nombre')
            ->when(!auth()->user()->esSuperAdmin(), function ($q) { $q->where('es_superadmin', false); })
            ->pluck('nombre', 'id');

        return view('admin.usuarios.index', compact('usuarios', 'perfiles'));
    }

    public function create()
    {
        $perfiles = $this->perfilesElegibles();
        $puedeTodo = $this->puedeTodo(auth()->user());

        return view('admin.usuarios.create', compact('perfiles', 'puedeTodo'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'activo'    => 'boolean',
            'perfil_id' => $this->reglaDePerfil(),
        ], $this->mensajes());

        $validated['password']  = Hash::make($validated['password']);
        // Todo usuario que se crea acá es para entrar al panel: qué puede hacer lo dice
        // su perfil, y "Usuario activo" lo deja afuera. (Antes había un tercer
        // interruptor, apagado al crear, que dejaba usuarios que no podían entrar.)
        $validated['is_admin']  = true;
        $validated['activo']    = $request->boolean('activo');
        $validated['perfil_id'] = $this->perfilElegido($request);

        User::create($validated);

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario creado correctamente');
    }

    public function edit(User $usuario)
    {
        $this->abortSiSuperadminAjeno($usuario);
        $perfiles = $this->perfilesElegibles();
        $puedeTodo = $this->puedeTodo(auth()->user());

        return view('admin.usuarios.edit', compact('usuario', 'perfiles', 'puedeTodo'));
    }

    public function update(Request $request, User $usuario)
    {
        $this->abortSiSuperadminAjeno($usuario);
        // En el usuario propio sólo se cambian el nombre, el email y la contraseña:
        // nadie se sube el perfil a sí mismo ni se deja afuera por error (en una
        // tienda con un solo usuario, después no entraba nadie).
        $propio = $usuario->id === auth()->id();

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => ['required', 'email', Rule::unique('users')->ignore($usuario->id)],
            'password'  => 'nullable|string|min:8|confirmed',
            'is_admin'  => 'boolean',
            'activo'    => 'boolean',
            'perfil_id' => $propio ? 'nullable' : $this->reglaDePerfil(),
        ], $this->mensajes());

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if ($propio) {
            unset($validated['is_admin'], $validated['activo'], $validated['perfil_id']);
        } else {
            // El acceso al panel sólo se puede dar (a un usuario viejo que no lo tenía);
            // para sacarlo está "Usuario activo".
            $validated['is_admin']  = $usuario->is_admin || $request->boolean('is_admin');
            $validated['activo']    = $request->boolean('activo');
            $validated['perfil_id'] = $this->perfilElegido($request);
        }

        $usuario->update($validated);

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario actualizado correctamente');
    }

    public function destroy(User $usuario)
    {
        $this->abortSiSuperadminAjeno($usuario);

        if ($usuario->id === auth()->id()) {
            return redirect()->route('admin.usuarios.index')
                ->with('error', 'No podés eliminar tu propio usuario');
        }

        $usuario->delete();

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario eliminado correctamente');
    }

    private function abortSiSuperadminAjeno(User $usuario)
    {
        if ($this->puedeTodo($usuario) && !$this->puedeTodo(auth()->user())) {
            abort(403);
        }
    }

    /** Puede hacer todo: perfil de superadministrador, o administrador sin perfil. */
    private function puedeTodo(User $usuario): bool
    {
        return $usuario->esSuperAdmin() || ($usuario->is_admin && !$usuario->perfil_id);
    }

    /**
     * Los perfiles que puede darle a otro quien está en el panel. Sólo quien puede
     * todo reparte perfiles que pueden todo: si no, cualquiera con permiso para
     * editar usuarios se daba acceso total.
     */
    private function perfilesElegibles()
    {
        return Perfil::where('activo', true)
            ->when(!$this->puedeTodo(auth()->user()), function ($q) { $q->where('es_superadmin', false); })
            ->orderBy('nombre')
            ->get();
    }

    /** Lo que viaja en el formulario cuando se elige "Sin perfil: puede hacer todo". */
    const SIN_PERFIL = 'sin';

    /**
     * Siempre hay que elegir: un perfil o, a propósito, "sin perfil" (que es poder
     * todo y sólo lo elige quien ya puede todo). Antes "sin perfil" era lo que
     * quedaba si no se tocaba nada.
     */
    private function reglaDePerfil(): array
    {
        $elegibles = $this->perfilesElegibles()->pluck('id')->map(function ($id) { return (string) $id; })->all();
        if ($this->puedeTodo(auth()->user())) {
            $elegibles[] = self::SIN_PERFIL;
        }

        return ['required', Rule::in($elegibles)];
    }

    private function perfilElegido(Request $request)
    {
        return $request->input('perfil_id') === self::SIN_PERFIL ? null : (int) $request->input('perfil_id');
    }

    private function mensajes(): array
    {
        return [
            'perfil_id.required' => 'Elegí un perfil: dice qué puede hacer este usuario.',
            'perfil_id.in'       => 'Ese perfil no se puede elegir.',
        ];
    }
}
