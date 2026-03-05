@extends('layouts.app')

@section('title', $producto->descripcion)

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('tienda.index') }}">Tienda</a></li>
            <li class="breadcrumb-item active">{{ $producto->descripcion }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-5">
            @if($producto->url_imagen)
                <img src="{{ $producto->imagen_url }}" class="img-fluid rounded shadow" alt="{{ $producto->descripcion }}">
            @else
                <div class="bg-secondary rounded d-flex align-items-center justify-content-center text-white" style="height: 400px;">
                    <i class="bi bi-image" style="font-size: 5rem;"></i>
                </div>
            @endif
        </div>
        <div class="col-md-7">
            <h2>{{ $producto->descripcion }}</h2>

            @if($producto->id_proveedor)
                <p class="text-muted mb-1">Código: {{ $producto->id_proveedor }}</p>
            @endif

            @if($producto->proveedor)
                <p class="text-muted mb-1">Proveedor: {{ $producto->proveedor->nombre }}</p>
            @endif

            @if($producto->detalle)
                <div class="my-3">
                    <p class="lead" style="white-space: pre-line;">{{ $producto->detalle }}</p>
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

            <div class="mb-3">
                @if($producto->stock > 0)
                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> En stock ({{ $producto->stock }} disponibles)</span>
                @elseif($producto->por_encargue)
                    <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Disponible por encargue</span>
                @else
                    <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Sin stock</span>
                @endif
            </div>

            @if($producto->estaDisponible())
                <form id="form-agregar" class="d-flex gap-2 mt-4">
                    @csrf
                    <input type="number" name="cantidad" value="1" min="1" class="form-control" style="width: 100px;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-cart-plus"></i> Agregar al carrito
                    </button>
                </form>
            @endif

            <button type="button" onclick="history.back()" class="btn btn-outline-secondary mt-3">
                <i class="bi bi-arrow-left"></i> Volver a la tienda
            </button>
        </div>
    </div>
</div>
@push('scripts')
<script>
    var formAgregar = document.getElementById('form-agregar');
    if (formAgregar) {
        formAgregar.addEventListener('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(this);
            fetch('{{ route('carrito.agregar', $producto) }}', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                showToast(data.message, data.success ? 'success' : 'danger');
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
