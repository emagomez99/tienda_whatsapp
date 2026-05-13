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

{{-- Filtros --}}
@php $filtrosActivos = request()->hasAny(['buscar','estado']); @endphp
<div class="card mb-4">
    <div class="card-header d-flex d-md-none justify-content-between align-items-center py-2 px-3"
         style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#filtros-pedidos"
         aria-expanded="{{ $filtrosActivos ? 'true' : 'false' }}">
        <span class="small fw-semibold text-muted"><i class="bi bi-funnel me-1"></i> Filtros{!! $filtrosActivos ? ' <span class="badge bg-primary ms-1" style="font-size:.6rem;">activo</span>' : '' !!}</span>
        <i class="bi bi-chevron-down filtros-chevron" style="transition:transform .2s;{{ $filtrosActivos ? 'transform:rotate(180deg);' : '' }}"></i>
    </div>
    <div class="collapse d-md-block{{ $filtrosActivos ? ' show' : '' }}" id="filtros-pedidos">
    <div class="card-body">
        <form action="{{ route('admin.pedidos.index') }}" method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="buscar" class="form-control" placeholder="Buscar..." value="{{ request('buscar') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="pendiente"  {{ request('estado') === 'pendiente'  ? 'selected' : '' }}>Pendiente</option>
                        <option value="confirmado" {{ request('estado') === 'confirmado' ? 'selected' : '' }}>Confirmado</option>
                        <option value="cancelado"  {{ request('estado') === 'cancelado'  ? 'selected' : '' }}>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
                @if(request('buscar') || request('estado'))
                    <div class="col-md-2">
                        <a href="{{ route('admin.pedidos.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
                    </div>
                @endif
            </div>
            <small class="text-muted fst-italic mt-2 d-block">Nombre, apellido, email, celular — o <code>#123</code> para buscar por ID</small>
        </form>
    </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        @if($pedidos->isEmpty())
            <div class="p-4 text-muted text-center">No hay pedidos.</div>
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
