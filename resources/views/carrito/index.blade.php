@extends('layouts.app')

@section('title', 'Carrito')

@section('content')
<div class="container py-4">
    <h2><i class="bi bi-cart3"></i> Mi Carrito</h2>

    @if(empty($productos))
        <div class="alert alert-info mt-4">
            <i class="bi bi-info-circle"></i> Tu carrito está vacío.
            <a href="{{ route('tienda.index') }}" class="alert-link">Ver productos</a>
        </div>
    @else
        <div class="table-responsive mt-4">
            <table class="table table-hover">
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
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productos as $item)
                        <tr>
                            <td>
                                <strong>{{ $item['producto']->descripcion }}</strong>
                                @if($item['producto']->etiquetas->count() > 0)
                                    <br>
                                    <button class="btn btn-sm btn-link p-0 mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#detalle-{{ $item['producto']->id }}" aria-expanded="false">
                                        <i class="bi bi-chevron-down"></i> Detalle
                                    </button>
                                    <div class="collapse mt-2" id="detalle-{{ $item['producto']->id }}">
                                        <div class="card card-body p-2">
                                            @foreach($item['producto']->etiquetas as $etiqueta)
                                                <div class="mb-2">
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
                                <form action="{{ route('carrito.actualizar', $item['producto']) }}" method="POST" class="d-flex gap-1">
                                    @csrf
                                    @method('PUT')
                                    <input type="number" name="cantidad" value="{{ $item['cantidad'] }}" min="1" class="form-control form-control-sm" style="width: 70px;">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </form>
                            </td>
                            @if($mostrarPrecios)
                                <td>${{ number_format($item['subtotal'], 2) }}</td>
                            @endif
                            <td>
                                <form action="{{ route('carrito.eliminar', $item['producto']) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
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
                            <td><strong class="text-primary">${{ number_format($total, 2) }}</strong></td>
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
@endsection
