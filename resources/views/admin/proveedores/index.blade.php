@extends('layouts.admin')

@section('title', 'Proveedores')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-truck"></i> Proveedores</h2>
    <a href="{{ route('admin.proveedores.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo Proveedor
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('admin.proveedores.index') }}" method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre, contacto o email..." value="{{ request('buscar') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
        </form>
    </div>
</div>

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
                                        <form action="{{ route('admin.proveedores.destroy', $proveedor) }}" method="POST" onsubmit="return confirm('¿Eliminar este proveedor?')">
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
