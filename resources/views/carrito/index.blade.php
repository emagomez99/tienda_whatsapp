@extends('layouts.app')

@section('title', 'Carrito')

@section('content')
<div class="container py-4">

    <div class="d-flex align-items-center gap-2 mb-4">
        <h3 class="mb-0"><i class="bi bi-cart3"></i> Mi Carrito</h3>
        @if(!empty($productos))
            <span class="badge bg-secondary rounded-pill">{{ count($productos) }}</span>
        @endif
    </div>

    @if(!empty($ajustes))
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>El stock de algunos productos fue ajustado:</strong>
            <ul class="mb-0 mt-1">
                @foreach($ajustes as $ajuste)
                    <li>{{ $ajuste }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(empty($productos))
        <div class="text-center py-5 d-flex flex-column align-items-center justify-content-center" style="min-height: 50vh;">
            <i class="bi bi-cart-x" style="font-size: 3.5rem; color: #dee2e6;"></i>
            <p class="text-muted mt-3 mb-3">Tu carrito está vacío.</p>
            <a href="{{ route('tienda.index') }}" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Ver productos
            </a>
        </div>
    @else
        <div class="row g-4 align-items-start">

            {{-- Columna de items --}}
            <div class="col-lg-8">
                <div class="d-flex flex-column gap-3">
                    @foreach($productos as $item)
                        @php $prod = $item['producto']; @endphp
                        {{-- TODO(public_id): public_id se usa acá sólo como identificador
                             único para los ids del DOM; sirve igual $prod->id. Al eliminar
                             la columna hay que reemplazarlo en los 5 lugares de este archivo
                             a la vez, o el JS deja de encontrar sus elementos (ids vacíos)
                             y el carrito deja de recalcular sin tirar ningún error. --}}
                        <div class="card border-0 shadow-sm" id="fila-{{ $prod->public_id }}">
                            <div class="card-body p-3">
                                <div class="ci-grid">

                                    {{-- Imagen --}}
                                    <div class="ci-img rounded bg-light overflow-hidden d-flex align-items-center justify-content-center"
                                         style="width:60px; height:60px;">
                                        <img src="{{ $prod->imagen_url ?? '/img/no-image.svg' }}"
                                             alt="{{ $prod->descripcion }}"
                                             style="width:100%; height:100%; object-fit:contain;"
                                             onerror="this.onerror=null;this.src='/img/no-image.svg';">
                                    </div>

                                    {{-- Nombre y código --}}
                                    <div class="ci-name" style="min-width:0;">
                                        <div class="fw-semibold lh-sm">{{ $prod->descripcion }}</div>
                                        @if($prod->id_proveedor)
                                            <small class="text-muted">{{ $prod->id_proveedor }}</small>
                                        @endif
                                        @if($prod->etiquetas->count() > 0)
                                            <div class="mt-1">
                                                <button class="btn btn-link btn-sm p-0 text-muted text-decoration-none"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#detalle-{{ $prod->public_id }}">
                                                    <i class="bi bi-tags"></i>
                                                    <small>Ver etiquetas</small>
                                                </button>
                                                <div class="collapse mt-1" id="detalle-{{ $prod->public_id }}">
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach($prod->etiquetas as $etiqueta)
                                                            <span class="badge bg-light text-dark border">
                                                                {{ $etiqueta->nombre }}: {{ $etiqueta->pivot->valor }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Precio unitario --}}
                                    @if($mostrarPrecios)
                                        <div class="ci-price text-center">
                                            <div class="text-muted small">Precio</div>
                                            <div class="small">{{ $prod->moneda ? $prod->moneda->simbolo : '$' }}{{ number_format($prod->precio, 2, ',', '.') }}</div>
                                        </div>
                                    @endif

                                    {{-- Cantidad --}}
                                    <div class="ci-qty qty-control d-flex align-items-center"
                                         data-producto-id="{{ $prod->public_id }}"
                                         data-url="{{ route('carrito.actualizar', $prod) }}"
                                         data-stock-url="{{ route('carrito.stock', $prod) }}"
                                         data-precio="{{ $prod->precio }}"
                                         data-stock="{{ $prod->stock ?? '' }}"
                                         data-moneda-id="{{ $prod->moneda_id ?? '' }}"
                                         data-moneda-simbolo="{{ $prod->moneda ? $prod->moneda->simbolo : '$' }}"
                                         data-moneda-nombre="{{ $prod->moneda ? $prod->moneda->nombre : '' }}">
                                        <button type="button"
                                                class="btn btn-outline-secondary btn-sm btn-decrement"
                                                style="width:32px; height:32px; padding:0;"
                                                {{ $item['cantidad'] <= 1 ? 'disabled' : '' }}>
                                            <i class="bi bi-dash"></i>
                                        </button>
                                        <input type="number"
                                               value="{{ $item['cantidad'] }}"
                                               min="1"
                                               class="form-control form-control-sm text-center qty-input mx-1"
                                               style="width:48px; height:32px;">
                                        <button type="button"
                                                class="btn btn-outline-secondary btn-sm btn-increment"
                                                style="width:32px; height:32px; padding:0;">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>

                                    {{-- Subtotal --}}
                                    @if($mostrarPrecios)
                                        <div class="ci-sub fw-semibold" id="subtotal-{{ $prod->public_id }}">
                                            {{ $prod->moneda ? $prod->moneda->simbolo : '$' }}{{ number_format($item['subtotal'], 2, ',', '.') }}
                                        </div>
                                    @endif

                                    {{-- Eliminar --}}
                                    <form class="ci-del" action="{{ route('carrito.eliminar', $prod) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="btn btn-link text-danger p-0"
                                                title="Eliminar">
                                            <i class="bi bi-trash" style="font-size: 1.1rem;"></i>
                                        </button>
                                    </form>

                                </div>

                                <div class="qty-feedback text-warning small mt-1" style="min-height: 1rem; font-size: .75rem;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 text-center text-lg-start">
                    <a href="{{ route('tienda.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Seguir comprando
                    </a>
                </div>
            </div>

            {{-- Resumen del pedido --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm" style="position: sticky; top: 1rem;">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">Resumen del pedido</h5>

                        @if($mostrarPrecios)
                            <div id="total-carrito" class="mb-4">
                                @foreach($totalesPorMoneda as $grupo)
                                    @php $simbolo = $grupo['moneda'] ? $grupo['moneda']->simbolo : '$'; @endphp
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">{{ $grupo['moneda'] ? $grupo['moneda']->nombre : 'Total' }}</span>
                                        <span class="fw-semibold text-primary">{{ $simbolo }}{{ number_format($grupo['total'], 2, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <a href="{{ route('carrito.checkout') }}" class="btn btn-success w-100 py-2">
                            <i class="bi bi-whatsapp me-1"></i> Finalizar pedido
                        </a>

                        <form action="{{ route('carrito.vaciar') }}" method="POST" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="btn btn-link text-danger w-100 btn-sm"
                                    onclick="return confirm('¿Estás seguro de vaciar el carrito?')">
                                <i class="bi bi-trash"></i> Vaciar carrito
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    @endif
</div>

@if(!empty($productos))
<style>
/* Mobile: CSS Grid de 2 filas */
.ci-grid {
    display: grid;
    grid-template-columns: 60px 1fr auto;
    grid-template-rows: auto auto;
    column-gap: 12px;
    row-gap: 8px;
    align-items: center;
}
.ci-img   { grid-column: 1; grid-row: 1 / 3; align-self: center; }
.ci-name  { grid-column: 2; grid-row: 1; }
.ci-del   { grid-column: 3; grid-row: 1; align-self: start; }
.ci-price { display: none; }
.ci-qty   { grid-column: 2; grid-row: 2; }
.ci-sub   { grid-column: 3; grid-row: 2; text-align: right; white-space: nowrap; }

/* Desktop: Flexbox de 1 fila */
@media (min-width: 576px) {
    .ci-grid {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .ci-img   { flex-shrink: 0; align-self: auto; }
    .ci-name  { flex: 1 1 0; min-width: 0; }
    .ci-price { display: block; flex-shrink: 0; text-align: center; min-width: 64px; }
    .ci-qty   { flex-shrink: 0; }
    .ci-sub   { flex-shrink: 0; min-width: 70px; text-align: right; white-space: normal; }
    .ci-del   { flex-shrink: 0; align-self: auto; }
}

.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.qty-input { -moz-appearance: textfield; }
</style>
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let timers = {};

    function enviarActualizacion(control, nuevaCantidad) {
        const url = control.dataset.url;
        const precio = parseFloat(control.dataset.precio);
        const productoId = control.dataset.productoId;
        const input = control.querySelector('.qty-input');
        const feedback = control.closest('.card-body').querySelector('.qty-feedback');
        const btnDec = control.querySelector('.btn-decrement');

        input.value = nuevaCantidad;
        btnDec.disabled = nuevaCantidad <= 1;
        feedback.textContent = '';

        clearTimeout(timers[productoId]);
        timers[productoId] = setTimeout(function () {
            const body = new URLSearchParams();
            body.append('_method', 'PUT');
            body.append('cantidad', nuevaCantidad);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: body.toString(),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const cantidadFinal = data.cantidad;
                input.value = cantidadFinal;
                btnDec.disabled = cantidadFinal <= 1;

                const subtotalEl = document.getElementById('subtotal-' + productoId);
                if (subtotalEl) {
                    const simbolo = control.dataset.monedaSimbolo || '$';
                    subtotalEl.textContent = simbolo + formatNum(precio * cantidadFinal);
                }
                recalcularTotal();

                if (typeof updateCartBadge === 'function') {
                    updateCartBadge(data.cantidad_carrito);
                }

                if (data.warning) {
                    feedback.textContent = '⚠ ' + data.warning;
                }
            })
            .catch(function () {
                feedback.textContent = 'Error al actualizar.';
            });
        }, 400);
    }

    function recalcularTotal() {
        var grupos = {};
        document.querySelectorAll('.qty-control').forEach(function (control) {
            var precio   = parseFloat(control.dataset.precio);
            var monedaId = control.dataset.monedaId || '0';
            var simbolo  = control.dataset.monedaSimbolo || '$';
            var nombre   = control.dataset.monedaNombre || 'Total';
            var cantidad = parseInt(control.querySelector('.qty-input').value) || 1;
            if (!grupos[monedaId]) {
                grupos[monedaId] = { simbolo: simbolo, nombre: nombre, total: 0 };
            }
            grupos[monedaId].total += precio * cantidad;
        });
        var elTotal = document.getElementById('total-carrito');
        if (elTotal) {
            var html = '';
            Object.values(grupos).forEach(function (g) {
                html += '<div class="d-flex justify-content-between mb-1">' +
                    '<span class="text-muted">' + g.nombre + '</span>' +
                    '<span class="fw-semibold text-primary">' + g.simbolo + formatNum(g.total) + '</span>' +
                    '</div>';
            });
            elTotal.innerHTML = html;
        }
    }

    function formatNum(n) {
        var parts = n.toFixed(2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return parts.join(',');
    }

    document.querySelectorAll('.qty-control').forEach(function (control) {
        const input = control.querySelector('.qty-input');
        const btnDec = control.querySelector('.btn-decrement');
        const btnInc = control.querySelector('.btn-increment');
        const productoId = control.dataset.productoId;
        const feedback = control.closest('.card-body').querySelector('.qty-feedback');

        btnDec.addEventListener('click', function () {
            const actual = parseInt(input.value) || 1;
            if (actual > 1) enviarActualizacion(control, actual - 1);
        });

        btnInc.addEventListener('click', function () {
            const actual = parseInt(input.value) || 1;
            const nuevaCantidad = actual + 1;

            fetch(control.dataset.stockUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.stock !== null && nuevaCantidad > data.stock) {
                    feedback.textContent = '⚠ Stock máximo: ' + data.stock;
                } else {
                    feedback.textContent = '';
                    enviarActualizacion(control, nuevaCantidad);
                }
            })
            .catch(function () {
                enviarActualizacion(control, nuevaCantidad);
            });
        });

        input.addEventListener('change', function () {
            const nuevaCantidad = parseInt(input.value) || 1;
            enviarActualizacion(control, nuevaCantidad);
        });
    });
})();
</script>
@endif
@endsection
