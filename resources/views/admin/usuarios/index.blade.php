@extends('layouts.admin')

@section('title', 'Usuarios')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-people"></i> Usuarios</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-usuarios" titulo="Cómo funcionan usuarios y perfiles">
            @include('admin.ayudas.usuarios')
        </x-ayuda>
        <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Crear usuario
        </a>
    </div>
</div>

<x-filtros :url="route('admin.usuarios.index')" buscar="Buscar por nombre o email" titulo="Filtrar usuarios"
           :campos="[
               ['nombre' => 'activo', 'titulo' => 'Estado', 'opciones' => ['1' => 'Activos', '0' => 'Inactivos']],
               ['nombre' => 'perfil', 'titulo' => 'Perfil', 'lista' => true, 'opciones' => ['sin' => 'Sin perfil (pueden todo)'] + $perfiles->all()],
           ]" />

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
