@extends('layouts.admin')

@section('title', 'Perfiles')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-shield-check"></i> Perfiles</h3>
    @if(auth()->user()->puede('perfiles.gestionar'))
    <a href="{{ route('admin.perfiles.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo
    </a>
    @endif
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
                            <th>Descripción</th>
                            <th>Tipo</th>
                            <th>Usuarios</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($perfiles as $perfil)
                            <tr>
                                <td><strong>{{ $perfil->nombre }}</strong></td>
                                <td class="text-muted">{{ $perfil->descripcion ?? '—' }}</td>
                                <td>
                                    @if($perfil->es_superadmin)
                                        <span class="badge bg-danger">
                                            <i class="bi bi-star-fill"></i> Superadmin
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">Estándar</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">{{ $perfil->usuarios_count }}</span>
                                </td>
                                <td>
                                    @if($perfil->activo)
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-danger">Inactivo</span>
                                    @endif
                                </td>
                                <td>
                                    @if(auth()->user()->puede('perfiles.gestionar'))
                                    <div class="btn-group">
                                        <a href="{{ route('admin.perfiles.edit', $perfil) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @if($perfil->usuarios_count === 0)
                                            <form action="{{ route('admin.perfiles.destroy', $perfil) }}" method="POST" onsubmit="return confirm('¿Eliminar este perfil?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
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
