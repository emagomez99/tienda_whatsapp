@extends('layouts.admin')

@section('title', 'Historial de stock — ' . $producto->descripcion)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-start gap-3 mb-4">
    <div>
        <h3><i class="bi bi-clock-history"></i> Historial de stock</h3>
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
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-ajuste-stock">
            <i class="bi bi-arrow-left-right"></i><span class="d-none d-sm-inline"> Ajustar stock</span>
        </button>
        <a href="{{ route('admin.productos.edit', $producto) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil"></i><span class="d-none d-sm-inline"> Editar</span>
        </a>
        <a href="{{ route('admin.productos.index') }}" class="btn btn-outline-secondary btn-sm">
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
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-primary">{{ $producto->stock }}</div>
                <div class="text-muted small">Stock actual</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-muted">{{ $movimientos->total() }}</div>
                <div class="text-muted small">Movimientos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-success">+{{ $totales['entradas'] }}</div>
                <div class="text-muted small">Entradas</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-danger">-{{ $totales['salidas'] }}</div>
                <div class="text-muted small">Salidas</div>
            </div>
        </div>
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

{{-- Modal: ajuste de stock (mismo desde edit) --}}
<div class="modal fade" id="modal-ajuste-stock" tabindex="-1" aria-labelledby="modal-ajuste-stock-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.productos.ajustar-stock', $producto) }}" method="POST">
                @csrf
                <input type="hidden" name="_redirect" value="historial">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-ajuste-stock-label">
                        <i class="bi bi-arrow-left-right"></i> Ajustar stock
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if($errors->has('variacion'))
                        <div class="alert alert-danger py-2">{{ $errors->first('variacion') }}</div>
                    @endif
                    <div class="alert alert-secondary py-2 mb-3">
                        Stock actual: <strong>{{ $producto->stock }}</strong>
                    </div>
                    <div class="mb-3">
                        <label for="modal-variacion" class="form-label fw-semibold">Variación *</label>
                        <input type="number" name="variacion" id="modal-variacion" class="form-control"
                               placeholder="Ej: 10 para entrada, -5 para salida" required>
                        <small class="text-muted">Positivo = entrada de stock &nbsp;·&nbsp; Negativo = salida.</small>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted">Stock resultante:</span>
                            <strong id="stock-resultante-preview">{{ $producto->stock }}</strong>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label for="modal-descripcion" class="form-label">Motivo</label>
                        <input type="text" name="descripcion" id="modal-descripcion" class="form-control"
                               placeholder="Ej: Recepción de mercadería, corrección de inventario">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Confirmar ajuste
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    var stockBase = {{ $producto->stock }};
    var modalVariacion = document.getElementById('modal-variacion');
    var stockResultantePreview = document.getElementById('stock-resultante-preview');

    if (modalVariacion) {
        modalVariacion.addEventListener('input', function() {
            var variacion = parseInt(this.value) || 0;
            var resultante = stockBase + variacion;
            stockResultantePreview.textContent = resultante;
            stockResultantePreview.style.color = resultante < 0 ? '#dc3545' : '';
            stockResultantePreview.style.fontWeight = 'bold';
        });

        @if($errors->has('variacion'))
            var modalAjuste = new bootstrap.Modal(document.getElementById('modal-ajuste-stock'));
            modalAjuste.show();
        @endif
    }
</script>
@endpush
