@extends('layouts.admin')

@section('title', 'Dashboard')

@push('styles')
<style>
    .accion-rapida {
        display: flex;
        align-items: center;
        gap: .7rem;
        padding: .65rem .9rem;
        text-decoration: none;
        color: inherit;
        border-bottom: 1px solid rgba(0,0,0,.055);
        transition: background-color .15s ease;
    }
    .accion-rapida:last-child { border-bottom: 0; }
    .accion-rapida:hover  { background-color: rgba(0,0,0,.03); color: inherit; }
    .accion-rapida:focus-visible {
        outline: 2px solid var(--bs-primary);
        outline-offset: -2px;
    }
    /* La destacada se marca con un filete lateral y no con un fondo fuerte: se
       distingue igual y no compite con los badges de estado de la pantalla. */
    .accion-rapida.destacada {
        box-shadow: inset 3px 0 0 var(--bs-primary);
        background-color: rgba(var(--bs-primary-rgb), .04);
    }
    .accion-rapida.destacada:hover { background-color: rgba(var(--bs-primary-rgb), .09); }

    .accion-icono {
        flex: 0 0 2.1rem;
        height: 2.1rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: .5rem;
        font-size: .95rem;
    }
    .accion-texto  { flex: 1 1 auto; min-width: 0; line-height: 1.25; }
    .accion-titulo { display: block; font-size: .875rem; font-weight: 600; }
    .accion-detalle {
        display: block;
        font-size: .72rem;
        color: var(--bs-secondary-color, #6c757d);
    }
    .accion-flecha {
        color: #adb5bd;
        font-size: .8rem;
        transition: transform .15s ease;
    }
    .accion-rapida:hover .accion-flecha { transform: translateX(3px); }

    @media (prefers-reduced-motion: reduce) {
        .accion-rapida, .accion-flecha { transition: none; }
        .accion-rapida:hover .accion-flecha { transform: none; }
    }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-speedometer2"></i> Dashboard</h3>
        <small class="text-muted">{{ now()->translatedFormat('l j \d\e F') }}</small>
    </div>

    {{-- Mes de las ventas: cambia Confirmados, Facturado y la lista de pedidos. --}}
    <form method="GET" action="{{ route('admin.dashboard') }}" class="d-flex align-items-center gap-1" aria-label="Mes de las estadísticas">
        @if($hayMesAnterior)
            <a href="{{ route('admin.dashboard', ['mes' => $mes->anterior()->parametro()]) }}"
               class="btn btn-sm btn-outline-secondary" title="Mes anterior" aria-label="Mes anterior">
                <i class="bi bi-chevron-left"></i>
            </a>
        @else
            <span class="btn btn-sm btn-outline-secondary disabled" aria-hidden="true"><i class="bi bi-chevron-left"></i></span>
        @endif

        <select name="mes" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
            @foreach($meses as $opcion)
                <option value="{{ $opcion->parametro() }}" {{ $opcion->equals($mes) ? 'selected' : '' }}>
                    {{ ucfirst($opcion->nombre()) }}
                </option>
            @endforeach
        </select>

        @if($mes->siguiente())
            <a href="{{ route('admin.dashboard', ['mes' => $mes->siguiente()->parametro()]) }}"
               class="btn btn-sm btn-outline-secondary" title="Mes siguiente" aria-label="Mes siguiente">
                <i class="bi bi-chevron-right"></i>
            </a>
        @else
            <span class="btn btn-sm btn-outline-secondary disabled" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
        @endif
    </form>
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
        <a href="{{ route('admin.pedidos.index', ['estado' => 'confirmado'] + $rangoDelMes) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="text-muted mb-0" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em">Confirmados {{ $mes->nombreCorto() }}</div>
                    <span class="fs-4 fw-bold text-success lh-1">{{ $pedidosStats['confirmados'] }}</span>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm h-100 bg-success text-white">
            <div class="card-body py-2 px-3">
                <div class="mb-1" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;opacity:.85">
                    Facturado {{ $mes->nombreCorto() }}
                </div>
                @forelse($pedidosStats['totales_mes'] as $tm)
                    <div class="fw-bold lh-1 {{ $loop->first ? 'fs-5' : 'fs-6 mt-1' }}">
                        {{ $tm->moneda_simbolo ?? '$' }}{{ number_format($tm->total, 0, ',', '.') }}
                    </div>
                @empty
                    <span class="fs-5 fw-bold lh-1">$0</span>
                @endforelse
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
                <span class="fw-semibold">
                    <i class="bi bi-bag-check"></i>
                    {{ $mes->esActual() ? 'Últimos pedidos' : 'Pedidos de ' . $mes->nombre() }}
                </span>
                <a href="{{ route('admin.pedidos.index') }}" class="btn btn-sm btn-outline-primary py-0">Ver todos</a>
            </div>
            <div class="card-body p-0">
                @if($pedidosRecientes->isEmpty())
                    <p class="text-muted p-3 mb-0 small">{{ $mes->esActual() ? 'No hay pedidos aún.' : 'No hubo pedidos en ' . $mes->nombre() . '.' }}</p>
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
                                    <td class="small d-none d-md-table-cell">
                                        @foreach($pedido->totales as $pt)
                                            <div class="lh-sm">{{ $pt->moneda ? $pt->moneda->simbolo : '$' }}{{ number_format($pt->total, 2, ',', '.') }}</div>
                                        @endforeach
                                    </td>
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
        {{-- Acciones rápidas.

             Cada acción es una fila con ícono, qué hace y adónde lleva, en vez de
             cuatro botones iguales apilados: con botones idénticos había que leer el
             texto de cada uno para distinguirlos. El pedido va primero porque es lo
             que más se hace en el día, y es la única destacada -- si se destacan todas,
             no se destaca ninguna.

             Sólo se muestran las acciones que el usuario puede ejecutar: ofrecer un
             botón que después devuelve "no tenés permiso" es peor que no ofrecerlo. --}}
        @php
            // auth()->user() y no $user: el layout define esa variable en su propio
            // cuerpo, que Blade evalúa DESPUÉS de capturar esta sección.
            $usuario  = auth()->user();
            $acciones = [];

            if ($usuario->puede('pedidos.gestionar')) {
                $acciones[] = [
                    'url'      => route('admin.pedidos.create'),
                    'icono'    => 'bi-bag-plus',
                    'titulo'   => 'Nuevo Pedido',
                    'detalle'  => 'Cargar un nuevo pedido',
                    'destacar' => true,
                ];
            }

            if ($usuario->puede('productos.crear')) {
                $acciones[] = [
                    'url'     => route('admin.productos.create'),
                    'icono'   => 'bi-box-seam',
                    'titulo'  => 'Nuevo Producto',
                    'detalle' => 'Sumar un artículo al catálogo',
                ];
            }

            if ($usuario->puede('proveedores.crear')) {
                $acciones[] = [
                    'url'     => route('admin.proveedores.create'),
                    'icono'   => 'bi-truck',
                    'titulo'  => 'Nuevo Proveedor',
                    'detalle' => 'Dar de alta un nuevo proveedor',
                ];
            }

            if ($usuario->puede('configuraciones.ver')) {
                $acciones[] = [
                    'url'     => route('admin.configuraciones.index'),
                    'icono'   => 'bi-sliders',
                    'titulo'  => 'Configuración',
                    'detalle' => 'Ir a los ajustes de la tienda',
                ];
            }
        @endphp

        @if(count($acciones))
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2">
                <span class="fw-semibold"><i class="bi bi-lightning-charge-fill text-warning"></i> Acciones rápidas</span>
            </div>
            <div class="card-body p-0">
                @foreach($acciones as $accion)
                    @php $destacar = !empty($accion['destacar']); @endphp
                    <a href="{{ $accion['url'] }}" class="accion-rapida{{ $destacar ? ' destacada' : '' }}">
                        <span class="accion-icono {{ $destacar ? 'bg-primary text-white' : 'bg-primary bg-opacity-10 text-primary' }}">
                            <i class="bi {{ $accion['icono'] }}"></i>
                        </span>
                        <span class="accion-texto">
                            <span class="accion-titulo">{{ $accion['titulo'] }}</span>
                            <span class="accion-detalle">{{ $accion['detalle'] }}</span>
                        </span>
                        <i class="bi bi-chevron-right accion-flecha"></i>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Resumen pedidos --}}
        <div class="card shadow-sm">
            <div class="card-header py-2">
                <span class="fw-semibold"><i class="bi bi-bar-chart"></i> Pedidos de {{ $mes->nombre() }}</span>
            </div>
            <div class="card-body p-0">
                <a href="{{ route('admin.pedidos.index', ['estado' => 'pendiente'] + $rangoDelMes) }}" class="text-decoration-none">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <span class="small"><i class="bi bi-hourglass-split text-warning me-1"></i> Pendientes</span>
                        <span class="badge bg-warning text-dark">{{ $pedidosStats['pendientes_mes'] }}</span>
                    </div>
                </a>
                <a href="{{ route('admin.pedidos.index', ['estado' => 'confirmado'] + $rangoDelMes) }}" class="text-decoration-none">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <span class="small"><i class="bi bi-check-circle text-success me-1"></i> Confirmados</span>
                        <span class="badge bg-success">{{ $pedidosStats['confirmados'] }}</span>
                    </div>
                </a>
                <a href="{{ route('admin.pedidos.index', ['estado' => 'cancelado'] + $rangoDelMes) }}" class="text-decoration-none">
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
