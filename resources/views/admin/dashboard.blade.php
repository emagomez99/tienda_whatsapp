@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-speedometer2"></i> Dashboard</h3>
    <small class="text-muted">{{ now()->translatedFormat('l j \d\e F') }}</small>
</div>

{{-- Stats compactos --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md-2">
        <a href="{{ route('admin.productos.index') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="text-muted mb-1" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em">Productos</div>
                    <div class="fs-4 fw-bold text-primary lh-1">{{ $stats['productos'] }}</div>
                    <div class="text-success mt-1" style="font-size:.75rem;">
                        <i class="bi bi-check-circle-fill"></i> {{ $stats['productos_disponibles'] }} disponibles
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="{{ route('admin.productos.index', ['stock' => 'sin']) }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 {{ $stats['productos_sin_stock'] > 0 ? 'border-warning border' : '' }}">
            <div class="card-body py-2 px-3">
                <div class="text-muted mb-0" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em">Sin stock</div>
                <div class="d-flex align-items-center gap-1">
                    <span class="fs-4 fw-bold {{ $stats['productos_sin_stock'] > 0 ? 'text-warning' : 'text-secondary' }} lh-1">
                        {{ $stats['productos_sin_stock'] }}
                    </span>
                    @if($stats['productos_sin_stock'] > 0)
                        <i class="bi bi-exclamation-triangle-fill text-warning small"></i>
                    @endif
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="{{ route('admin.proveedores.index') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="text-muted mb-0" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em">Proveedores</div>
                    <span class="fs-4 fw-bold text-info lh-1">{{ $stats['proveedores'] }}</span>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="{{ route('admin.pedidos.index', ['estado' => 'pendiente']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 {{ $pedidosStats['pendientes'] > 0 ? 'border-warning border' : '' }}">
                <div class="card-body py-2 px-3">
                    <div class="text-muted mb-0" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em">Pendientes</div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="fs-4 fw-bold {{ $pedidosStats['pendientes'] > 0 ? 'text-warning' : 'text-secondary' }} lh-1">
                            {{ $pedidosStats['pendientes'] }}
                        </span>
                        @if($pedidosStats['pendientes'] > 0)
                            <i class="bi bi-hourglass-split text-warning small"></i>
                        @endif
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="{{ route('admin.pedidos.index', ['estado' => 'confirmado']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="text-muted mb-0" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em">Confirmados</div>
                    <span class="fs-4 fw-bold text-success lh-1">{{ $pedidosStats['confirmados'] }}</span>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm h-100 bg-success text-white">
            <div class="card-body py-2 px-3">
                <div class="mb-0" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;opacity:.85">
                    Facturado {{ now()->translatedFormat('M') }}
                </div>
                <span class="fs-5 fw-bold lh-1">${{ number_format($pedidosStats['total_mes'], 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
</div>

{{-- Contenido principal --}}
<div class="row g-3">
    {{-- Pedidos recientes --}}
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="fw-semibold"><i class="bi bi-bag-check"></i> Últimos pedidos</span>
                <a href="{{ route('admin.pedidos.index') }}" class="btn btn-sm btn-outline-primary py-0">Ver todos</a>
            </div>
            <div class="card-body p-0">
                @if($pedidosRecientes->isEmpty())
                    <p class="text-muted p-3 mb-0 small">No hay pedidos aún.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Cliente</th>
                                    <th class="d-none d-md-table-cell">Total</th>
                                    <th>Estado</th>
                                    <th class="d-none d-md-table-cell">Fecha</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($pedidosRecientes as $pedido)
                                <tr>
                                    <td class="ps-3"><code class="small">#{{ $pedido->id }}</code></td>
                                    <td class="small">{{ $pedido->nombre }} {{ $pedido->apellido }}</td>
                                    <td class="small d-none d-md-table-cell">${{ number_format($pedido->total, 2) }}</td>
                                    <td>
                                        @if($pedido->esPendiente())
                                            <span class="badge bg-warning text-dark">Pendiente</span>
                                        @elseif($pedido->esConfirmado())
                                            <span class="badge bg-success">Confirmado</span>
                                        @else
                                            <span class="badge bg-danger">Cancelado</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted d-none d-md-table-cell">{{ $pedido->created_at->format('d/m H:i') }}</td>
                                    <td>
                                        <a href="{{ route('admin.pedidos.show', $pedido) }}" class="btn btn-sm btn-outline-primary py-0 px-2">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Productos recientes --}}
        <div class="card shadow-sm mt-3">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="fw-semibold"><i class="bi bi-clock-history"></i> Productos recientes</span>
                <a href="{{ route('admin.productos.index') }}" class="btn btn-sm btn-outline-secondary py-0">Ver todos</a>
            </div>
            <div class="card-body p-0">
                @if($productosRecientes->isEmpty())
                    <p class="text-muted p-3 mb-0 small">No hay productos registrados.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Descripción</th>
                                    <th class="d-none d-md-table-cell">Proveedor</th>
                                    <th>Precio</th>
                                    <th class="d-none d-md-table-cell">Stock</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($productosRecientes as $producto)
                                    <tr>
                                        <td class="ps-3">
                                            <a href="{{ route('admin.productos.edit', $producto) }}" class="small">{{ $producto->descripcion }}</a>
                                        </td>
                                        <td class="small text-muted d-none d-md-table-cell">{{ $producto->proveedor->nombre ?? '-' }}</td>
                                        <td class="small">{{ $producto->precio_con_moneda }}</td>
                                        <td class="small d-none d-md-table-cell">{{ $producto->stock }}</td>
                                        <td>
                                            @if($producto->disponible)
                                                <span class="badge bg-success">Disponible</span>
                                            @else
                                                <span class="badge bg-secondary">No disp.</span>
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
    </div>

    {{-- Panel lateral --}}
    <div class="col-md-4">
        {{-- Acciones rápidas --}}
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2">
                <span class="fw-semibold"><i class="bi bi-lightning"></i> Acciones rápidas</span>
            </div>
            <div class="card-body py-2">
                <div class="d-grid gap-2">
                    <a href="{{ route('admin.productos.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle"></i> Nuevo Producto
                    </a>
                    <a href="{{ route('admin.proveedores.create') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-plus-circle"></i> Nuevo Proveedor
                    </a>
                    <a href="{{ route('admin.pedidos.create') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-plus-circle"></i> Nuevo Pedido
                    </a>
                    <a href="{{ route('admin.configuraciones.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-sliders"></i> Configuración
                    </a>
                </div>
            </div>
        </div>

        {{-- Resumen pedidos --}}
        <div class="card shadow-sm">
            <div class="card-header py-2">
                <span class="fw-semibold"><i class="bi bi-bar-chart"></i> Estado de pedidos</span>
            </div>
            <div class="card-body p-0">
                <a href="{{ route('admin.pedidos.index', ['estado' => 'pendiente']) }}" class="text-decoration-none">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <span class="small"><i class="bi bi-hourglass-split text-warning me-1"></i> Pendientes</span>
                        <span class="badge bg-warning text-dark">{{ $pedidosStats['pendientes'] }}</span>
                    </div>
                </a>
                <a href="{{ route('admin.pedidos.index', ['estado' => 'confirmado']) }}" class="text-decoration-none">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <span class="small"><i class="bi bi-check-circle text-success me-1"></i> Confirmados</span>
                        <span class="badge bg-success">{{ $pedidosStats['confirmados'] }}</span>
                    </div>
                </a>
                <a href="{{ route('admin.pedidos.index', ['estado' => 'cancelado']) }}" class="text-decoration-none">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2">
                        <span class="small"><i class="bi bi-x-circle text-danger me-1"></i> Cancelados</span>
                        <span class="badge bg-danger">{{ $pedidosStats['cancelados'] }}</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
