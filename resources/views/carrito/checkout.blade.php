@extends('layouts.app')

@section('title', 'Finalizar Pedido')

@section('content')
<div class="container">
    <header class="listado-head">
        <div class="listado-head-text">
            <nav class="crumbs" aria-label="Ruta de navegación">
                <a href="{{ route('carrito.index') }}">Carrito</a>
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                <span aria-current="page">Finalizar pedido</span>
            </nav>
            <h1 class="page-title">Finalizar pedido</h1>
            <p class="page-sub">Completá tus datos y te llevamos a WhatsApp para confirmar el pedido con el vendedor.</p>
        </div>
    </header>

    @php
        $todosAjustes = array_merge($ajustes ?? [], session('ajustes_stock', []));
    @endphp
    @if(!empty($todosAjustes))
        <div class="tienda-nota tienda-nota-warn">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>El stock de algunos productos cambió. Tu pedido fue actualizado antes de continuar:</strong>
                <ul>
                    @foreach($todosAjustes as $ajuste)
                        <li>{{ $ajuste }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="row g-4 align-items-start">
        <div class="col-lg-7">
            <div class="panel">
                <form action="{{ route('carrito.enviar') }}" method="POST">
                    @csrf

                    <section class="step">
                        <h2 class="step-title"><span class="step-num">1</span> Datos de contacto</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre" value="{{ old('nombre') }}" autocomplete="given-name" required>
                                @error('nombre')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="apellido" class="form-label">Apellido *</label>
                                <input type="text" class="form-control @error('apellido') is-invalid @enderror" id="apellido" name="apellido" value="{{ old('apellido') }}" autocomplete="family-name" required>
                                @error('apellido')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                @include('partials.intl-tel-input', ['inputId' => 'celular-input', 'fieldName' => 'celular', 'value' => '', 'required' => true, 'label' => 'Celular'])
                            </div>
                        </div>
                    </section>

                    @if($pedirDireccion)
                        <section class="step">
                            <h2 class="step-title"><span class="step-num">2</span> Dirección de envío</h2>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="direccion" class="form-label">Dirección *</label>
                                    <input type="text" class="form-control @error('direccion') is-invalid @enderror" id="direccion" name="direccion" value="{{ old('direccion') }}" placeholder="Ej: Av. Corrientes 1234" autocomplete="street-address" required>
                                    @error('direccion')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="localidad" class="form-label">Localidad *</label>
                                    <input type="text" class="form-control @error('localidad') is-invalid @enderror" id="localidad" name="localidad" value="{{ old('localidad') }}" autocomplete="address-level2" required>
                                    @error('localidad')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="provincia" class="form-label">Provincia *</label>
                                    <input type="text" class="form-control @error('provincia') is-invalid @enderror" id="provincia" name="provincia" value="{{ old('provincia') }}" autocomplete="address-level1" required>
                                    @error('provincia')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="cp" class="form-label">Código Postal *</label>
                                    <input type="text" class="form-control @error('cp') is-invalid @enderror" id="cp" name="cp" value="{{ old('cp') }}" placeholder="Ej: 1414" autocomplete="postal-code" required>
                                    @error('cp')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </section>
                    @endif

                    <div class="tienda-nota tienda-nota-info mt-4 mb-0">
                        <i class="bi bi-whatsapp"></i>
                        <div>Al enviar el pedido, serás redirigido a WhatsApp para completar la comunicación con el vendedor.</div>
                    </div>

                    <div class="d-flex flex-column-reverse flex-sm-row justify-content-sm-between align-items-sm-center gap-2 mt-4">
                        <a href="{{ route('carrito.index') }}" class="link-quiet justify-content-center">
                            <i class="bi bi-arrow-left"></i> Volver al carrito
                        </a>
                        <button type="submit" class="btn btn-success btn-cta">
                            <i class="bi bi-whatsapp"></i> Enviar pedido por WhatsApp
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <aside class="panel panel-sticky">
                <h2 class="cart-summary-title">Resumen del pedido</h2>
                <div>
                    @foreach($productos as $item)
                        @php $prod = $item['producto']; @endphp
                        <div class="resumen-item">
                            <div class="resumen-thumb">
                                <img src="{{ $prod->imagen_url ?? '/img/no-image.svg' }}" alt="" loading="lazy"
                                     onerror="this.onerror=null;this.src='/img/no-image.svg';">
                            </div>
                            <div class="resumen-info">
                                <div class="resumen-name">{{ $prod->descripcion }}</div>
                                <div class="resumen-meta">
                                    Cantidad: {{ $item['cantidad'] }}@if($prod->id_proveedor) · Cód. {{ $prod->id_proveedor }}@endif
                                </div>
                            </div>
                            @if($mostrarPrecios)
                                <div class="resumen-price">
                                    {{ $prod->moneda ? $prod->moneda->simbolo : '$' }}{{ number_format($item['subtotal'], 2, ',', '.') }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if($mostrarPrecios)
                    <div class="summary-divider"></div>
                    @foreach($totalesPorMoneda as $grupo)
                        @php $simbolo = $grupo['moneda'] ? $grupo['moneda']->simbolo : '$'; @endphp
                        <div class="summary-row">
                            <span class="summary-row-label">{{ $grupo['moneda'] ? 'Total ' . $grupo['moneda']->nombre : 'Total' }}</span>
                            <span class="summary-row-value">{{ $simbolo }}{{ number_format($grupo['total'], 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                @endif
            </aside>
        </div>
    </div>
</div>
@endsection
