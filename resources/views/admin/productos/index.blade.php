@extends('layouts.admin')

@section('title', 'Productos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-box-seam"></i> Productos</h3>
    <a href="{{ route('admin.productos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo
    </a>
</div>

@php $filtrosActivos = request()->hasAny(['buscar','proveedor','disponible','stock']); @endphp
<div class="card mb-4">
    <div class="card-header d-flex d-md-none justify-content-between align-items-center py-2 px-3"
         style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#filtros-productos"
         aria-expanded="{{ $filtrosActivos ? 'true' : 'false' }}">
        <span class="small fw-semibold text-muted"><i class="bi bi-funnel me-1"></i> Filtros{!! $filtrosActivos ? ' <span class="badge bg-primary ms-1" style="font-size:.6rem;">activo</span>' : '' !!}</span>
        <i class="bi bi-chevron-down filtros-chevron" style="transition:transform .2s;{{ $filtrosActivos ? 'transform:rotate(180deg);' : '' }}"></i>
    </div>
    <div class="collapse d-md-block{{ $filtrosActivos ? ' show' : '' }}" id="filtros-productos">
    <div class="card-body">
        <form action="{{ route('admin.productos.index') }}" method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label text-muted mb-1" style="font-size:.75rem;">Buscar</label>
                <input type="text" name="buscar" class="form-control" placeholder="Buscar..." value="{{ request('buscar') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted mb-1" style="font-size:.75rem;">Proveedor</label>
                <select name="proveedor" class="form-select">
                    <option value="">Todos</option>
                    @foreach($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}" {{ request('proveedor') == $proveedor->id ? 'selected' : '' }}>
                            {{ $proveedor->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted mb-1" style="font-size:.75rem;">Estado</label>
                <select name="disponible" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" {{ request('disponible') === '1' ? 'selected' : '' }}>Disponibles</option>
                    <option value="0" {{ request('disponible') === '0' ? 'selected' : '' }}>No disponibles</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted mb-1" style="font-size:.75rem;">Stock</label>
                <select name="stock" class="form-select">
                    <option value="">Todos</option>
                    <option value="con" {{ request('stock') === 'con' ? 'selected' : '' }}>Con stock</option>
                    <option value="sin" {{ request('stock') === 'sin' ? 'selected' : '' }}>Sin stock</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1" style="font-size:.75rem; visibility:hidden;">.</label>
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
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
                                        <img src="{{ $producto->imagen_url ?? '/img/no-image.svg' }}"
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
                                <td>{{ $producto->stock }}</td>
                                <td>
                                    <span class="d-flex flex-column gap-1">
                                    @if($producto->disponible)
                                        <span class="badge rounded-pill text-bg-success" style="font-size:.68rem;font-weight:500;">Disponible</span>
                                    @else
                                        <span class="badge rounded-pill text-bg-secondary" style="font-size:.68rem;font-weight:500;">No disponible</span>
                                    @endif
                                    @if($producto->por_encargue)
                                        <span class="badge rounded-pill text-bg-info" style="font-size:.68rem;font-weight:500;">
                                            <i class="bi bi-clock"></i> Por encargue
                                        </span>
                                    @endif
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('tienda.show', $producto) }}" class="btn btn-sm btn-outline-secondary" title="Ver en tienda" target="_blank">
                                            <i class="bi bi-eye"></i>
                                        </a>
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
