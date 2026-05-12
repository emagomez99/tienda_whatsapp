@extends('layouts.admin')

@section('title', 'Pedido #' . $pedido->id)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-bag-check"></i> Pedido <code>#{{ $pedido->id }}</code></h3>
    <a href="{{ route('admin.pedidos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

@if(session('error_stock'))
<div class="alert alert-danger">
    <i class="bi bi-x-circle"></i> <strong>Stock insuficiente para confirmar el pedido:</strong>
    <ul class="mb-0 mt-1">
        @foreach(session('error_stock') as $e)
            <li>{{ $e }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="row g-4">

    {{-- Resumen compacto --}}
    <div class="col-12">
        <div class="card">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap align-items-center gap-3 justify-content-between">

                    {{-- Info cliente --}}
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div>
                            <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Cliente</div>
                            <div class="fw-semibold">{{ $pedido->nombre }} {{ $pedido->apellido }}</div>
                        </div>
                        @if($pedido->celular)
                            @php $waNumber = ltrim($pedido->celular, '+'); @endphp
                            <div>
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Celular</div>
                                <div class="d-flex align-items-center gap-1">
                                    <span>{{ $pedido->celular }}</span>
                                    <a href="https://wa.me/{{ $waNumber }}" target="_blank"
                                       class="btn btn-sm btn-success py-0 px-2" style="font-size:.75rem;">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                </div>
                            </div>
                        @endif
                        @if($pedido->email)
                            <div>
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Email</div>
                                <div>{{ $pedido->email }}</div>
                            </div>
                        @endif
                        @if($pedido->localidad || $pedido->provincia)
                            <div>
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Ubicación</div>
                                <div>{{ implode(', ', array_filter([$pedido->localidad, $pedido->provincia])) }}</div>
                            </div>
                        @endif
                    </div>

                    {{-- Estado + total + acciones --}}
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="text-end">
                            <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Total</div>
                            <div class="fw-bold fs-5">${{ number_format($pedido->total, 2) }}</div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Estado</div>
                            @if($pedido->esPendiente())
                                <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pendiente</span>
                            @elseif($pedido->esConfirmado())
                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Confirmado</span>
                            @else
                                <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Cancelado</span>
                            @endif
                            <div class="text-muted" style="font-size:.72rem;">{{ $pedido->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        @if($pedido->esPendiente())
                            <div class="d-flex flex-column gap-1">
                                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalConfirmar">
                                    <i class="bi bi-check-circle"></i> Confirmar
                                </button>
                                <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalCancelar">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </button>
                            </div>
                        @elseif($pedido->esConfirmado())
                            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalCancelar">
                                <i class="bi bi-x-circle"></i> Cancelar
                            </button>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Productos del pedido --}}
    <div class="col-12">
        @php $editable = ! $pedido->esCancelado(); @endphp
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-list-ul"></i> Productos</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabla-productos">
                        <thead class="table-light">
                            <tr>
                                <th>Descripción</th>
                                <th class="text-end">Precio unit.</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Subtotal</th>
                                @if($editable)<th style="width:80px"></th>@endif
                            </tr>
                        </thead>
                        <tbody id="tbody-productos">
                        @foreach($pedido->items as $item)
                            <tr>
                                <td>
                                    @if($item->producto)
                                        <a href="{{ route('admin.productos.edit', $item->producto) . '?_back_url=' . urlencode(route('admin.pedidos.show', $pedido)) }}" class="text-decoration-none">
                                            {{ $item->descripcion }}
                                        </a>
                                    @else
                                        {{ $item->descripcion }}
                                        <small class="text-muted">(producto eliminado)</small>
                                    @endif
                                </td>
                                <td class="text-end">${{ number_format($item->precio_unitario, 2) }}</td>
                                <td class="text-center">
                                    @if($editable)
                                        <form method="POST"
                                              action="{{ route('admin.pedidos.productos.update', [$pedido, $item]) }}"
                                              class="d-inline-flex align-items-center gap-1">
                                            @csrf @method('PUT')
                                            <input type="number" name="cantidad" value="{{ $item->cantidad }}"
                                                   min="1" class="form-control form-control-sm text-center" style="width:70px">
                                            <button class="btn btn-sm btn-outline-primary" title="Guardar">
                                                <i class="bi bi-check"></i>
                                            </button>
                                        </form>
                                    @else
                                        {{ $item->cantidad }}
                                    @endif
                                </td>
                                <td class="text-end">${{ number_format($item->subtotal, 2) }}</td>
                                @if($editable)
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger"
                                                title="Eliminar"
                                                onclick="confirmarEliminarItem('{{ route('admin.pedidos.productos.destroy', [$pedido, $item]) }}')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Total</td>
                                <td class="text-end fw-bold">${{ number_format($pedido->total, 2) }}</td>
                                @if($editable)
                                <td class="text-end">
                                    <button type="button" id="btn-nueva-fila" class="btn btn-sm btn-outline-primary" title="Agregar producto">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </td>
                                @endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
{{-- Modal: Confirmar pedido --}}
@if($pedido->esPendiente())
<div class="modal fade" id="modalConfirmar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-check-circle text-success"></i> Confirmar pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Confirmás el pedido <strong>#{{ $pedido->id }}</strong> de <strong>{{ $pedido->nombre }} {{ $pedido->apellido }}</strong>?</p>
                <p class="text-muted mb-0"><i class="bi bi-info-circle"></i> Se descontará el stock de los productos que lo tengan disponible.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="{{ route('admin.pedidos.confirmar', $pedido) }}">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Sí, confirmar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Modal: Cancelar pedido --}}
