@extends('layouts.admin')

@section('title', 'Pedidos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-bag-check"></i> Pedidos</h3>
    @if(auth()->user()->puede('pedidos.gestionar'))
        <a href="{{ route('admin.pedidos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo
        </a>
    @endif
</div>

{{-- Filtros: búsqueda y rango de fechas. El estado va aparte, en pestañas con contadores. --}}
@php
    $filtrosActivos = request()->filled('buscar') || $rango->estaActivo();
    // Parámetros que se conservan al cambiar de pestaña de estado o al limpiar filtros.
    $conBusqueda = array_filter(['buscar' => request('buscar')]);
    $conEstado   = array_filter(['estado' => $estado]);
@endphp
<div class="card mb-3">
    <div class="card-header d-flex d-md-none justify-content-between align-items-center py-2 px-3"
         style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#filtros-pedidos"
         aria-expanded="{{ $filtrosActivos ? 'true' : 'false' }}">
        <span class="small fw-semibold text-muted"><i class="bi bi-funnel me-1"></i> Filtros{!! $filtrosActivos ? ' <span class="badge bg-primary ms-1" style="font-size:.6rem;">activo</span>' : '' !!}</span>
        <i class="bi bi-chevron-down filtros-chevron" style="transition:transform .2s;{{ $filtrosActivos ? 'transform:rotate(180deg);' : '' }}"></i>
    </div>
    <div class="collapse d-md-block{{ $filtrosActivos ? ' show' : '' }}" id="filtros-pedidos">
    <div class="card-body py-3">
        <form action="{{ route('admin.pedidos.index') }}" method="GET">
            @if($estado)
                <input type="hidden" name="estado" value="{{ $estado }}">
            @endif
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="buscar" class="form-label small mb-1">Buscar</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="buscar" id="buscar" class="form-control"
                               placeholder="Nombre, email, celular o #123" value="{{ request('buscar') }}">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label for="desde" class="form-label small mb-1">Desde</label>
                    <input type="date" name="desde" id="desde" class="form-control" value="{{ $rango->desdeTexto() }}">
                </div>
                <div class="col-6 col-md-2">
                    <label for="hasta" class="form-label small mb-1">Hasta</label>
                    <input type="date" name="hasta" id="hasta" class="form-control" value="{{ $rango->hastaTexto() }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    @if($filtrosActivos)
                        <a href="{{ route('admin.pedidos.index', $conEstado) }}" class="btn btn-outline-secondary" title="Quitar búsqueda y fechas">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>
    </div>
</div>

{{-- Estado en pestañas: cada una dice cuántos pedidos hay dentro de la búsqueda y las fechas. --}}
@php
    $pestanas = [
        null         => ['Todos',       $porEstado->sum()],
        'pendiente'  => ['Pendientes',  $porEstado['pendiente'] ?? 0],
        'confirmado' => ['Confirmados', $porEstado['confirmado'] ?? 0],
        'cancelado'  => ['Cancelados',  $porEstado['cancelado'] ?? 0],
    ];
@endphp
<ul class="nav nav-tabs mb-0">
    @foreach($pestanas as $valor => $pestana)
        <li class="nav-item">
            <a class="nav-link {{ $estado === ($valor ?: null) ? 'active' : '' }}"
               href="{{ route('admin.pedidos.index', array_merge($conBusqueda, $rango->parametros(), array_filter(['estado' => $valor]))) }}">
                {{ $pestana[0] }} <span class="badge rounded-pill bg-secondary bg-opacity-25 text-body ms-1">{{ $pestana[1] }}</span>
            </a>
        </li>
    @endforeach
</ul>

<div class="card border-top-0 rounded-top-0">
    <div class="card-body p-0">
        @if($pedidos->isEmpty())
            <div class="p-4 text-muted text-center">{{ $filtrosActivos || $estado ? 'No hay pedidos con estos filtros.' : 'No hay pedidos.' }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    @php $mostrarLocalidad = App\Models\Configuracion::pedirDireccionEnvio(); @endphp
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            @if($mostrarLocalidad)<th>Localidad</th>@endif
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($pedidos as $pedido)
                        <tr>
                            <td><code>#{{ $pedido->id }}</code></td>
                            <td>
                                {{ $pedido->nombre }} {{ $pedido->apellido }}<br>
                                <small class="text-muted">{{ $pedido->celular }}</small>
                            </td>
                            @if($mostrarLocalidad)
                            <td>{{ $pedido->localidad ? $pedido->localidad . ($pedido->provincia ? ', ' . $pedido->provincia : '') : ($pedido->provincia ?: '—') }}</td>
                            @endif
                            <td>
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
                            <td>{{ $pedido->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.pedidos.show', $pedido) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $pedidos->links('vendor.pagination.tienda') }}
            </div>
        @endif
    </div>
</div>
@endsection
