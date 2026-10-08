@extends('layouts.admin')

@section('title', 'Historial de stock — ' . $producto->descripcion)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h3 class="mb-0"><i class="bi bi-clock-history"></i> Historial de stock</h3>
        <p class="text-muted mb-0">
            <strong>{{ $producto->descripcion }}</strong>
            @if($producto->id_proveedor)
                <span class="text-secondary ms-2">· Cód. {{ $producto->id_proveedor }}</span>
            @endif
            @if($producto->proveedor)
                <span class="text-secondary ms-2">· {{ $producto->proveedor->nombre }}</span>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-ajuste-stock">
            <i class="bi bi-arrow-left-right"></i><span class="d-none d-sm-inline"> Ajustar stock</span>
        </button>
        <a href="{{ route('admin.productos.edit', $producto) }}" class="btn btn-outline-secondary" title="Editar">
            <i class="bi bi-pencil"></i><span class="d-none d-sm-inline"> Editar</span>
        </a>
        <a href="{{ route('admin.productos.index') }}" class="btn btn-outline-secondary" title="Volver">
            <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Resumen de stock --}}
{{-- Números: mismas tarjetas que en Inicio (.panel-dato). Un cero va sin signo y
     atenuado: "-0" parecía un error. --}}
<div class="row g-2 mb-4">
    <div class="col-6 col-md-3">
        <div class="panel-dato"><div class="card"><div class="card-body">
            <div class="panel-rotulo">Stock actual</div>
            <div class="panel-dato-valor">{{ $producto->stock }}</div>
        </div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="panel-dato"><div class="card"><div class="card-body">
            <div class="panel-rotulo">Movimientos</div>
            <div class="panel-dato-valor">{{ $movimientos->total() }}</div>
        </div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="panel-dato"><div class="card"><div class="card-body">
            <div class="panel-rotulo">Entradas</div>
            <div class="panel-dato-valor {{ $totales['entradas'] ? 'text-success' : 'text-muted' }}">{{ $totales['entradas'] ? '+' . $totales['entradas'] : '0' }}</div>
        </div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="panel-dato"><div class="card"><div class="card-body">
            <div class="panel-rotulo">Salidas</div>
            <div class="panel-dato-valor {{ $totales['salidas'] ? 'text-danger' : 'text-muted' }}">{{ $totales['salidas'] ? '-' . $totales['salidas'] : '0' }}</div>
        </div></div></div>
    </div>
</div>

{{-- Tabla de movimientos --}}
<div class="card">
    <div class="card-body p-0">
        @if($movimientos->isEmpty())
            <div class="p-4 text-center text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                Este producto no tiene movimientos de stock registrados.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Fecha</th>
                            <th>Tipo</th>
                            <th class="d-none d-md-table-cell">Descripción</th>
                            <th class="d-none d-md-table-cell">Pedido</th>
                            <th class="d-none d-lg-table-cell">Usuario</th>
                            <th class="text-end">Variación</th>
                            <th class="text-end pe-3">Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($movimientos as $mov)
                            <tr>
                                <td class="ps-3 text-nowrap text-muted small">
                                    {{ $mov->created_at->format('d/m/Y') }}<br>
                                    {{ $mov->created_at->format('H:i') }}
                                </td>
                                <td>
                                    <span class="badge bg-{{ $mov->tipoBadgeClase() }}">
                                        <i class="bi {{ $mov->tipoIcono() }}"></i>
                                        <span class="d-none d-sm-inline">{{ $mov->tipoLabel() }}</span>
                                    </span>
                                    {{-- De qué variante (Talle M): el stock de la columna es el de ella. --}}
                                    @if($mov->variante)
                                        <span class="badge text-bg-light border d-block mt-1 fw-normal">{{ $mov->variante->nombre() }}</span>
                                    @endif
                                </td>
                                <td class="text-muted small d-none d-md-table-cell">
                                    {{ $mov->descripcion ?? '—' }}
                                </td>
                                <td class="d-none d-md-table-cell">
                                    @if($mov->pedido_id && $mov->pedido)
                                        <a href="{{ route('admin.pedidos.show', $mov->pedido) }}" class="text-decoration-none fw-semibold">
                                            #{{ $mov->pedido->id }}
                                        </a>
                                        <small class="text-muted d-block">{{ $mov->pedido->nombre }} {{ $mov->pedido->apellido }}</small>
                                    @elseif($mov->pedido_id)
                                        <span class="text-muted small">#{{ $mov->pedido_id }} <em>(eliminado)</em></span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-muted small d-none d-lg-table-cell">
                                    @if($mov->user)
                                        {{ $mov->user->name }}
                                    @elseif($mov->user_id)
                                        <em>Usuario #{{ $mov->user_id }}</em>
                                    @else
                                        <span class="text-muted">Sistema</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold">
                                    @if($mov->esEntrada())
                                        <span class="text-success">+{{ $mov->variacion }}</span>
                                    @else
                                        <span class="text-danger">{{ $mov->variacion }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3 fw-semibold">
                                    {{ $mov->stock_resultante }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($movimientos->hasPages())
                <div class="p-3">
                    {{ $movimientos->withQueryString()->links('vendor.pagination.tienda') }}
                </div>
            @endif
        @endif
    </div>
</div>

@include('admin.productos.partials.modal-ajuste-stock', ['redirect' => 'historial'])
@endsection

