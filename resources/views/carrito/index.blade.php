@extends('layouts.app')

@section('title', 'Carrito')

@section('content')
<div class="container">

    <div class="cart-head">
        <h1 class="page-title">Mi carrito</h1>
        @if(!empty($productos))
            <span class="count-pill">{{ count($productos) }} {{ count($productos) === 1 ? 'producto' : 'productos' }}</span>
        @endif
    </div>

    @if(!empty($ajustes))
        <div class="tienda-nota tienda-nota-warn">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>El stock de algunos productos fue ajustado:</strong>
                <ul>
                    @foreach($ajustes as $ajuste)
                        <li>{{ $ajuste }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if(empty($productos))
        <div class="tienda-aviso py-5">
            <div class="tienda-aviso-icon"><i class="bi bi-cart3"></i></div>
            <div class="tienda-aviso-title">Tu carrito está vacío</div>
            <p class="mb-0">Explorá el catálogo y agregá los productos que necesites.</p>
            <a href="{{ route('tienda.index') }}" class="btn btn-primary px-4">
                <i class="bi bi-grid me-1"></i> Ver productos
            </a>
        </div>
    @else
        <div class="row g-4 align-items-start">

            {{-- Columna de items --}}
            <div class="col-lg-8">
                <div class="cart-list">
                    @foreach($productos as $item)
                        @php
                            $prod = $item['producto'];
                            $simbolo = $prod->moneda ? $prod->moneda->simbolo : '$';
                        @endphp
                        {{-- TODO(public_id): public_id se usa acá sólo como identificador
                             único para los ids del DOM; sirve igual $prod->id. Al eliminar
                             la columna hay que reemplazarlo en los 5 lugares de este archivo
                             a la vez, o el JS deja de encontrar sus elementos (ids vacíos)
                             y el carrito deja de recalcular sin tirar ningún error. --}}
                        <div class="cart-item" id="fila-{{ $prod->public_id }}">
                            <div class="ci-grid">

                                {{-- Imagen --}}
                                <a href="{{ $prod->url() }}" class="ci-img">
                                    <img src="{{ $prod->imagen_url ?? '/img/no-image.svg' }}"
                                         alt="{{ $prod->descripcion }}"
                                         loading="lazy"
                                         onerror="this.onerror=null;this.src='/img/no-image.svg';">
                                </a>

                                {{-- Nombre y código --}}
                                <div class="ci-name">
                                    <a href="{{ $prod->url() }}" class="ci-title">{{ $prod->descripcion }}</a>
                                    @if($prod->id_proveedor)
                                        <div class="ci-code">Cód. {{ $prod->id_proveedor }}</div>
                                    @endif
                                    @if($prod->etiquetas->count() > 0)
                                        <button class="ci-tags-btn"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#detalle-{{ $prod->public_id }}"
                                                aria-expanded="false">
                                            <i class="bi bi-tags"></i> Ver etiquetas
                                        </button>
                                        <div class="collapse" id="detalle-{{ $prod->public_id }}">
                                            <div class="d-flex flex-wrap gap-1 pt-2">
                                                @foreach($prod->etiquetas as $etiqueta)
                                                    <span class="chip">
                                                        <span class="chip-k">{{ $etiqueta->nombre }}</span> {{ $etiqueta->pivot->valor }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Precio unitario --}}
                                @if($mostrarPrecios)
                                    <div class="ci-price">
                                        <div class="ci-label">Precio</div>
                                        <div class="ci-price-value">{{ $simbolo }}{{ number_format($prod->precio, 2, ',', '.') }}</div>
                                    </div>
                                @endif

                                {{-- Cantidad --}}
                                <div class="ci-qty qty-control qty-stepper"
                                     data-producto-id="{{ $prod->public_id }}"
                                     data-url="{{ route('carrito.actualizar', $prod) }}"
                                     data-stock-url="{{ route('carrito.stock', $prod) }}"
                                     {{-- Con los precios ocultos no se manda el número: el JS sólo lo usa
                                          para reescribir el subtotal y el total, que en ese caso ni existen. --}}
                                     data-precio="{{ $mostrarPrecios ? $prod->precio : 0 }}"
                                     data-stock="{{ $prod->stock ?? '' }}"
                                     data-moneda-id="{{ $prod->moneda_id ?? '' }}"
                                     data-moneda-simbolo="{{ $simbolo }}"
                                     data-moneda-nombre="{{ $prod->moneda ? $prod->moneda->nombre : '' }}">
                                    <button type="button" class="btn-decrement" aria-label="Restar uno"
                                            {{ $item['cantidad'] <= 1 ? 'disabled' : '' }}>
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number"
                                           value="{{ $item['cantidad'] }}"
                                           min="1"
                                           class="qty-input"
                                           aria-label="Cantidad">
                                    <button type="button" class="btn-increment" aria-label="Sumar uno">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>

                                {{-- Subtotal --}}
                                @if($mostrarPrecios)
                                    <div class="ci-sub" id="subtotal-{{ $prod->public_id }}">
                                        {{ $simbolo }}{{ number_format($item['subtotal'], 2, ',', '.') }}
                                    </div>
                                @endif

                                {{-- Eliminar --}}
                                <form class="ci-del" action="{{ route('carrito.eliminar', $prod) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ci-del-btn" title="Eliminar"
                                            aria-label="Eliminar {{ $prod->descripcion }}">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>

                            </div>

                            <div class="qty-feedback"></div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Resumen del pedido --}}
            <div class="col-lg-4">
                <aside class="cart-summary">
                    <h2 class="cart-summary-title">Resumen del pedido</h2>

                    @if($mostrarPrecios)
                        <div id="total-carrito">
                            @foreach($totalesPorMoneda as $grupo)
                                @php $simbolo = $grupo['moneda'] ? $grupo['moneda']->simbolo : '$'; @endphp
                                <div class="summary-row">
                                    <span class="summary-row-label">{{ $grupo['moneda'] ? $grupo['moneda']->nombre : 'Total' }}</span>
                                    <span class="summary-row-value">{{ $simbolo }}{{ number_format($grupo['total'], 2, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="summary-divider"></div>
                    @endif

                    <p class="summary-note">
                        <i class="bi bi-whatsapp"></i>
                        <span>El pedido se confirma con el vendedor por WhatsApp.</span>
                    </p>

                    <a href="{{ route('carrito.checkout') }}" class="btn btn-success btn-cta w-100">
                        <i class="bi bi-whatsapp"></i> Finalizar pedido
                    </a>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <a href="{{ route('tienda.index') }}" class="link-quiet">
                            <i class="bi bi-arrow-left"></i> Seguir comprando
                        </a>
                        <form action="{{ route('carrito.vaciar') }}" method="POST"
                              data-confirmar="¿Vaciar el carrito?"
                              data-confirmar-detalle="Se quitan todos los productos que agregaste."
                              data-confirmar-boton="Sí, vaciar">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="link-quiet is-danger">
                                <i class="bi bi-trash3"></i> Vaciar
                            </button>
                        </form>
                    </div>
                </aside>
            </div>

        </div>
    @endif
</div>

@if(!empty($productos))
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let timers = {};

    function enviarActualizacion(control, nuevaCantidad) {
        const url = control.dataset.url;
        const precio = parseFloat(control.dataset.precio);
        const productoId = control.dataset.productoId;
        const input = control.querySelector('.qty-input');
        const feedback = control.closest('.cart-item').querySelector('.qty-feedback');
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
            // Mismo markup que las filas .summary-row que arma el servidor.
            var html = '';
            Object.values(grupos).forEach(function (g) {
                html += '<div class="summary-row">' +
                    '<span class="summary-row-label">' + g.nombre + '</span>' +
                    '<span class="summary-row-value">' + g.simbolo + formatNum(g.total) + '</span>' +
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
        const feedback = control.closest('.cart-item').querySelector('.qty-feedback');

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
