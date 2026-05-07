@extends('layouts.admin')

@section('title', 'Pedidos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-bag-check"></i> Pedidos</h3>
</div>

{{-- Filtros --}}
<div class="card mb-4">
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

<div class="card">
    <div class="card-body p-0">
        @if($pedidos->isEmpty())
            <div class="p-4 text-muted text-center">No hay pedidos.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Localidad</th>
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
                            <td>{{ $pedido->localidad ? $pedido->localidad . ($pedido->provincia ? ', ' . $pedido->provincia : '') : ($pedido->provincia ?: '—') }}</td>
                            <td>${{ number_format($pedido->total, 2) }}</td>
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
                {{ $pedidos->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