@if(! $pedido->esCancelado())
<div class="modal fade" id="modalCancelar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-x-circle text-danger"></i> Cancelar pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Cancelás el pedido <strong>#{{ $pedido->id }}</strong>?</p>
                @if($pedido->esConfirmado())
                    <p class="text-muted mb-0"><i class="bi bi-arrow-counterclockwise"></i> El stock descontado será restaurado automáticamente.</p>
                @endif
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                <form method="POST" action="{{ route('admin.pedidos.cancelar', $pedido) }}">
                    @csrf
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Sí, cancelar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Modal: Eliminar ítem --}}
@if($editable)
<div class="modal fade" id="modalEliminarItem" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-trash text-danger"></i> Eliminar ítem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Eliminás este ítem del pedido?</p>
                <p class="text-muted mb-0"><i class="bi bi-arrow-counterclockwise"></i> El stock del producto será restaurado.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                <form id="formEliminarItem" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Sí, eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function confirmarEliminarItem(url) {
    document.getElementById('formEliminarItem').action = url;
    new bootstrap.Modal(document.getElementById('modalEliminarItem')).show();
}
</script>
@endpush
@endif

@if($editable)
@push('scripts')
<script>
(function () {
    var urlBuscar  = '{{ route('admin.productos.buscar') }}';
    var urlAdd     = '{{ route('admin.pedidos.productos.add', $pedido) }}';
    var csrfToken  = '{{ csrf_token() }}';
    var tbody      = document.getElementById('tbody-productos');

    function initFila(fila) {
        var inputBuscar = fila.querySelector('.fila-buscar');
        var inputId     = fila.querySelector('.fila-id');
        var inputCant   = fila.querySelector('.fila-cantidad');
        var dropdown    = fila.querySelector('.fila-dropdown');
        var btnOk       = fila.querySelector('.btn-fila-ok');
        var btnCancel   = fila.querySelector('.btn-fila-cancel');
        var timer       = null;

        function posicionarDropdown() {
            var rect = inputBuscar.getBoundingClientRect();
            var maxH = 240;
            var spaceBelow = window.innerHeight - rect.bottom;
            dropdown.style.left  = rect.left + 'px';
            dropdown.style.width = Math.max(rect.width, 340) + 'px';
            if (spaceBelow < maxH + 8) {
                dropdown.style.top    = 'auto';
                dropdown.style.bottom = (window.innerHeight - rect.top + 2) + 'px';
            } else {
                dropdown.style.top    = (rect.bottom + 2) + 'px';
                dropdown.style.bottom = 'auto';
            }
        }

        inputBuscar.addEventListener('input', function () {
            clearTimeout(timer);
            inputId.value = '';
            btnOk.disabled = true;
            var q = this.value.trim();
            if (q.length < 2) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch(urlBuscar + '?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        dropdown.innerHTML = '';
                        if (data.length === 0) {
                            dropdown.innerHTML = '<div class="list-group-item text-muted small">Sin resultados</div>';
                            posicionarDropdown();
                            dropdown.style.display = 'block';
                            return;
                        }
                        data.forEach(function (p) {
                            var item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'list-group-item list-group-item-action py-2';
                            var stockTxt  = p.stock !== null ? ' · stock: ' + p.stock : ' · por encargue';
                            var codigoTxt = p.codigo ? ' <small class="text-muted">(' + p.codigo + ')</small>' : '';
                            item.innerHTML = '<span class="fw-semibold">' + p.descripcion + '</span>' + codigoTxt +
                                '<br><small class="text-muted">$' + parseFloat(p.precio).toFixed(2) + stockTxt + '</small>';
                            item.addEventListener('click', function () {
                                inputBuscar.value = p.descripcion;
                                inputId.value     = p.id;
                                btnOk.disabled    = false;
                                dropdown.style.display = 'none';
                                inputCant.focus();
                            });
                            dropdown.appendChild(item);
                        });
                        posicionarDropdown();
                        dropdown.style.display = 'block';
                    });
            }, 250);
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target) && e.target !== inputBuscar) {
                dropdown.style.display = 'none';
            }
        });

        btnOk.addEventListener('click', function () {
            if (!inputId.value) { inputBuscar.focus(); return; }
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = urlAdd;
            form.innerHTML =
                '<input type="hidden" name="_token" value="' + csrfToken + '">' +
                '<input type="hidden" name="producto_id" value="' + inputId.value + '">' +
                '<input type="hidden" name="cantidad" value="' + inputCant.value + '">';
            document.body.appendChild(form);
            form.submit();
        });

        btnCancel.addEventListener('click', function () {
            fila.remove();
        });

        inputBuscar.focus();
    }

    document.getElementById('btn-nueva-fila').addEventListener('click', function () {
        var cols = 5;
        var tr = document.createElement('tr');
        tr.className = 'table-primary';
        tr.innerHTML =
            '<td colspan="2" class="py-2 ps-3">' +
                '<div class="position-relative">' +
                    '<input type="text" class="form-control form-control-sm fila-buscar" placeholder="Buscar por nombre o código..." autocomplete="off">' +
                    '<input type="hidden" class="fila-id">' +
                    '<div class="fila-dropdown list-group shadow" style="position:fixed;z-index:1055;display:none;min-width:340px;max-height:240px;overflow-y:auto;"></div>' +
                '</div>' +
            '</td>' +
            '<td class="text-center py-2">' +
                '<input type="number" class="form-control form-control-sm text-center fila-cantidad" value="1" min="1" style="width:70px">' +
            '</td>' +
            '<td></td>' +
            '<td class="text-end py-2">' +
                '<div class="d-flex justify-content-end gap-1">' +
                    '<button type="button" class="btn btn-sm btn-success btn-fila-ok" disabled title="Confirmar"><i class="bi bi-check-lg"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary btn-fila-cancel" title="Cancelar"><i class="bi bi-x-lg"></i></button>' +
                '</div>' +
            '</td>';
        tbody.appendChild(tr);
        initFila(tr);
    });
})();
</script>
@endpush
@endif

@endsection
