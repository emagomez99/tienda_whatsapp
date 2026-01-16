@extends('layouts.admin')

@section('title', 'Configuraciones')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-sliders"></i> Configuraciones</h2>
</div>

<form action="{{ route('admin.configuraciones.update') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-palette"></i> Apariencia</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label for="logo" class="form-label">Logo de la Tienda</label>
                        @php $logoActual = App\Models\Configuracion::logo(); @endphp
                        @if($logoActual)
                            <div class="mb-2 d-flex align-items-center gap-3">
                                <img src="{{ asset('storage/' . $logoActual) }}" alt="Logo actual" style="max-height: 60px;" class="rounded border">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="eliminar_logo" name="eliminar_logo" value="1">
                                    <label class="form-check-label text-danger" for="eliminar_logo">
                                        <i class="bi bi-trash"></i> Eliminar logo
                                    </label>
                                </div>
                            </div>
                        @endif
                        <input type="file" class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" accept="image/*">
                        @error('logo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Formatos: JPG, PNG, GIF. Máx: 2MB</small>
                    </div>

                    <div class="mb-4">
                        <label for="nombre_tienda" class="form-label">Nombre de la Tienda</label>
                        <input type="text" class="form-control @error('nombre_tienda') is-invalid @enderror" id="nombre_tienda" name="nombre_tienda" value="{{ App\Models\Configuracion::obtener('nombre_tienda', 'Tienda MC') }}">
                        @error('nombre_tienda')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Mostrar Nombre en Cabecera</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_nombre_tienda" id="nombre_si" value="true" {{ App\Models\Configuracion::obtener('mostrar_nombre_tienda', 'true') === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="nombre_si">
                                    <i class="bi bi-eye"></i> Sí
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_nombre_tienda" id="nombre_no" value="false" {{ App\Models\Configuracion::obtener('mostrar_nombre_tienda', 'true') === 'false' ? 'checked' : '' }}>
                                <label class="form-check-label" for="nombre_no">
                                    <i class="bi bi-eye-slash"></i> No, solo logo
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Paleta de Colores</label>
                        @php
                            $paletas = App\Models\Configuracion::paletas();
                            $paletaActual = App\Models\Configuracion::paleta();
                        @endphp
                        <div class="row g-2">
                            @foreach($paletas as $clave => $paleta)
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="paleta" id="paleta_{{ $clave }}" value="{{ $clave }}" {{ $paletaActual === $clave ? 'checked' : '' }}>
                                        <label class="form-check-label d-flex align-items-center gap-2" for="paleta_{{ $clave }}">
                                            <span class="rounded-circle d-inline-block border" style="width: 18px; height: 18px; background-color: {{ $paleta['primary'] }};"></span>
                                            {{ $paleta['nombre'] }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-shop"></i> Tienda</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label for="whatsapp_admin" class="form-label">Número de WhatsApp</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                            <input type="text" class="form-control @error('whatsapp_admin') is-invalid @enderror" id="whatsapp_admin" name="whatsapp_admin" value="{{ App\Models\Configuracion::obtener('whatsapp_admin', '') }}" placeholder="5491112345678">
                        </div>
                        @error('whatsapp_admin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Código de país + número. Ej: 5491112345678</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Mostrar Precios</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_precios" id="precios_si" value="true" {{ App\Models\Configuracion::obtener('mostrar_precios', 'true') === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="precios_si">
                                    <i class="bi bi-eye"></i> Sí
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_precios" id="precios_no" value="false" {{ App\Models\Configuracion::obtener('mostrar_precios', 'true') === 'false' ? 'checked' : '' }}>
                                <label class="form-check-label" for="precios_no">
                                    <i class="bi bi-eye-slash"></i> Ocultar
                                </label>
                            </div>
                        </div>
                        <small class="text-muted">Si se ocultan, los clientes consultarán por WhatsApp</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mostrar Productos sin Stock</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_productos_sin_stock" id="sin_stock_si" value="true" {{ App\Models\Configuracion::obtener('mostrar_productos_sin_stock', 'true') === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="sin_stock_si">
                                    <i class="bi bi-eye"></i> Sí
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_productos_sin_stock" id="sin_stock_no" value="false" {{ App\Models\Configuracion::obtener('mostrar_productos_sin_stock', 'true') === 'false' ? 'checked' : '' }}>
                                <label class="form-check-label" for="sin_stock_no">
                                    <i class="bi bi-eye-slash"></i> Ocultar
                                </label>
                            </div>
                        </div>
                        <small class="text-muted">Si se ocultan, solo se verán productos disponibles</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-circle"></i> Guardar Configuraciones
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
