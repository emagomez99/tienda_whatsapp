@extends('layouts.app')

@section('title', $producto->descripcion)

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb" style="--bs-breadcrumb-divider: '›';">
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item">
                <a href="{{ route('tienda.index') }}" class="text-decoration-none">
                    <i class="bi bi-house-door"></i> Tienda
                </a>
            </li>
            <li class="breadcrumb-item active text-truncate" style="max-width: 480px;" aria-current="page">
                {{ $producto->descripcion }}
            </li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="rounded shadow overflow-hidden bg-light d-flex align-items-center justify-content-center img-producto-wrap">
                <img src="{{ $producto->url_imagen ? $producto->imagen_url : '/img/no-image.svg' }}"
                     alt="{{ $producto->descripcion }}"
                     style="width: 100%; height: 100%; object-fit: contain;"
                     onerror="this.onerror=null;this.src='/img/no-image.svg';">
            </div>
        </div>
        <div class="col-md-7">
            <h2 class="fs-3">{{ $producto->descripcion }}</h2>

            @if($producto->id_proveedor)
                <p class="text-muted mb-1">Código: {{ $producto->id_proveedor }}</p>
            @endif

            @if($producto->proveedor && App\Models\Configuracion::mostrarProveedor())
                <p class="text-muted mb-1">Proveedor: {{ $producto->proveedor->nombre }}</p>
            @endif

            @if($producto->detalle)
                <div class="my-3">
                    {!! $producto->detalle !!}
                </div>
                <hr>
            @endif

            @if($mostrarPrecios)
                <h3 class="text-primary my-3">${{ number_format($producto->precio, 2) }}</h3>
            @endif

            @if($producto->etiquetas->where('visible_usuarios', true)->count() > 0)
                <div class="mb-3">
                    <strong>Etiquetas:</strong><br>
                    @foreach($producto->etiquetas->where('visible_usuarios', true) as $etiqueta)
                        <span class="badge bg-info me-1">{{ $etiqueta->nombre }}: {{ $etiqueta->pivot->valor }}</span>
                    @endforeach
                </div>
            @endif

            @if($producto->especificaciones->count() > 0)
                <div class="mb-3">
                    <strong>Especificaciones:</strong>
                    <table class="table table-sm table-striped mt-2">
                        @foreach($producto->especificaciones as $espec)
                            <tr>
                                <td class="fw-bold">{{ $espec->clave }}</td>
                                <td>{{ $espec->valor }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif

            <div class="mb-3 d-flex gap-2 flex-wrap">
                @if($producto->stock > 0)
                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> En stock ({{ $producto->stock }} disponibles)</span>
                @elseif($producto->stock !== null)
                    <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Sin stock</span>
                @endif
                @if($producto->por_encargue)
                    <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Disponible por encargue</span>
                @endif
            </div>

            @if($producto->estaDisponible())
                @php $maxStock = $producto->stockMaximo(); @endphp
                <form id="form-agregar" class="mt-4">
                    @csrf
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" id="btn-decrement" class="btn btn-outline-secondary" style="width:44px;height:44px;padding:0;" disabled>
                            <i class="bi bi-dash"></i>
                        </button>
                        <input type="number" name="cantidad" value="1" min="1" @if($maxStock !== null) max="{{ $maxStock }}" @endif
                               class="form-control text-center"
                               style="width: 70px; height:44px; -moz-appearance: textfield;"
                               id="input-cantidad">
                        <button type="button" id="btn-increment" class="btn btn-outline-secondary" style="width:44px;height:44px;padding:0;" {{ $maxStock === 1 ? 'disabled' : '' }}>
                            <i class="bi bi-plus"></i>
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg flex-grow-1" style="height:44px;">
                            <i class="bi bi-cart-plus"></i> Agregar al carrito
                        </button>
                    </div>
                </form>
            @endif

            <a href="{{ route('tienda.index') }}" class="btn btn-outline-secondary mt-3 w-100" id="btn-volver">
                <i class="bi bi-arrow-left"></i> Volver a la tienda
            </a>
        </div>
    </div>
</div>
<style>
.img-producto-wrap { height: 260px; }
#input-cantidad::-webkit-outer-spin-button,
#input-cantidad::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
@media (min-width: 768px) {
    .img-producto-wrap { height: 420px; }
    #btn-volver { width: auto !important; }
}
</style>
@push('scripts')
<script>
    var formAgregar = document.getElementById('form-agregar');
    if (formAgregar) {
        var inputCantidad = document.getElementById('input-cantidad');
        var btnDec = document.getElementById('btn-decrement');
        var btnInc = document.getElementById('btn-increment');
        var maxStock = {{ $maxStock ?? 'null' }};

        function actualizarBotones(val) {
            btnDec.disabled = val <= 1;
            btnInc.disabled = maxStock !== null && val >= maxStock;
        }

        btnDec.addEventListener('click', function () {
            var val = parseInt(inputCantidad.value) || 1;
            if (val > 1) { inputCantidad.value = val - 1; actualizarBotones(val - 1); }
        });

        btnInc.addEventListener('click', function () {
            var val = parseInt(inputCantidad.value) || 1;
            if (maxStock === null || val < maxStock) { inputCantidad.value = val + 1; actualizarBotones(val + 1); }
        });

        inputCantidad.addEventListener('input', function () {
            var val = parseInt(this.value) || 1;
            if (maxStock !== null && val > maxStock) { val = maxStock; this.value = val; }
            if (val < 1) { val = 1; this.value = val; }
            actualizarBotones(val);
        });

        formAgregar.addEventListener('submit', function (e) {
            e.preventDefault();
            var cantidad = parseInt(inputCantidad.value) || 1;
            if (maxStock !== null && cantidad > maxStock) {
                showToast('Stock máximo: ' + maxStock, 'warning');
                inputCantidad.value = maxStock;
                actualizarBotones(maxStock);
                return;
            }
            var formData = new FormData(this);
            fetch('{{ route('carrito.agregar', $producto) }}', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                showToast(data.message, data.success ? (data.warning ? 'warning' : 'success') : 'danger');
                updateCartBadge(data.cantidad_carrito);
            })
            .catch(function () {
                showToast('Error al agregar el producto', 'danger');
            });
        });
    }
</script>
@endpush
@endsection
