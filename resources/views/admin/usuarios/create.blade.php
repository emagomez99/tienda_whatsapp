@extends('layouts.admin')

@section('title', 'Nuevo usuario')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-plus-circle"></i> Nuevo usuario</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-usuarios" titulo="Cómo funcionan usuarios y perfiles">
            @include('admin.ayudas.usuarios')
        </x-ayuda>
        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary" title="Volver">
            <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
        </a>
    </div>
</div>

<form action="{{ route('admin.usuarios.store') }}" method="POST" id="form-usuario">
    @csrf
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <label for="name" class="form-label">Nombre *</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Contraseña *</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="password_confirmation" class="form-label">Repetir contraseña *</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="perfil_id" class="form-label">Perfil</label>
                    <select class="form-select @error('perfil_id') is-invalid @enderror" id="perfil_id" name="perfil_id">
                        <option value="" disabled {{ old('perfil_id') ? '' : 'selected' }}>Elegí un perfil…</option>
                        @foreach($perfiles as $perfil)
                            <option value="{{ $perfil->id }}" {{ old('perfil_id') == $perfil->id ? 'selected' : '' }}>
                                {{ $perfil->nombre }}
                                @if($perfil->es_superadmin) (puede hacer todo) @endif
                            </option>
                        @endforeach
                        @if($puedeTodo)
                            <option value="sin" {{ old('perfil_id') === 'sin' ? 'selected' : '' }}>Sin perfil: puede hacer todo</option>
                        @endif
                    </select>
                    <div class="form-text">El perfil dice qué puede hacer este usuario en el panel.</div>
                    @error('perfil_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                {{-- Apagado y con error de validación tiene que volver apagado: un
                     interruptor apagado no viaja, así que old() sólo no alcanza. --}}
                <div class="form-check form-switch mb-3">
                    <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" {{ (session()->hasOldInput() ? old('activo') : true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="activo">Usuario activo</label>
                    <div class="form-text">Si lo apagás, no puede entrar al panel.</div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.barra-guardar', [
    'textoGuardar' => 'Crear usuario',
    'cancelarUrl'  => route('admin.usuarios.index'),
    'formId'       => 'form-usuario',
])
</form>
@endsection
