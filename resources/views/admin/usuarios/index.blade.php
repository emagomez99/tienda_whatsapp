@extends('layouts.admin')

@section('title', 'Usuarios')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-people"></i> Usuarios</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-usuarios" titulo="Cómo funcionan usuarios y perfiles">
            @include('admin.ayudas.usuarios')
        </x-ayuda>
        @if(auth()->user()->puede('usuarios.crear'))
        <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Crear usuario
        </a>
        @endif
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
            @include('admin.partials.vacio', ['icono' => 'bi-people', 'filtrado' => 'Ningún usuario coincide con lo que buscás', 'nada' => 'Todavía no hay usuarios', 'url' => route('admin.usuarios.index')])
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th class="d-none d-md-table-cell">Email</th>
                            <th>Perfil</th>
                            <th class="d-none d-md-table-cell">Estado</th>
                            <th class="d-none d-md-table-cell">Creado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usuarios as $usuario)
                            <tr>
                                {{-- En el celular el email baja debajo del nombre. --}}
                                <td>
                                    <strong>{{ $usuario->name }}</strong>
                                    @if(!$usuario->activo)
                                        <span class="badge bg-danger d-md-none">Inactivo</span>
                                    @endif
                                    <div class="small text-muted text-break d-md-none">{{ $usuario->email }}</div>
                                </td>
                                <td class="d-none d-md-table-cell">{{ $usuario->email }}</td>
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
                                <td class="d-none d-md-table-cell">
                                    @if($usuario->activo)
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-danger">Inactivo</span>
                                    @endif
                                </td>
                                <td class="d-none d-md-table-cell">{{ $usuario->created_at->format('d/m/Y') }}</td>
                                <td>
                                    <div class="btn-group">
                                        @if(auth()->user()->puede('usuarios.editar'))
                                        <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="btn btn-sm btn-outline-primary" title="Editar" aria-label="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @endif
                                        @if($usuario->id !== auth()->id())
                                            @if(auth()->user()->puede('usuarios.eliminar'))
                                            <form action="{{ route('admin.usuarios.destroy', $usuario) }}" method="POST" data-confirmar="¿Eliminar el usuario «{{ $usuario->name }}»?" data-confirmar-boton="Sí, eliminar">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar" aria-label="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            @endif
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
