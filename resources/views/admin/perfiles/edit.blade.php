@extends('layouts.admin')

@section('title', 'Editar perfil')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-pencil"></i> Editar perfil <span class="text-muted fw-normal">{{ $perfil->nombre }}</span></h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-usuarios" titulo="Cómo funcionan usuarios y perfiles">
            @include('admin.ayudas.usuarios')
        </x-ayuda>
        <a href="{{ route('admin.perfiles.index') }}" class="btn btn-outline-secondary" title="Volver">
            <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
        </a>
    </div>
</div>

<form action="{{ route('admin.perfiles.update', $perfil) }}" method="POST" id="form-perfil">
    @csrf
    @method('PUT')
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">Datos del perfil</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control @error('nombre') is-invalid @enderror"
                               id="nombre" name="nombre" value="{{ old('nombre', $perfil->nombre) }}" required>
                        @error('nombre')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <input type="text" class="form-control @error('descripcion') is-invalid @enderror"
                               id="descripcion" name="descripcion" value="{{ old('descripcion', $perfil->descripcion) }}" maxlength="500">
                        @error('descripcion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="es_superadmin"
                                       name="es_superadmin" value="1"
                                       {{ old('es_superadmin', $perfil->es_superadmin) ? 'checked' : '' }}>
                                <label class="form-check-label" for="es_superadmin">
                                    <i class="bi bi-star-fill text-warning"></i> Superadmin
                                    <small class="text-muted d-block">Acceso total, ignora permisos individuales</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="activo"
                                       name="activo" value="1"
                                       {{ old('activo', $perfil->activo) ? 'checked' : '' }}>
                                <label class="form-check-label" for="activo">Perfil activo</label>
                                <small class="text-muted d-block">Si lo apagás, los usuarios con este perfil no pueden entrar al panel.</small>
                                @error('activo')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <span>Permisos</span>
                        @if($perfil->es_superadmin)
                            <span class="badge bg-warning text-dark ms-2">
                                <i class="bi bi-info-circle"></i> Superadmin — tiene todos los permisos automáticamente
                            </span>
                        @endif
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="btn-todos">Marcar todos</button>
                        <button type="button" class="btn btn-outline-secondary" id="btn-ninguno">Desmarcar todos</button>
                    </div>
                </div>
                <div class="card-body">
                    @foreach($catalogo as $grupo => $slugs)
                        <div class="mb-4">
                            <h6 class="panel-rotulo fw-semibold border-bottom pb-1 mb-2">{{ $grupo }}</h6>
                            <div class="row g-2">
                                @foreach($slugs as $slug)
                                    @if(isset($permisos[$slug]))
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input permiso-check"
                                                       id="perm_{{ $permisos[$slug]->id }}"
                                                       name="permisos[]"
                                                       value="{{ $permisos[$slug]->id }}"
                                                       {{ in_array($permisos[$slug]->id, old('permisos', $permisosActivos)) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="perm_{{ $permisos[$slug]->id }}">
                                                    {{ $permisos[$slug]->descripcion }}
                                                    <small class="text-muted">{{ $slug }}</small>
                                                </label>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Usuarios con este perfil</div>
                <div class="card-body">
                    @if($perfil->usuarios->isEmpty())
                        <p class="text-muted mb-0">Ningún usuario asignado.</p>
                    @else
                        <ul class="list-unstyled mb-0">
                            @foreach($perfil->usuarios as $u)
                                <li class="py-1 border-bottom">
                                    <i class="bi bi-person"></i> {{ $u->name }}
                                    <small class="text-muted d-block">{{ $u->email }}</small>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('admin.partials.barra-guardar', [
        'textoGuardar' => 'Guardar cambios',
        'cancelarUrl'  => route('admin.perfiles.index'),
        'formId'       => 'form-perfil',
    ])
</form>
@endsection

@push('scripts')
<script>
document.getElementById('btn-todos').addEventListener('click', function () {
    document.querySelectorAll('.permiso-check').forEach(function (cb) { cb.checked = true; });
});
document.getElementById('btn-ninguno').addEventListener('click', function () {
    document.querySelectorAll('.permiso-check').forEach(function (cb) { cb.checked = false; });
});
</script>
@endpush
