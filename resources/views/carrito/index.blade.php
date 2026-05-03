@extends('layouts.app')

@section('title', 'Carrito')

@section('content')
<div class="container py-4">
    <h2><i class="bi bi-cart3"></i> Mi Carrito</h2>

    @if(!empty($ajustes))
        <div class="alert alert-warning mt-3">
            <i class="bi bi-exclamation-triangle"></i> <strong>El stock de algunos productos fue modificado:</strong>
            <ul class="mb-0 mt-1">
                @foreach($ajustes as $ajuste)
                    <li>{{ $ajuste }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(empty($productos))
        <div class="alert alert-info mt-4">
            <i class="bi bi-info-circle"></i> Tu carrito está vacío.
            <a href="{{ route('tienda.index') }}" class="alert-link">Ver productos</a>
        </div>
    @else
        <div class="table-responsive mt-4">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th>Código</th>
                        @if($mostrarPrecios)
                            <th>Precio</th>
                        @endif
                        <th>Cantidad</th>
                        @if($mostrarPrecios)
                            <th>Subtotal</th>
                        @endif
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productos as $item)
                        <tr id="fila-{{ $item['producto']->id }}">
                            <td>
                                <strong>{{ $item['producto']->descripcion }}</strong>
                                @if($item['producto']->etiquetas->count() > 0)
                                    <br>
                                    <button class="btn btn-sm btn-link p-0 mt-1" type="button" data-bs-toggle="collapse" data-bs-target="#detalle-{{ $item['producto']->id }}" aria-expanded="false">
                                        <i class="bi bi-chevron-down"></i> Detalle
                                    </button>
                                    <div class="collapse mt-1" id="detalle-{{ $item['producto']->id }}">
                                        <div class="card card-body p-2">
                                            @foreach($item['producto']->etiquetas as $etiqueta)
                                                <div class="mb-1">
                                                    <span class="badge bg-info">{{ $etiqueta->nombre }}: {{ $etiqueta->pivot->valor }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td>{{ $item['producto']->id_proveedor ?? '-' }}</td>
                            @if($mostrarPrecios)
                                <td>${{ number_format($item['producto']->precio, 2) }}</td>
                            @endif
                            <td>
                                <div class="qty-control d-flex align-items-center gap-1"
                                     data-producto-id="{{ $item['producto']->id }}"
                                     data-url="{{ route('carrito.actualizar', $item['producto']) }}"
                                     data-precio="{{ $item['producto']->precio }}"
                                     data-stock="{{ $item['producto']->stock ?? '' }}">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-decrement" {{ $item['cantidad'] <= 1 ? 'disabled' : '' }}>
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number"
                                           value="{{ $item['cantidad'] }}"
                                           min="1"
                                           class="form-control form-control-sm text-center qty-input"
                                           style="width: 65px;">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-increment">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                                <small class="qty-feedback text-warning d-block mt-1" style="min-height: 1rem;"></small>
                            </td>
                            @if($mostrarPrecios)
                                <td id="subtotal-{{ $item['producto']->id }}">${{ number_format($item['subtotal'], 2) }}</td>
                            @endif
                            <td>
                                <form action="{{ route('carrito.eliminar', $item['producto']) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                @if($mostrarPrecios)
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end"><strong>Total:</strong></td>
                            <td id="total-carrito"><strong class="text-primary">${{ number_format($total, 2) }}</strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <form action="{{ route('carrito.vaciar') }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('¿Estás seguro de vaciar el carrito?')">
                    <i class="bi bi-trash"></i> Vaciar carrito
                </button>
            </form>

            <div>
                <a href="{{ route('tienda.index') }}" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Seguir comprando
                </a>
                <a href="{{ route('carrito.checkout') }}" class="btn btn-success btn-lg">
                    <i class="bi bi-whatsapp"></i> Finalizar pedido
                </a>
            </div>
        </div>
    @endif
</div>

@if(!empty($productos))
<style>
/* Ocultar flechas del input number */
.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.qty-input { -moz-appearance: textfield; }
</style>
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let timers = {};

    function getStockUrl(productoId) {
        return '/carrito/stock/' + productoId;
    }

    function enviarActualizacion(control, nuevaCantidad) {
        const url = control.dataset.url;
        const precio = parseFloat(control.dataset.precio);
        const productoId = control.dataset.productoId;
        const input = control.querySelector('.qty-input');
        const feedback = control.closest('td').querySelector('.qty-feedback');
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
                    subtotalEl.innerHTML = '$' + formatNum(precio * cantidadFinal);
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
        const totalEl = document.getElementById('total-carrito');
        if (!totalEl) return;
        let total = 0;
        document.querySelectorAll('.qty-control').forEach(function (control) {
            const precio = parseFloat(control.dataset.precio);
            const input = control.querySelector('.qty-input');
            total += precio * (parseInt(input.value) || 1);
        });
        totalEl.innerHTML = '<strong class="text-primary">$' + formatNum(total) + '</strong>';
    }

    function formatNum(n) {
        return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    document.querySelectorAll('.qty-control').forEach(function (control) {
        const input = control.querySelector('.qty-input');
        const btnDec = control.querySelector('.btn-decrement');
        const btnInc = control.querySelector('.btn-increment');
        const productoId = control.dataset.productoId;
        const feedback = control.closest('td').querySelector('.qty-feedback');

        btnDec.addEventListener('click', function () {
            const actual = parseInt(input.value) || 1;
            if (actual > 1) enviarActualizacion(control, actual - 1);
        });

        btnInc.addEventListener('click', function () {
            const actual = parseInt(input.value) || 1;
            const nuevaCantidad = actual + 1;

            // Consultar stock actual en el servidor antes de incrementar
            fetch(getStockUrl(productoId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const stock = data.stock;
                if (stock !== null && nuevaCantidad > stock) {
                    feedback.textContent = '⚠ Stock ' + stock;
                } else {
                    feedback.textContent = '';
                    enviarActualizacion(control, nuevaCantidad);
                }
            })
            .catch(function () {
                // Si falla el chequeo, igual intentamos (el servidor validará)
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
