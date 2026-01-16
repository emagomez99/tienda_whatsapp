@extends('layouts.admin')

@section('title', 'Configuraciones')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-sliders"></i> Configuraciones</h2>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Configuración General</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.configuraciones.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="nombre_tienda" class="form-label">Nombre de la Tienda</label>
                        <input type="text" class="form-control @error('nombre_tienda') is-invalid @enderror" id="nombre_tienda" name="nombre_tienda" value="{{ App\Models\Configuracion::obtener('nombre_tienda', 'Tienda MC') }}">
                        @error('nombre_tienda')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Este nombre se mostrará en la cabecera de la tienda</small>
                    </div>

                    <div class="mb-4">
                        <label for="whatsapp_admin" class="form-label">Número de WhatsApp del Administrador</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                            <input type="text" class="form-control @error('whatsapp_admin') is-invalid @enderror" id="whatsapp_admin" name="whatsapp_admin" value="{{ App\Models\Configuracion::obtener('whatsapp_admin', '') }}" placeholder="5491112345678">
                        </div>
                        @error('whatsapp_admin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Formato: código de país + número sin espacios ni guiones. Ej: 5491112345678</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Mostrar Precios en la Tienda</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_precios" id="precios_si" value="true" {{ App\Models\Configuracion::obtener('mostrar_precios', 'true') === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="precios_si">
                                    <i class="bi bi-eye"></i> Sí, mostrar precios
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_precios" id="precios_no" value="false" {{ App\Models\Configuracion::obtener('mostrar_precios', 'true') === 'false' ? 'checked' : '' }}>
                                <label class="form-check-label" for="precios_no">
                                    <i class="bi bi-eye-slash"></i> No, ocultar precios
                                </label>
                            </div>
                        </div>
                        <small class="text-muted">Si se ocultan los precios, los clientes deberán consultar por WhatsApp</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Mostrar Productos sin Stock</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_productos_sin_stock" id="sin_stock_si" value="true" {{ App\Models\Configuracion::obtener('mostrar_productos_sin_stock', 'true') === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="sin_stock_si">
                                    <i class="bi bi-eye"></i> Sí, mostrar sin stock
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_productos_sin_stock" id="sin_stock_no" value="false" {{ App\Models\Configuracion::obtener('mostrar_productos_sin_stock', 'true') === 'false' ? 'checked' : '' }}>
                                <label class="form-check-label" for="sin_stock_no">
                                    <i class="bi bi-eye-slash"></i> No, ocultar sin stock
                                </label>
                            </div>
                        </div>
                        <small class="text-muted">Si se ocultan, solo se mostrarán productos con stock disponible o habilitados por encargue</small>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Guardar Configuraciones
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Información</h5>
            </div>
            <div class="card-body">
                <p><strong>Nombre de la tienda:</strong><br>Se muestra en el logo/cabecera de la tienda pública.</p>
                <p><strong>WhatsApp:</strong><br>Los pedidos del carrito se enviarán a este número.</p>
                <p><strong>Precios:</strong><br>Si se ocultan, los productos se mostrarán sin precio y el cliente deberá consultar.</p>
                <p><strong>Productos sin stock:</strong><br>Controla si se muestran productos agotados. Si se ocultan, solo verán productos disponibles.</p>
            </div>
        </div>
    </div>
</div>
@endsection
