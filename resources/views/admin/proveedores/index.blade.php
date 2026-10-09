@extends('layouts.admin')

@section('title', 'Proveedores')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-truck"></i> Proveedores</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-proveedores" titulo="Cómo funcionan los proveedores">
            @include('admin.ayudas.proveedores')
        </x-ayuda>
        <a href="{{ route('admin.proveedores.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Crear proveedor
        </a>
    </div>
</div>

<x-filtros :url="route('admin.proveedores.index')" buscar="Buscar por nombre, contacto o email" titulo="Filtrar proveedores"
           :campos="[['nombre' => 'activo', 'titulo' => 'Estado', 'opciones' => ['1' => 'Activos', '0' => 'Inactivos']]]" />

<div class="card">
    <div class="card-body">
        @if($proveedores->isEmpty())
            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle"></i> No se encontraron proveedores.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Contacto</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Productos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($proveedores as $proveedor)
                            <tr>
                                <td><strong>{{ $proveedor->nombre }}</strong></td>
                                <td>{{ $proveedor->contacto ?? '-' }}</td>
                                <td>{{ $proveedor->telefono ?? '-' }}</td>
                                <td>{{ $proveedor->email ?? '-' }}</td>
                                <td><span class="badge bg-info">{{ $proveedor->productos_count }}</span></td>
                                <td>
                                    @if($proveedor->activo)
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-secondary">Inactivo</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('admin.proveedores.edit', $proveedor) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.proveedores.destroy', $proveedor) }}" method="POST" data-confirmar="¿Eliminar este proveedor?" data-confirmar-boton="Sí, eliminar">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center">
                {{ $proveedores->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
