@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
</div>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4>{{ $stats['productos'] }}</h4>
                        <p class="mb-0">Productos Totales</p>
                    </div>
                    <i class="bi bi-box-seam" style="font-size: 2rem;"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="{{ route('admin.productos.index') }}" class="text-white">Ver productos <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4>{{ $stats['productos_disponibles'] }}</h4>
                        <p class="mb-0">Productos Disponibles</p>
                    </div>
                    <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4>{{ $stats['proveedores'] }}</h4>
                        <p class="mb-0">Proveedores Activos</p>
                    </div>
                    <i class="bi bi-truck" style="font-size: 2rem;"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="{{ route('admin.proveedores.index') }}" class="text-white">Ver proveedores <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4>{{ $stats['productos_sin_stock'] }}</h4>
                        <p class="mb-0">Sin Stock</p>
                    </div>
                    <i class="bi bi-exclamation-triangle" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Productos Recientes</h5>
            </div>
            <div class="card-body">
                @if($productosRecientes->isEmpty())
                    <p class="text-muted">No hay productos registrados.</p>
                @else
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Proveedor</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($productosRecientes as $producto)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.productos.edit', $producto) }}">{{ $producto->descripcion }}</a>
                                    </td>
                                    <td>{{ $producto->proveedor->nombre ?? '-' }}</td>
                                    <td>{{ $producto->precio_con_moneda }}</td>
                                    <td>{{ $producto->stock }}</td>
                                    <td>
                                        @if($producto->disponible)
                                            <span class="badge bg-success">Disponible</span>
                                        @else
                                            <span class="badge bg-secondary">No disponible</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning"></i> Acciones Rápidas</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('admin.productos.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Nuevo Producto
                    </a>
                    <a href="{{ route('admin.proveedores.create') }}" class="btn btn-outline-primary">
                        <i class="bi bi-plus-circle"></i> Nuevo Proveedor
                    </a>
                    <a href="{{ route('admin.etiquetas.create') }}" class="btn btn-outline-primary">
                        <i class="bi bi-plus-circle"></i> Nueva Etiqueta
                    </a>
                    <a href="{{ route('admin.configuraciones.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-sliders"></i> Configuración
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
