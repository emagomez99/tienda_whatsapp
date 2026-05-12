@extends('layouts.admin')

@section('title', 'Nuevo Pedido')

@push('styles')
<style>
    .form-section { animation: fadeSlide .2s ease; }
    @keyframes fadeSlide {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .field-group {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 1rem;
    }
    .field-group .form-label {
        font-size: .8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6c757d;
        margin-bottom: .3rem;
    }
    .field-group .form-control { background: #fff; border-color: #dee2e6; border-radius: 8px; }
    .field-group .form-control:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 .2rem rgba(13,110,253,.15);
    }
    #buscar-cliente { border-radius: 12px; font-size: 1rem; }
    .drop-static-item { background: #f8f9fa; }
    .drop-static-item:hover { background: #e9ecef; }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3><i class="bi bi-plus-circle"></i> Nuevo Pedido</h3>
    </div>
    <a href="{{ route('admin.pedidos.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Volver
    </a>
</div>

{{-- Buscador unificado de cliente --}}
<div class="mb-4" style="max-width:480px;">
    <div class="position-relative">
        <i class="bi bi-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#adb5bd;pointer-events:none;z-index:1;"></i>
        <input type="text" id="buscar-cliente" class="form-control form-control-lg ps-5"
               placeholder="Buscar cliente, o elegir tipo..." autocomplete="off">
        <div id="drop-cliente" class="list-group shadow"
             style="position:fixed;z-index:1055;display:none;max-height:320px;overflow-y:auto;"></div>
    </div>
</div>

{{-- Formulario unificado --}}
<form action="{{ route('admin.pedidos.store') }}" method="POST" id="form-pedido" style="display:none;" class="form-section">
    @csrf
    <input type="hidden" name="nombre"    id="f-nombre">
    <input type="hidden" name="apellido"  id="f-apellido">
    <input type="hidden" name="email"     id="f-email">
    <input type="hidden" name="direccion" id="f-direccion">
    <input type="hidden" name="localidad" id="f-localidad">
    <input type="hidden" name="provincia" id="f-provincia">
    <input type="hidden" name="cp"        id="f-cp">

    <div class="row g-4">

        {{-- Columna: datos del cliente --}}
        <div class="col-12 col-lg-5" id="col-cliente" style="display:none;">
            <div id="campos-cliente">
                <div class="field-group">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control vis-input" data-field="nombre" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Apellido <span class="req-star text-danger" style="display:none;">*</span></label>
                            <input type="text" class="form-control vis-input" data-field="apellido">
                        </div>
                        <div class="col-sm-6">
                            @include('partials.intl-tel-input', [
                                'inputId'   => 'celular-input',
                                'fieldName' => 'celular',
                                'value'     => '',
                                'label'     => 'Celular',
                            ])
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Email <span class="req-star text-danger" style="display:none;">*</span></label>
                            <input type="email" class="form-control vis-input" data-field="email">
                        </div>
                    </div>
                </div>
                <div class="field-group">
                    <button type="button"
                            class="btn btn-link p-0 text-muted fw-semibold d-flex align-items-center gap-1"
                            style="font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;text-decoration:none;"
                            data-bs-toggle="collapse" data-bs-target="#sec-direccion" aria-expanded="false">
                        <i class="bi bi-chevron-right" id="icon-dir" style="transition:transform .2s;font-size:.75rem;"></i>
                        Dirección y envío
                    </button>
                    <div class="collapse" id="sec-direccion">
                        @php $pedirDir = \App\Models\Configuracion::pedirDireccionEnvio(); @endphp
                        <div class="row g-3 mt-2">
                            <div class="col-12">
                                <label class="form-label">Dirección @if($pedirDir)<span class="req-star text-danger" style="display:none;">*</span>@endif</label>
                                <input type="text" class="form-control vis-input" data-field="direccion">
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label">Localidad</label>
                                <input type="text" class="form-control vis-input" data-field="localidad">
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label">Provincia</label>
                                <input type="text" class="form-control vis-input" data-field="provincia">
                            </div>
                            <div class="col-sm-2">
                                <label class="form-label">CP @if($pedirDir)<span class="req-star text-danger" style="display:none;">*</span>@endif</label>
                                <input type="text" class="form-control vis-input" data-field="cp">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Columna: productos --}}
        <div class="col-12" id="col-productos">
            <div class="card">
                <div class="card-header fw-semibold py-2">
                    <i class="bi bi-list-ul"></i> Productos
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Descripción</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-center" style="width:90px">Cant.</th>
                                    <th class="text-end">Subtotal</th>
                                    <th style="width:54px"></th>
                                </tr>
                            </thead>
                            <tbody id="tbody-items">
                                <tr id="tr-empty">
                                    <td colspan="5" class="text-center text-muted py-4 small" id="td-empty">
                                        <i class="bi bi-bag me-1"></i> Ningún producto agregado
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="3" class="text-end fw-bold pe-3">Total</td>
                                    <td class="text-end fw-bold" id="total-display">$0.00</td>
                                    <td class="text-end pe-2">
                                        <button type="button" id="btn-add-prod"
                                                class="btn btn-sm btn-outline-primary" title="Agregar producto">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary px-4" style="border-radius:10px;height:42px;">
            <i class="bi bi-check-lg me-1"></i> Crear pedido
        </button>
    </div>
</form>

@endsection

@push('scripts')
<script>
(function () {
    var items        = [];
    var inputBuscar  = document.getElementById('buscar-cliente');
    var drop         = document.getElementById('drop-cliente');
    var formPedido   = document.getElementById('form-pedido');
    var colCliente   = document.getElementById('col-cliente');
    var colProductos = document.getElementById('col-productos');
    var tbody        = document.getElementById('tbody-items');
    var trEmpty      = document.getElementById('tr-empty');
    var totalDisplay = document.getElementById('total-display');
    var campos       = ['nombre','apellido','celular','email','direccion','localidad','provincia','cp'];
    var urlProductos = '{{ route('admin.productos.buscar') }}';
    var urlClientes  = '{{ route('admin.pedidos.sugerir-clientes') }}';
    var debTimer     = null;
    var resultados   = [];
    var queryActual  = '';
    var tipoActual   = null;
    var pedirDir     = {{ \App\Models\Configuracion::pedirDireccionEnvio() ? 'true' : 'false' }};

    // ── Dropdown cliente ──────────────────────────────────────────────────────

    function posDrop() {
        var rect = inputBuscar.getBoundingClientRect();
        drop.style.left  = rect.left + 'px';
        drop.style.top   = (rect.bottom + 4) + 'px';
        drop.style.width = Math.max(rect.width, 340) + 'px';
    }

    function renderDrop(clientes) {
        drop.innerHTML = '';

        // 1. Consumidor Final — solo cuando no hay búsqueda activa
        if (!queryActual) {
            drop.appendChild(itemEstatico('🧾', 'Consumidor Final', 'Sin datos de cliente', function () {
                seleccionar('final', null);
            }));
        }

        // 2. Clientes encontrados
        clientes.forEach(function (s) {
            var nombre  = [s.nombre, s.apellido].filter(Boolean).join(' ');
            var detalle = [s.celular, s.email].filter(Boolean).join(' · ');
            var btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'list-group-item list-group-item-action py-2 px-3';
            btn.innerHTML =
                '<div class="fw-semibold" style="font-size:.9rem;">' + esc(nombre) + '</div>' +
                (detalle ? '<div class="text-muted" style="font-size:.78rem;">' + esc(detalle) + '</div>' : '');
            btn.addEventListener('click', function () { seleccionar('existente', s); });
            drop.appendChild(btn);
        });

        // 3. Nuevo cliente (siempre último)
        drop.appendChild(itemEstatico('✨', 'Nuevo cliente', 'Ingresar datos manualmente', function () {
            seleccionar('nuevo', null);
        }));

        posDrop();
        drop.style.display = '';
    }

    function itemEstatico(icon, label, sub, onClick) {
        var btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'list-group-item list-group-item-action py-2 px-3 drop-static-item';
        btn.innerHTML =
            '<div class="d-flex align-items-center gap-2">' +
                '<span style="font-size:1.1rem;line-height:1;">' + icon + '</span>' +
                '<div>' +
                    '<div class="fw-semibold" style="font-size:.9rem;">' + label + '</div>' +
                    '<div class="text-muted" style="font-size:.75rem;">' + sub + '</div>' +
                '</div>' +
            '</div>';
        btn.addEventListener('click', onClick);
        return btn;
    }

    function ocultarDrop() { drop.style.display = 'none'; }

    inputBuscar.addEventListener('focus', function () {
        queryActual = inputBuscar.value.trim();
        renderDrop(resultados);
    });

    inputBuscar.addEventListener('input', function () {
        clearTimeout(debTimer);
        queryActual = inputBuscar.value.trim();
        if (queryActual.length < 2) {
            resultados = [];
            renderDrop([]);
            return;
        }
        debTimer = setTimeout(function () {
            fetch(urlClientes + '?q=' + encodeURIComponent(queryActual), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) { resultados = data; renderDrop(data); })
            .catch(function () { renderDrop(resultados); });
        }, 220);
    });

    inputBuscar.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') ocultarDrop();
    });

    document.addEventListener('click', function (e) {
        if (!drop.contains(e.target) && e.target !== inputBuscar) ocultarDrop();
    });

    window.addEventListener('scroll', function () { if (drop.style.display !== 'none') posDrop(); }, { passive: true });
    window.addEventListener('resize', function () { if (drop.style.display !== 'none') posDrop(); });

    // ── Selección ─────────────────────────────────────────────────────────────

    function actualizarRequired(tipo) {
        var esNuevo = tipo === 'nuevo';

        // Apellido y email
        ['apellido', 'email'].forEach(function (f) {
            var inp = document.querySelector('.vis-input[data-field="' + f + '"]');
            if (inp) inp.required = esNuevo;
        });

        // Dirección y CP (solo si la config lo pide)
        if (pedirDir) {
            ['direccion', 'cp'].forEach(function (f) {
                var inp = document.querySelector('.vis-input[data-field="' + f + '"]');
                if (inp) inp.required = esNuevo;
            });
            // Auto-expandir la sección si es requerida
            if (esNuevo && secDir) {
                var col = bootstrap.Collapse.getOrCreateInstance(secDir, { toggle: false });
                col.show();
            }
        }

        // Mostrar/ocultar asteriscos
        document.querySelectorAll('.req-star').forEach(function (s) {
            s.style.display = esNuevo ? '' : 'none';
        });
    }

    function seleccionar(tipo, data) {
        tipoActual = tipo;
        ocultarDrop();
        campos.forEach(function (f) { setField(f, ''); });

        colCliente.style.display = '';
        colProductos.className   = 'col-12 col-lg-7';

        if (tipo === 'final') {
            inputBuscar.value = 'Consumidor Final';
            setField('nombre', 'Consumidor Final');
        } else if (tipo === 'existente' && data) {
            campos.forEach(function (f) { setField(f, data[f] || ''); });
            inputBuscar.value = [data.nombre, data.apellido].filter(Boolean).join(' ');
        } else {
            inputBuscar.value = '';
        }

        actualizarRequired(tipo);
        formPedido.style.display = '';
    }

    function setField(campo, valor) {
        if (campo === 'celular') {
            var telInput = document.getElementById('celular-input');
            if (telInput && window.intlTelInputGlobals) {
                var iti = window.intlTelInputGlobals.getInstance(telInput);
                if (iti) iti.setNumber(valor || '');
            }
            return;
        }
        var h = document.getElementById('f-' + campo);
        if (h) h.value = valor;
        var v = document.querySelector('.vis-input[data-field="' + campo + '"]');
        if (v) v.value = valor;
    }

    document.querySelectorAll('.vis-input').forEach(function (inp) {
        inp.addEventListener('input', function () {
            var h = document.getElementById('f-' + inp.dataset.field);
            if (h) h.value = inp.value;
        });
    });

    // ── Lista de productos ────────────────────────────────────────────────────

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function actualizarTotal() {
        var t = items.reduce(function (s, i) { return s + i.precio * i.cantidad; }, 0);
        totalDisplay.textContent = '$' + t.toFixed(2);
    }

    function renderItems() {
        tbody.querySelectorAll('.item-row').forEach(function (r) { r.remove(); });
        if (items.length === 0) {
            document.getElementById('td-empty').innerHTML = '<i class="bi bi-bag me-1"></i> Ningún producto agregado';
        }
        trEmpty.style.display = items.length ? 'none' : '';

        items.forEach(function (item, idx) {
            var tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.innerHTML =
                '<td class="ps-3" style="font-size:.9rem;">' + esc(item.descripcion) + '</td>' +
                '<td class="text-end">$' + item.precio.toFixed(2) + '</td>' +
                '<td class="text-center">' +
                    '<input type="number" class="form-control form-control-sm text-center item-cant" ' +
                           'style="width:70px;margin:0 auto;" value="' + item.cantidad + '" min="1" data-idx="' + idx + '">' +
                '</td>' +
                '<td class="text-end item-sub">$' + (item.precio * item.cantidad).toFixed(2) + '</td>' +
                '<td class="text-end pe-2">' +
                    '<button type="button" class="btn btn-sm btn-outline-danger btn-quitar" data-idx="' + idx + '" title="Quitar">' +
                        '<i class="bi bi-trash"></i>' +
                    '</button>' +
                '</td>';

            tr.querySelector('.item-cant').addEventListener('input', function () {
                var val = Math.max(1, parseInt(this.value) || 1);
                this.value = val;
                var i = parseInt(this.dataset.idx);
                items[i].cantidad = val;
                tr.querySelector('.item-sub').textContent = '$' + (items[i].precio * val).toFixed(2);
                actualizarTotal();
            });

            tr.querySelector('.btn-quitar').addEventListener('click', function () {
                items.splice(parseInt(this.dataset.idx), 1);
                renderItems();
            });

            tbody.appendChild(tr);
        });

        actualizarTotal();
    }

    // ── Fila de búsqueda de producto ──────────────────────────────────────────

    document.getElementById('btn-add-prod').addEventListener('click', function () {
        if (tbody.querySelector('.edit-row')) {
            tbody.querySelector('.fila-buscar').focus();
            return;
        }

        var tr = document.createElement('tr');
        tr.className = 'table-primary edit-row';
        tr.innerHTML =
            '<td colspan="2" class="py-2 ps-3">' +
                '<div class="position-relative">' +
                    '<input type="text" class="form-control form-control-sm fila-buscar" placeholder="Buscar producto..." autocomplete="off">' +
                    '<input type="hidden" class="fila-id">' +
                    '<input type="hidden" class="fila-precio">' +
                    '<input type="hidden" class="fila-desc">' +
                    '<div class="fila-drop list-group shadow" style="position:fixed;z-index:1055;display:none;min-width:320px;max-height:240px;overflow-y:auto;"></div>' +
                '</div>' +
            '</td>' +
            '<td class="text-center py-2">' +
                '<input type="number" class="form-control form-control-sm text-center fila-cant" value="1" min="1" style="width:70px;margin:0 auto;">' +
            '</td>' +
            '<td></td>' +
            '<td class="text-end py-2 pe-2">' +
                '<div class="d-flex justify-content-end gap-1">' +
                    '<button type="button" class="btn btn-sm btn-success btn-ok" disabled title="Confirmar"><i class="bi bi-check-lg"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary btn-cancel" title="Cancelar"><i class="bi bi-x-lg"></i></button>' +
                '</div>' +
            '</td>';

        tbody.appendChild(tr);
        initFilaEdicion(tr);
    });

    function initFilaEdicion(fila) {
        var inpB  = fila.querySelector('.fila-buscar');
        var inpId = fila.querySelector('.fila-id');
        var inpP  = fila.querySelector('.fila-precio');
        var inpD  = fila.querySelector('.fila-desc');
        var inpC  = fila.querySelector('.fila-cant');
        var dropP = fila.querySelector('.fila-drop');
        var btnOk = fila.querySelector('.btn-ok');
        var btnX  = fila.querySelector('.btn-cancel');
        var timer = null;

        function posP() {
            var rect  = inpB.getBoundingClientRect();
            var below = window.innerHeight - rect.bottom;
            dropP.style.left  = rect.left + 'px';
            dropP.style.width = Math.max(rect.width, 320) + 'px';
            if (below < 248) {
                dropP.style.top    = 'auto';
                dropP.style.bottom = (window.innerHeight - rect.top + 2) + 'px';
            } else {
                dropP.style.top    = (rect.bottom + 2) + 'px';
                dropP.style.bottom = 'auto';
            }
        }

        inpB.addEventListener('input', function () {
            clearTimeout(timer);
            inpId.value = ''; inpP.value = ''; btnOk.disabled = true;
            var q = inpB.value.trim();
            if (q.length < 2) { dropP.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch(urlProductos + '?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        dropP.innerHTML = '';
                        if (!data.length) {
                            dropP.innerHTML = '<div class="list-group-item text-muted small">Sin resultados</div>';
                            posP(); dropP.style.display = 'block'; return;
                        }
                        data.forEach(function (p) {
                            var btn = document.createElement('button');
                            btn.type      = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2';
                            var stockTxt  = p.stock !== null ? ' · stock: ' + p.stock : ' · por encargue';
                            var codTxt    = p.codigo ? ' <small class="text-muted">(' + p.codigo + ')</small>' : '';
                            btn.innerHTML =
                                '<span class="fw-semibold">' + esc(p.descripcion) + '</span>' + codTxt +
                                '<br><small class="text-muted">$' + parseFloat(p.precio).toFixed(2) + stockTxt + '</small>';
                            btn.addEventListener('click', function () {
                                inpB.value = p.descripcion;
                                inpId.value = p.id; inpP.value = p.precio; inpD.value = p.descripcion;
                                btnOk.disabled = false;
                                dropP.style.display = 'none';
                                inpC.focus();
                            });
                            dropP.appendChild(btn);
                        });
                        posP(); dropP.style.display = 'block';
                    });
            }, 250);
        });

        document.addEventListener('click', function (e) {
            if (!dropP.contains(e.target) && e.target !== inpB) dropP.style.display = 'none';
        });

        btnOk.addEventListener('click', function () {
            if (!inpId.value) { inpB.focus(); return; }
            var id   = parseInt(inpId.value);
            var prec = parseFloat(inpP.value);
            var desc = inpD.value;
            var cant = Math.max(1, parseInt(inpC.value) || 1);
            var ex   = null;
            items.forEach(function (i) { if (i.id === id) ex = i; });
            if (ex) { ex.cantidad += cant; } else { items.push({ id: id, descripcion: desc, precio: prec, cantidad: cant }); }
            fila.remove();
            renderItems();
        });

        btnX.addEventListener('click', function () { fila.remove(); });
        inpB.focus();
    }

    // ── Chevron dirección ─────────────────────────────────────────────────────

    var secDir  = document.getElementById('sec-direccion');
    var iconDir = document.getElementById('icon-dir');
    if (secDir) {
        secDir.addEventListener('show.bs.collapse',  function () { iconDir.style.transform = 'rotate(90deg)'; });
        secDir.addEventListener('hide.bs.collapse',  function () { iconDir.style.transform = 'rotate(0deg)';  });
    }

    // ── Submit ────────────────────────────────────────────────────────────────

    formPedido.addEventListener('submit', function (e) {
        if (items.length === 0) {
            e.preventDefault();
            var tdEmpty = document.getElementById('td-empty');
            tdEmpty.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Agregá al menos un producto antes de crear el pedido.</span>';
            document.getElementById('btn-add-prod').focus();
            return;
        }

        if (tipoActual === 'nuevo') {
            var telInput = document.getElementById('celular-input');
            if (telInput && !telInput.value.trim()) {
                e.preventDefault();
                telInput.focus();
                return;
            }
        }
        items.forEach(function (item, idx) {
            var a = document.createElement('input');
            a.type = 'hidden'; a.name = 'productos[' + idx + '][id]'; a.value = item.id;
            formPedido.appendChild(a);
            var b = document.createElement('input');
            b.type = 'hidden'; b.name = 'productos[' + idx + '][cantidad]'; b.value = item.cantidad;
            formPedido.appendChild(b);
        });
    });

})();
</script>
@endpush
