@extends('layouts.admin')

@section('title', 'Productos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-box-seam"></i> Productos</h3>
    <a href="{{ route('admin.productos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo Producto
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('admin.productos.index') }}" method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar..." value="{{ request('buscar') }}">
            </div>
            <div class="col-md-3">
                <select name="proveedor" class="form-select">
                    <option value="">Todos los proveedores</option>
                    @foreach($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}" {{ request('proveedor') == $proveedor->id ? 'selected' : '' }}>
                            {{ $proveedor->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="disponible" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="1" {{ request('disponible') === '1' ? 'selected' : '' }}>Disponibles</option>
                    <option value="0" {{ request('disponible') === '0' ? 'selected' : '' }}>No disponibles</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($productos->isEmpty())
            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle"></i> No se encontraron productos.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Imagen</th>
                            <th>Descripción</th>
                            <th>Cod. Proveedor</th>
                            <th>Proveedor</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($productos as $producto)
                            <tr>
                                <td style="width: 60px;">
                                    <div class="rounded overflow-hidden bg-light" style="width: 50px; height: 50px; flex-shrink: 0;">
                                        <img src="{{ $producto->url_imagen ? $producto->imagen_url : '/img/no-image.svg' }}"
                                             alt=""
                                             style="width: 100%; height: 100%; object-fit: cover; display: block;"
                                             onerror="this.onerror=null;this.src='/img/no-image.svg';">
                                    </div>
                                </td>
                                <td>
                                    <strong>{{ $producto->descripcion }}</strong>
                                </td>
                                <td>{{ $producto->id_proveedor ?? '-' }}</td>
                                <td>{{ $producto->proveedor->nombre ?? '-' }}</td>
                                <td>{{ $producto->precio_con_moneda }}</td>
                                <td>
                                    {{ $producto->stock }}
                                    @if($producto->por_encargue)
                                        <br><small class="text-info">Por encargue</small>
                                    @endif
                                </td>
                                <td>
                                    @if($producto->disponible)
                                        <span class="badge bg-success">Disponible</span>
                                    @else
                                        <span class="badge bg-secondary">No disponible</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('admin.productos.historial', $producto) }}" class="btn btn-sm btn-outline-secondary" title="Historial de movimientos">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="{{ route('admin.productos.edit', $producto) . (request()->getQueryString() ? '?_back=' . urlencode(request()->getQueryString()) : '') }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.productos.destroy', $producto) }}" method="POST" onsubmit="return confirm('¿Eliminar este producto?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
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
            <div class="mt-4">
                {{ $productos->withQueryString()->links('vendor.pagination.tienda') }}
            </div>
        @endif
    </div>
</div>
@endsection
