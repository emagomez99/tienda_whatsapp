@extends('layouts.admin')

@section('title', 'Perfiles')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-shield-check"></i> Perfiles</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-usuarios" titulo="Cómo funcionan usuarios y perfiles">
            @include('admin.ayudas.usuarios')
        </x-ayuda>
        @if(auth()->user()->puede('perfiles.gestionar'))
        <a href="{{ route('admin.perfiles.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Crear perfil
        </a>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($perfiles->isEmpty())
            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle"></i> No hay perfiles definidos.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th class="d-none d-md-table-cell">Descripción</th>
                            <th class="d-none d-md-table-cell">Tipo</th>
                            <th>Usuarios</th>
                            <th class="d-none d-md-table-cell">Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($perfiles as $perfil)
                            <tr>
                                {{-- En el celular la descripción baja debajo del nombre. --}}
                                <td>
                                    <strong>{{ $perfil->nombre }}</strong>
                                    @if(!$perfil->activo)
                                        <span class="badge bg-danger d-md-none">Inactivo</span>
                                    @endif
                                    @if($perfil->descripcion)
                                        <div class="small text-muted d-md-none">{{ $perfil->descripcion }}</div>
                                    @endif
                                </td>
                                <td class="text-muted d-none d-md-table-cell">{{ $perfil->descripcion ?? '—' }}</td>
                                <td class="d-none d-md-table-cell">
                                    @if($perfil->es_superadmin)
                                        <span class="badge bg-danger">
                                            <i class="bi bi-star-fill"></i> Puede todo
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark">Según sus permisos</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">{{ $perfil->usuarios_count }}</span>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    @if($perfil->activo)
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-danger">Inactivo</span>
                                    @endif
                                </td>
                                <td>
                                    @if(auth()->user()->puede('perfiles.gestionar'))
                                    <div class="btn-group">
                                        <a href="{{ route('admin.perfiles.edit', $perfil) }}" class="btn btn-sm btn-outline-primary" title="Editar" aria-label="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @if($perfil->usuarios_count === 0)
                                            <form action="{{ route('admin.perfiles.destroy', $perfil) }}" method="POST" data-confirmar="¿Eliminar el perfil «{{ $perfil->nombre }}»?" data-confirmar-boton="Sí, eliminar">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar" aria-label="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
