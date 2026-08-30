@extends('layouts.admin')

@section('title', 'Usuarios')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-people"></i> Usuarios</h3>
    <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo
    </a>
</div>

@php $filtrosActivos = request()->hasAny(['buscar']); @endphp
<div class="card mb-4">
    <div class="card-header d-flex d-md-none justify-content-between align-items-center py-2 px-3"
         style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#filtros-usuarios"
         aria-expanded="{{ $filtrosActivos ? 'true' : 'false' }}">
        <span class="small fw-semibold text-muted"><i class="bi bi-funnel me-1"></i> Filtros{!! $filtrosActivos ? ' <span class="badge bg-primary ms-1" style="font-size:.6rem;">activo</span>' : '' !!}</span>
        <i class="bi bi-chevron-down filtros-chevron" style="transition:transform .2s;{{ $filtrosActivos ? 'transform:rotate(180deg);' : '' }}"></i>
    </div>
    <div class="collapse d-md-block{{ $filtrosActivos ? ' show' : '' }}" id="filtros-usuarios">
    <div class="card-body">
        <form action="{{ route('admin.usuarios.index') }}" method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre o email..." value="{{ request('buscar') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
        </form>
    </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($usuarios->isEmpty())
            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle"></i> No se encontraron usuarios.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Perfil</th>
                            <th>Estado</th>
                            <th>Fecha Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usuarios as $usuario)
                            <tr>
                                <td><strong>{{ $usuario->name }}</strong></td>
                                <td>{{ $usuario->email }}</td>
                                <td>
                                    @if($usuario->perfil)
                                        <span class="badge {{ $usuario->perfil->es_superadmin ? 'bg-danger' : 'bg-primary' }}">
                                            {{ $usuario->perfil->nombre }}
                                        </span>
                                    @elseif($usuario->is_admin)
                                        <span class="badge bg-secondary">Admin (sin perfil)</span>
                                    @else
                                        <span class="badge bg-light text-dark">Sin perfil</span>
                                    @endif
                                </td>
                                <td>
                                    @if($usuario->activo)
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-danger">Inactivo</span>
                                    @endif
                                </td>
                                <td>{{ $usuario->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @if($usuario->id !== auth()->id())
                                            <form action="{{ route('admin.usuarios.destroy', $usuario) }}" method="POST" data-confirmar="¿Eliminar este usuario?" data-confirmar-boton="Sí, eliminar">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center">
                {{ $usuarios->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
