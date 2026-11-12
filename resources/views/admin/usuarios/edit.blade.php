@extends('layouts.admin')

@section('title', 'Editar usuario')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-pencil"></i> Editar usuario <span class="text-muted fw-normal">{{ $usuario->name }}</span></h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-usuarios" titulo="Cómo funcionan usuarios y perfiles">
            @include('admin.ayudas.usuarios')
        </x-ayuda>
        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary" title="Volver">
            <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
        </a>
    </div>
</div>

<form action="{{ route('admin.usuarios.update', $usuario) }}" method="POST" id="form-usuario">
    @csrf
    @method('PUT')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <label for="name" class="form-label">Nombre *</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $usuario->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $usuario->email) }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Nueva contraseña</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password">
                        <small class="text-muted">Dejala vacía para no cambiarla</small>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="password_confirmation" class="form-label">Repetir contraseña</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
                    </div>
                </div>
                @if($usuario->id === auth()->id())
                    <div class="alert alert-light border small mb-0">
                        <i class="bi bi-info-circle"></i> Éste es tu usuario: tu perfil y tu acceso los cambia otro usuario del panel, para que no te quedes afuera por error.
                    </div>
                @else
                <div class="mb-3">
                    <label for="perfil_id" class="form-label">Perfil</label>
                    <select class="form-select @error('perfil_id') is-invalid @enderror" id="perfil_id" name="perfil_id">
                        @if($puedeTodo)
                            <option value="">Sin perfil: puede hacer todo</option>
                        @else
                            <option value="" disabled {{ old('perfil_id', $usuario->perfil_id) ? '' : 'selected' }}>Elegí un perfil…</option>
                        @endif
                        @foreach($perfiles as $perfil)
                            <option value="{{ $perfil->id }}" {{ old('perfil_id', $usuario->perfil_id) == $perfil->id ? 'selected' : '' }}>
                                {{ $perfil->nombre }}
                                @if($perfil->es_superadmin) (puede hacer todo) @endif
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">El perfil dice qué puede hacer este usuario en el panel.</div>
                    @error('perfil_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                {{-- Apagado y con error de validación tiene que volver apagado: un
                     interruptor apagado no viaja, así que old() sólo no alcanza. --}}
                @if(!$usuario->is_admin)
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" class="form-check-input" id="is_admin" name="is_admin" value="1" {{ old('is_admin') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_admin">Puede entrar al panel</label>
                        <div class="form-text">Este usuario todavía no puede entrar. Prendelo para dejarlo pasar.</div>
                    </div>
                @endif
                <div class="form-check form-switch mb-3">
                    <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" {{ (session()->hasOldInput() ? old('activo') : $usuario->activo) ? 'checked' : '' }}>
                    <label class="form-check-label" for="activo">Usuario activo</label>
                    <div class="form-text">Si lo apagás, no puede entrar al panel.</div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@include('admin.partials.barra-guardar', [
    'textoGuardar' => 'Guardar cambios',
    'cancelarUrl'  => route('admin.usuarios.index'),
    'formId'       => 'form-usuario',
])
</form>
@endsection
