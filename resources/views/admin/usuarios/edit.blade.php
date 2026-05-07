@extends('layouts.admin')

@section('title', 'Editar Usuario')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-pencil"></i> Editar Usuario</h3>
    <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.usuarios.update', $usuario) }}" method="POST">
                    @csrf
                    @method('PUT')
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
                            <label for="password" class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password">
                            <small class="text-muted">Dejar en blanco para mantener la actual</small>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password_confirmation" class="form-label">Confirmar Contraseña</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="perfil_id" class="form-label">Perfil</label>
                        <select class="form-select @error('perfil_id') is-invalid @enderror" id="perfil_id" name="perfil_id">
                            <option value="">-- Sin perfil asignado --</option>
                            @foreach($perfiles as $perfil)
                                <option value="{{ $perfil->id }}" {{ old('perfil_id', $usuario->perfil_id) == $perfil->id ? 'selected' : '' }}>
                                    {{ $perfil->nombre }}
                                    @if($perfil->es_superadmin) (Superadmin) @endif
                                </option>
                            @endforeach
                        </select>
                        @error('perfil_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="is_admin" name="is_admin" value="1" {{ old('is_admin', $usuario->is_admin) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_admin">Acceso al panel admin</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" {{ old('activo', $usuario->activo) ? 'checked' : '' }}>
                                <label class="form-check-label" for="activo">Usuario activo</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Actualizar Usuario
                        </button>
                        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
