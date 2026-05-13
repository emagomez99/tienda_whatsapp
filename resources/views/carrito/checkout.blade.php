@extends('layouts.app')

@section('title', 'Finalizar Pedido')

@section('content')
<div class="container py-4">
    <h2><i class="bi bi-whatsapp"></i> Finalizar Pedido</h2>

    @php
        $todosAjustes = array_merge($ajustes ?? [], session('ajustes_stock', []));
    @endphp
    @if(!empty($todosAjustes))
        <div class="alert alert-warning mt-3">
            <i class="bi bi-exclamation-triangle"></i> <strong>El stock de algunos productos cambió. Tu pedido fue actualizado antes de continuar:</strong>
            <ul class="mb-0 mt-1">
                @foreach($todosAjustes as $ajuste)
                    <li>{{ $ajuste }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
                            @include('partials.intl-tel-input', ['inputId' => 'celular-input', 'fieldName' => 'celular', 'value' => '', 'required' => true, 'label' => 'Celular'])
                        </div>

                        @if($pedirDireccion)
                        <hr class="my-3">
                        <h6 class="text-muted mb-3"><i class="bi bi-geo-alt"></i> Dirección de envío</h6>

                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección *</label>
                            <input type="text" class="form-control @error('direccion') is-invalid @enderror" id="direccion" name="direccion" value="{{ old('direccion') }}" placeholder="Ej: Av. Corrientes 1234" required>
                            @error('direccion')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="localidad" class="form-label">Localidad *</label>
                                <input type="text" class="form-control @error('localidad') is-invalid @enderror" id="localidad" name="localidad" value="{{ old('localidad') }}" required>
                                @error('localidad')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="provincia" class="form-label">Provincia *</label>
                                <input type="text" class="form-control @error('provincia') is-invalid @enderror" id="provincia" name="provincia" value="{{ old('provincia') }}" required>
                                @error('provincia')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="cp" class="form-label">Código Postal *</label>
                            <input type="text" class="form-control @error('cp') is-invalid @enderror" id="cp" name="cp" value="{{ old('cp') }}" placeholder="Ej: 1414" required>
                            @error('cp')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Al enviar el pedido, serás redirigido a WhatsApp para completar la comunicación con el vendedor.
                        </div>

                        <div class="d-flex flex-column-reverse flex-sm-row justify-content-sm-between gap-2 mt-2">
                            <a href="{{ route('carrito.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Volver al carrito
                            </a>
                            <button type="submit" class="btn btn-success">
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
                                    <span class="badge bg-primary rounded-pill">
                                        {{ $item['producto']->moneda ? $item['producto']->moneda->simbolo : '$' }}{{ number_format($item['subtotal'], 2, ',', '.') }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if($mostrarPrecios)
                        <div class="card-footer bg-light">
                            @foreach($totalesPorMoneda as $grupo)
                                @php $simbolo = $grupo['moneda'] ? $grupo['moneda']->simbolo : '$'; @endphp
                                <div class="d-flex justify-content-between{{ $loop->first ? '' : ' mt-1' }}">
                                    <strong>{{ $grupo['moneda'] ? 'Total ' . $grupo['moneda']->nombre . ':' : 'Total:' }}</strong>
                                    <strong class="text-primary">{{ $simbolo }}{{ number_format($grupo['total'], 2, ',', '.') }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

