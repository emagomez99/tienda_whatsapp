@extends('layouts.app')

@section('title', 'Finalizar Pedido')

@section('content')
<div class="container py-4">
    <h2><i class="bi bi-whatsapp"></i> Finalizar Pedido</h2>

    <div class="row mt-4">
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-person"></i> Datos de contacto</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('carrito.enviar') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre" value="{{ old('nombre') }}" required>
                                @error('nombre')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="apellido" class="form-label">Apellido *</label>
                                <input type="text" class="form-control @error('apellido') is-invalid @enderror" id="apellido" name="apellido" value="{{ old('apellido') }}" required>
                                @error('apellido')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="celular" class="form-label">Celular *</label>
                            <input type="text" class="form-control @error('celular') is-invalid @enderror" id="celular" name="celular" value="{{ old('celular') }}" placeholder="Ej: 11-1234-5678" required>
                            @error('celular')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Al enviar el pedido, serás redirigido a WhatsApp para completar la comunicación con el vendedor.
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('carrito.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Volver al carrito
                            </a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-whatsapp"></i> Enviar pedido por WhatsApp
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-cart3"></i> Resumen del pedido</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @foreach($productos as $item)
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold">{{ $item['producto']->descripcion }}</div>
                                    <small class="text-muted">Cantidad: {{ $item['cantidad'] }}</small>
                                    @if($item['producto']->id_proveedor)
                                        <br><small class="text-muted">Cod: {{ $item['producto']->id_proveedor }}</small>
                                    @endif
                                </div>
                                @if($mostrarPrecios)
                                    <span class="badge bg-primary rounded-pill">${{ number_format($item['subtotal'], 2) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if($mostrarPrecios)
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between">
                                <strong>Total:</strong>
                                <strong class="text-primary">${{ number_format($total, 2) }}</strong>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
