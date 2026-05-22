@extends('layouts.admin')

@section('title', 'Editar Producto')

@section('content')
@php
    $backUrl = request('_back_url')
        ? request('_back_url')
        : route('admin.productos.index') . (request('_back') ? '?' . request('_back') : '');
@endphp
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-pencil"></i> Editar Producto</h3>
    <a href="{{ $backUrl }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form id="form-producto" action="{{ route('admin.productos.update', $producto) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @if($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Información del Producto</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Nombre del Producto *</label>
                        <input type="text" class="form-control @error('descripcion') is-invalid @enderror" id="descripcion" name="descripcion" value="{{ old('descripcion', $producto->descripcion) }}" required>
                        @error('descripcion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción detallada</label>
                        <div id="detalle-editor" class="@error('detalle') is-invalid @enderror"></div>
                        <input type="hidden" name="detalle" id="detalle" value="{{ old('detalle', $producto->detalle) }}">
                        @error('detalle')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="proveedor_id" class="form-label">Proveedor *</label>
                            <select class="form-select @error('proveedor_id') is-invalid @enderror" id="proveedor_id" name="proveedor_id" required>
                                <option value="">Seleccionar proveedor</option>
                                @foreach($proveedores as $proveedor)
                                    <option value="{{ $proveedor->id }}" data-prefijo="{{ $proveedor->prefijo }}" {{ old('proveedor_id', $producto->proveedor_id) == $proveedor->id ? 'selected' : '' }}>
                                        {{ $proveedor->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            @error('proveedor_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="id_proveedor" class="form-label">Código Proveedor
                                <i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top"
                                   title="Código interno del proveedor. Aparece en el detalle del producto y se usa en búsquedas. El botón ↺ genera uno automático con el prefijo del proveedor."></i>
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control @error('id_proveedor') is-invalid @enderror" id="id_proveedor" name="id_proveedor" value="{{ old('id_proveedor', $producto->id_proveedor) }}">
                                <button type="button" class="btn btn-outline-secondary" id="btn-generar-codigo" title="Generar código">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                            @error('id_proveedor')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="moneda_id" class="form-label">Moneda
                                <i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top"
                                   title="Moneda en la que se expresa el precio. Si el precio no aplica, dejá sin seleccionar."></i>
                            </label>
                            <select class="form-select @error('moneda_id') is-invalid @enderror" id="moneda_id" name="moneda_id">
                                <option value="">Seleccionar moneda</option>
                                @foreach($monedas as $moneda)
                                    <option value="{{ $moneda->id }}" {{ old('moneda_id', $producto->moneda_id) == $moneda->id ? 'selected' : '' }}>
                                        {{ $moneda->nombre }} ({{ $moneda->codigo }})
                                    </option>
                                @endforeach
                            </select>
                            @error('moneda_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="precio" class="form-label">Precio *
                                <i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top"
                                   title="Precio de venta al público. Solo se muestra en la tienda si la configuración 'Mostrar precios' está activa."></i>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" class="form-control @error('precio') is-invalid @enderror" id="precio" name="precio" value="{{ old('precio', $producto->precio) }}" required>
                            </div>
                            @error('precio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock actual
                                <i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top"
                                   title="El stock no se edita directamente. Usá 'Ajustar' para registrar entradas o salidas con trazabilidad completa en el historial."></i>
                            </label>
                            <div class="input-group">
                                <span class="form-control bg-light fw-semibold text-center" style="max-width: 80px;">{{ $producto->stock }}</span>
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-ajuste-stock">
                                    <i class="bi bi-arrow-left-right"></i> Ajustar
                                </button>
                                <a href="{{ route('admin.productos.historial', $producto) }}" class="btn btn-outline-info" title="Ver historial de movimientos">
                                    <i class="bi bi-clock-history"></i>
                                </a>
                            </div>
                            <small class="text-muted">El stock se modifica mediante movimientos trazables.</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label d-block">Disponible
                                <i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top"
                                   title="Si está desactivado, el producto no aparece en la tienda aunque tenga stock."></i>
                            </label>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="disponible" name="disponible" value="1" {{ old('disponible', $producto->disponible) ? 'checked' : '' }}>
                                <label class="form-check-label" for="disponible">Mostrar en tienda</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label d-block">Por Encargue
                                <i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top"
                                   title="Permite que el cliente lo solicite aunque no haya stock. Aparece con la etiqueta 'Disponible por encargue' en la tienda."></i>
                            </label>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="por_encargue" name="por_encargue" value="1" {{ old('por_encargue', $producto->por_encargue) ? 'checked' : '' }}>
                                <label class="form-check-label" for="por_encargue">Disponible sin stock</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Imagen principal -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-image"></i> Imagen Principal
                        <i class="bi bi-question-circle text-muted ms-1 fs-6" data-bs-toggle="tooltip" data-bs-placement="top"
                           title="Imagen que aparece en el listado de productos y en la vista de detalle. Se recomienda fondo blanco o transparente."></i>
                    </h5>
                </div>
                <div class="card-body">
                    @if($producto->url_imagen)
                        <div class="mb-3 p-3 bg-light rounded" id="imagen-actual">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <img src="{{ $producto->imagen_url }}" alt="" style="max-height: 120px;" class="rounded">
                                </div>
                                <div class="col">
                                    <p class="mb-1"><strong>Imagen actual</strong></p>
                                    @if($producto->esImagenExterna())
                                        <small class="text-muted"><i class="bi bi-link-45deg"></i> URL externa</small>
                                    @else
                                        <small class="text-muted"><i class="bi bi-hdd"></i> Archivo local</small>
                                    @endif
                                </div>
                                <div class="col-auto">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="eliminar_imagen" name="eliminar_imagen" value="1">
                                        <label class="form-check-label text-danger" for="eliminar_imagen">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <p class="text-muted mb-2"><small>Cambiar imagen principal:</small></p>
                    @endif

                    @php $modoImagen = App\Models\Configuracion::modoImagenProducto(); @endphp

                    @if($modoImagen === 'solo_url')
                        <input type="url" class="form-control @error('imagen_url') is-invalid @enderror"
                               id="imagen_url" name="imagen_url"
                               placeholder="https://ejemplo.com/imagen.jpg" value="{{ old('imagen_url') }}">
                        @error('imagen_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Ingresa la URL completa de la imagen externa.</small>

                    @elseif($modoImagen === 'solo_archivo')
                        <input type="file" class="form-control @error('imagen_archivo') is-invalid @enderror"
                               id="imagen_archivo" name="imagen_archivo" accept="image/*">
                        @error('imagen_archivo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Formatos: JPG, PNG, GIF. Máximo 2MB.</small>

                    @else
                        <ul class="nav nav-tabs" id="imagenTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="archivo-tab" data-bs-toggle="tab" data-bs-target="#archivo-panel" type="button" role="tab">
                                    <i class="bi bi-upload"></i> Subir archivo
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="url-tab" data-bs-toggle="tab" data-bs-target="#url-panel" type="button" role="tab">
                                    <i class="bi bi-link-45deg"></i> URL externa
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content pt-3" id="imagenTabsContent">
                            <div class="tab-pane fade show active" id="archivo-panel" role="tabpanel">
                                <input type="file" class="form-control @error('imagen_archivo') is-invalid @enderror" id="imagen_archivo" name="imagen_archivo" accept="image/*">
                                @error('imagen_archivo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Formatos: JPG, PNG, GIF. Máximo 2MB.</small>
                            </div>
                            <div class="tab-pane fade" id="url-panel" role="tabpanel">
                                <input type="url" class="form-control @error('imagen_url') is-invalid @enderror" id="imagen_url" name="imagen_url" placeholder="https://ejemplo.com/imagen.jpg" value="{{ old('imagen_url') }}">
                                @error('imagen_url')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Ingresa la URL completa de la imagen externa.</small>
                            </div>
                        </div>
                    @endif
                    <div id="imagen-preview" class="mt-3 text-center" style="display: none;">
                        <p class="text-muted mb-1"><small>Nueva imagen:</small></p>
                        <img src="" alt="Preview" class="img-thumbnail" style="max-height: 200px;">
                    </div>
                </div>
            </div>

            <!-- Card: Imágenes adicionales -->
            @if($imagenesAdicionalesActivas)
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-images"></i> Imágenes Adicionales
                        <i class="bi bi-question-circle text-muted ms-1 fs-6" data-bs-toggle="tooltip" data-bs-placement="top"
                           title="Se muestran en el carrusel de la vista detallada del producto. Podés agregar varias antes de guardar. Con ★ podés promover cualquiera como imagen principal."></i>
                    </h5>
                    <button type="button" id="btn-abrir-agregar" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg"></i> Agregar imagen
                    </button>
                </div>
                <div class="card-body">

                    {{-- Grid de imágenes existentes --}}
                    <div class="d-flex flex-wrap gap-3" id="grid-extras">
                        @forelse($producto->imagenes as $img)
                            <div class="img-extra-card position-relative" data-id="{{ $img->id }}" style="width:120px;">
                                <div class="rounded border overflow-hidden" style="height:90px;">
                                    <img src="{{ $img->imagen_url }}" alt=""
                                         style="width:100%;height:100%;object-fit:contain;"
                                         onerror="this.src='/img/no-image.svg';">
                                    <div class="img-overlay">
                                        <button type="button" class="btn btn-light btn-sm btn-hacer-portada px-2 py-1"
                                                data-id="{{ $img->id }}" title="Hacer imagen principal">
                                            <i class="bi bi-star-fill text-warning"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm btn-eliminar-extra px-2 py-1"
                                                data-id="{{ $img->id }}" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="text-center mt-1" style="font-size:.65rem;color:#888;">
                                    @if($img->esExterna()) <i class="bi bi-link-45deg"></i> URL
                                    @else <i class="bi bi-hdd"></i> Local @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0" id="msg-sin-extras">No hay imágenes adicionales aún.</p>
                        @endforelse
                    </div>

                    {{-- Panel agregar --}}
                    <div id="panel-agregar-extras" class="mt-3 border rounded p-3" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-semibold small">Nueva imagen</span>
                            <button type="button" id="btn-cerrar-agregar" class="btn-close" aria-label="Cerrar"></button>
                        </div>

                        @if($modoImagen !== 'solo_archivo')
                            <div class="mb-3">
                                <label class="form-label small fw-semibold mb-1">
                                    <i class="bi bi-link-45deg"></i> Pegar URL
                                </label>
                                <div class="input-group">
                                    <input type="url" id="input-url-nueva" class="form-control"
                                           placeholder="https://ejemplo.com/imagen.jpg">
                                    <button type="button" id="btn-agregar-url" class="btn btn-primary">
                                        <i class="bi bi-plus-lg"></i> Agregar
                                    </button>
                                </div>
                                <div id="preview-url-nueva" class="mt-2" style="display:none;">
                                    <img id="preview-url-img" src=""
                                         style="height:64px;object-fit:contain;border-radius:4px;border:1px solid #dee2e6;">
                                </div>
                            </div>
                        @endif

                        @if($modoImagen !== 'solo_url')
                            <div class="mb-2">
                                <label class="form-label small fw-semibold mb-1">
                                    <i class="bi bi-upload"></i> Subir archivos
                                </label>
                                <input type="file" id="imagenes_nuevas" name="imagenes_nuevas[]"
                                       class="form-control @error('imagenes_nuevas') is-invalid @enderror"
                                       accept="image/*" multiple>
                                @error('imagenes_nuevas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="text-muted">Podés seleccionar varias. Máx. 2MB c/u.</small>
                                <div id="preview-archivos" class="d-flex flex-wrap gap-2 mt-2"></div>
                            </div>
                        @endif

                        {{-- Cola de URLs pendientes --}}
                        <div id="cola-urls" style="display:none;">
                            <hr class="my-2">
                            <p class="text-muted small mb-2">
                                <i class="bi bi-clock-history"></i> En cola para guardar:
                            </p>
                            <div id="cola-urls-items" class="d-flex flex-wrap gap-2"></div>
                        </div>
                    </div>

                    <input type="hidden" name="hacer_portada_id" id="hacer_portada_id" value="">
                </div>
            </div>
            @endif {{-- imagenesAdicionalesActivas --}}

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Etiquetas
                        <i class="bi bi-question-circle text-muted ms-1 fs-6" data-bs-toggle="tooltip" data-bs-placement="right"
                           title="Categorizan el producto y habilitan filtros en la tienda. Cada etiqueta tiene un nombre y un valor específico para este producto. Ej: Marca → Toyota, Aplicación → Filtro de aceite."></i>
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="agregar-etiqueta">
                        <i class="bi bi-plus"></i> Agregar
                    </button>
                </div>
                <div class="card-body">
                    @php
                        $etiquetasProducto = $producto->etiquetas->keyBy('id');
                    @endphp
                    <div id="etiquetas-container">
                        @forelse($producto->etiquetas as $index => $etiquetaProd)
                            <div class="row mb-2 etiqueta-row">
                                <div class="col-md-5">
                                    <select class="form-select etiqueta-select" name="etiquetas[{{ $index }}][etiqueta_id]" data-index="{{ $index }}">
                                        <option value="">Seleccionar etiqueta</option>
                                        @foreach($etiquetas as $etiqueta)
                                            <option value="{{ $etiqueta->id }}" {{ $etiquetaProd->id == $etiqueta->id ? 'selected' : '' }}>{{ $etiqueta->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5 position-relative">
                                    <input type="text" class="form-control etiqueta-valor" name="etiquetas[{{ $index }}][valor]" placeholder="Valor (ej: Filtro, Auto)" value="{{ $etiquetaProd->pivot->valor }}" autocomplete="off">
                                    <div class="autocomplete-suggestions list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger btn-eliminar-etiqueta">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="row mb-2 etiqueta-row">
                                <div class="col-md-5">
                                    <select class="form-select etiqueta-select" name="etiquetas[0][etiqueta_id]" data-index="0">
                                        <option value="">Seleccionar etiqueta</option>
                                        @foreach($etiquetas as $etiqueta)
                                            <option value="{{ $etiqueta->id }}">{{ $etiqueta->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5 position-relative">
                                    <input type="text" class="form-control etiqueta-valor" name="etiquetas[0][valor]" placeholder="Valor (ej: Filtro, Auto)" autocomplete="off">
                                    <div class="autocomplete-suggestions list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger btn-eliminar-etiqueta">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @endforelse
                    </div>
                    <small class="text-muted" id="etiquetas-hint">Selecciona una etiqueta y asigna un valor especifico para este producto.</small>
                    @error('etiquetas')
                        <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Especificaciones
                        <i class="bi bi-question-circle text-muted ms-1 fs-6" data-bs-toggle="tooltip" data-bs-placement="right"
                           title="Tabla de características técnicas. Aparece en el detalle del producto. Ej: Peso → 1.75 kg, Material → Acero inoxidable."></i>
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="agregar-especificacion">
                        <i class="bi bi-plus"></i> Agregar
                    </button>
                </div>
                <div class="card-body">
                    <div id="especificaciones-container">
                        @forelse($producto->especificaciones as $index => $espec)
                            <div class="row mb-2 especificacion-row">
                                <div class="col-md-5 position-relative">
                                    <input type="text" class="form-control especificacion-clave" name="especificaciones[{{ $index }}][clave]" placeholder="Clave (ej: Peso)" value="{{ $espec->clave }}" autocomplete="off">
                                    <div class="autocomplete-suggestions list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                                </div>
                                <div class="col-md-5 position-relative">
                                    <input type="text" class="form-control especificacion-valor" name="especificaciones[{{ $index }}][valor]" placeholder="Valor (ej: 1.75)" value="{{ $espec->valor }}" autocomplete="off">
                                    <div class="autocomplete-suggestions list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger btn-eliminar-especificacion">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="row mb-2 especificacion-row">
                                <div class="col-md-5 position-relative">
                                    <input type="text" class="form-control especificacion-clave" name="especificaciones[0][clave]" placeholder="Clave (ej: Peso)" autocomplete="off">
                                    <div class="autocomplete-suggestions list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                                </div>
                                <div class="col-md-5 position-relative">
                                    <input type="text" class="form-control especificacion-valor" name="especificaciones[0][valor]" placeholder="Valor (ej: 1.75)" autocomplete="off">
                                    <div class="autocomplete-suggestions list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger btn-eliminar-especificacion">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-circle"></i> Actualizar Producto
                    </button>
                    @if(request('_back_url'))
                        <input type="hidden" name="_back_url" value="{{ request('_back_url') }}">
                    @else
                        <input type="hidden" name="_back" value="{{ request('_back', '') }}">
                    @endif
                    <a href="{{ $backUrl }}" class="btn btn-outline-secondary w-100">
                        Cancelar
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Modal: ajuste de stock -->
<div class="modal fade" id="modal-ajuste-stock" tabindex="-1" aria-labelledby="modal-ajuste-stock-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.productos.ajustar-stock', $producto) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-ajuste-stock-label">
                        <i class="bi bi-arrow-left-right"></i> Ajustar stock
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if($errors->has('variacion'))
                        <div class="alert alert-danger py-2">{{ $errors->first('variacion') }}</div>
                    @endif
                    <div class="alert alert-secondary py-2 mb-3">
                        Stock actual: <strong>{{ $producto->stock }}</strong>
                    </div>
                    <div class="mb-3">
                        <label for="modal-variacion" class="form-label fw-semibold">Variación *
                            <i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top"
                               title="Ingresá un número positivo para sumar stock (recepción de mercadería) o negativo para restar (salida, merma, ajuste)."></i>
                        </label>
                        <input type="number" name="variacion" id="modal-variacion" class="form-control"
                               placeholder="Ej: 10 para entrada, -5 para salida" required>
                        <small class="text-muted">Positivo = entrada de stock &nbsp;·&nbsp; Negativo = salida.</small>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted">Stock resultante:</span>
                            <strong id="stock-resultante-preview">{{ $producto->stock }}</strong>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label for="modal-descripcion" class="form-label">Motivo</label>
                        <input type="text" name="descripcion" id="modal-descripcion" class="form-control"
                               placeholder="Ej: Recepción de mercadería, corrección de inventario">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Confirmar ajuste
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/quill/quill.snow.css') }}">
<style>
    #detalle-editor { min-height: 120px; background: #fff; }
    .ql-toolbar { border-radius: 6px 6px 0 0; }
    .ql-container { border-radius: 0 0 6px 6px; font-size: 1rem; }
    .autocomplete-suggestions .list-group-item {
        cursor: pointer;
        padding: 0.5rem 0.75rem;
    }
    .autocomplete-suggestions .list-group-item:hover {
        background-color: #e9ecef;
    }
    .etiqueta-bloqueada {
        pointer-events: none;
        background-color: #fff5f5;
        border-color: #dc3545;
        color: #495057;
    }
    .img-extra-card { transition: opacity .2s, outline .15s; }
    .img-extra-card .img-overlay {
        position: absolute; inset: 0; border-radius: .375rem;
        background: rgba(0,0,0,.52); opacity: 0;
        display: flex; align-items: center; justify-content: center; gap: 6px;
        transition: opacity .18s;
    }
    .img-extra-card:hover .img-overlay { opacity: 1; }
    #panel-agregar-extras { background: #f8f9fa; }
    .cola-card { position:relative; width:88px; height:66px; border-radius:.375rem; overflow:hidden; border:1px solid #dee2e6; flex-shrink:0; }
    .cola-card img { width:100%; height:100%; object-fit:contain; }
    .cola-card .cola-del { position:absolute; top:2px; right:2px; width:18px; height:18px; border-radius:50%; border:none; background:rgba(220,53,69,.9); color:#fff; font-size:12px; line-height:1; cursor:pointer; padding:0; display:flex; align-items:center; justify-content:center; }
    .border-dashed-add { border: 2px dashed #adb5bd !important; cursor:pointer; transition: border-color .15s, background .15s; }
    .border-dashed-add:hover { border-color: #0d6efd !important; background: #e8f0fe; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/quill/quill.min.js') }}"></script>
<script>
    var quill = new Quill('#detalle-editor', {
        theme: 'snow',
        placeholder: 'Descripción completa del producto, características, usos, etc.',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['clean']
            ]
        }
    });

    var detalleInicial = document.getElementById('detalle').value;
    if (detalleInicial) quill.clipboard.dangerouslyPasteHTML(detalleInicial);

    document.getElementById('form-producto').addEventListener('submit', function() {
        var contenido = quill.root.innerHTML;
        document.getElementById('detalle').value = contenido === '<p><br></p>' ? '' : contenido;
    });

    let especificacionIndex = {{ $producto->especificaciones->count() ?: 1 }};
    let etiquetaIndex = {{ $producto->etiquetas->count() ?: 1 }};

    const etiquetasObligatoriasMapa = @json($etiquetasObligatorias);
    const etiquetasAplicablesMapa   = @json($etiquetasAplicables);
    const etiquetasData = @json($etiquetas->map(function ($e) { return ['id' => $e->id, 'nombre' => $e->nombre]; })->values());

    function getSelectedEtiquetaIds(exceptSelect) {
        var ids = [];
        document.querySelectorAll('.etiqueta-select').forEach(function(sel) {
            if (sel !== exceptSelect && sel.value) {
                ids.push(parseInt(sel.value));
            }
        });
        return ids;
    }

    function buildOptionsHtml(proveedorId, excludeIds) {
        excludeIds = excludeIds || [];
        var html = '<option value="">Seleccionar etiqueta</option>';
        var aplicables = (proveedorId && etiquetasAplicablesMapa.hasOwnProperty(String(proveedorId)))
            ? etiquetasAplicablesMapa[String(proveedorId)] : null;
        etiquetasData.forEach(function (e) {
            if ((aplicables === null || aplicables.indexOf(e.id) !== -1) && excludeIds.indexOf(e.id) === -1) {
                html += '<option value="' + e.id + '">' + e.nombre + '</option>';
            }
        });
        return html;
    }

    function refrescarOpciones(proveedorId) {
        document.querySelectorAll('.etiqueta-select').forEach(function(sel) {
            var currentVal = sel.value;
            sel.innerHTML = buildOptionsHtml(proveedorId || '', getSelectedEtiquetaIds(sel));
            if (currentVal) sel.value = currentVal;
        });
    }

    function crearFilaEtiqueta(preselectedId, proveedorId) {
        const newRow = document.createElement('div');
        newRow.className = 'row mb-2 etiqueta-row';
        newRow.innerHTML = `
            <div class="col-md-5">
                <select class="form-select etiqueta-select" name="etiquetas[${etiquetaIndex}][etiqueta_id]" data-index="${etiquetaIndex}">
                    ${buildOptionsHtml(proveedorId || '', [])}
                </select>
            </div>
            <div class="col-md-5 position-relative">
                <input type="text" class="form-control etiqueta-valor" name="etiquetas[${etiquetaIndex}][valor]" placeholder="Valor (ej: Filtro, Auto)" autocomplete="off">
                <div class="autocomplete-suggestions list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger btn-eliminar-etiqueta">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        if (preselectedId) {
            newRow.querySelector('.etiqueta-select').value = preselectedId;
        }
        setupAutocomplete(newRow.querySelector('.etiqueta-valor'));
        etiquetaIndex++;
        return newRow;
    }

    function agregarFilaEtiqueta(preselectedId, proveedorId) {
        const row = crearFilaEtiqueta(preselectedId, proveedorId);
        document.getElementById('etiquetas-container').appendChild(row);
        return row;
    }

    function limpiarTodasEtiquetas(proveedorId) {
        document.getElementById('etiquetas-container').innerHTML = '';
        agregarFilaEtiqueta(null, proveedorId);
    }

    function marcarObligatoria(row) {
        row.setAttribute('data-obligatoria', '1');
        const selectWrapper = row.querySelector('.col-md-5');
        const select = row.querySelector('.etiqueta-select');
        select.classList.add('etiqueta-bloqueada');
        if (!selectWrapper.querySelector('.badge-obligatoria')) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-danger badge-obligatoria mt-1 d-inline-block';
            badge.textContent = 'Obligatoria';
            selectWrapper.appendChild(badge);
        }
        row.querySelector('.btn-eliminar-etiqueta').classList.add('disabled');
    }

    function limpiarObligatorias() {
        document.querySelectorAll('.etiqueta-row').forEach(function(row) {
            row.removeAttribute('data-obligatoria');
            row.querySelector('.etiqueta-select').classList.remove('etiqueta-bloqueada');
            var badge = row.querySelector('.badge-obligatoria');
            if (badge) badge.remove();
            row.querySelector('.btn-eliminar-etiqueta').classList.remove('disabled');
        });
    }

    function actualizarObligatorias(proveedorId) {
        limpiarObligatorias();

        if (proveedorId && etiquetasObligatoriasMapa[proveedorId] && etiquetasObligatoriasMapa[proveedorId].length) {
            const container = document.getElementById('etiquetas-container');
            var insertAfter = null;

            etiquetasObligatoriasMapa[proveedorId].forEach(function(etiqueta) {
                var existingRow = null;
                document.querySelectorAll('.etiqueta-row').forEach(function(row) {
                    if (parseInt(row.querySelector('.etiqueta-select').value) === etiqueta.id) {
                        existingRow = row;
                    }
                });

                var targetRow = existingRow || crearFilaEtiqueta(etiqueta.id, proveedorId);

                if (insertAfter === null) {
                    container.insertBefore(targetRow, container.firstChild);
                } else if (insertAfter.nextSibling) {
                    container.insertBefore(targetRow, insertAfter.nextSibling);
                } else {
                    container.appendChild(targetRow);
                }

                targetRow.querySelector('.etiqueta-select').value = etiqueta.id;
                marcarObligatoria(targetRow);
                insertAfter = targetRow;
            });
        }

        refrescarOpciones(proveedorId);
    }

    document.getElementById('etiquetas-container').addEventListener('change', function(e) {
        if (e.target.classList.contains('etiqueta-select')) {
            refrescarOpciones(document.getElementById('proveedor_id').value);
        }
    });

    document.getElementById('btn-generar-codigo').addEventListener('click', function() {
        var sel = document.getElementById('proveedor_id');
        var option = sel.options[sel.selectedIndex];
        var prefijo = option ? (option.dataset.prefijo || '').trim().toUpperCase() : '';
        var numero = Math.floor(100000 + Math.random() * 900000);
        document.getElementById('id_proveedor').value = prefijo ? prefijo + '-' + numero : String(numero);
    });

    document.getElementById('proveedor_id').addEventListener('change', function() {
        var proveedorId = this.value;
        limpiarTodasEtiquetas(proveedorId);
        actualizarObligatorias(proveedorId);
    });

    // Al cargar: filtrar selects existentes según proveedor actual y marcar obligatorias
    (function() {
        var sel = document.getElementById('proveedor_id');
        if (sel.value) {
            actualizarObligatorias(sel.value);
        }
    })();

    // Autocompletado para valores de etiquetas
    let debounceTimer;
    function setupAutocomplete(input) {
        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const valor = this.value;
            const row = this.closest('.etiqueta-row');
            const select = row.querySelector('.etiqueta-select');
            const etiquetaId = select.value;
            const suggestionsDiv = row.querySelector('.autocomplete-suggestions');

            if (!etiquetaId || valor.length < 3) {
                suggestionsDiv.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`/admin/etiquetas/${etiquetaId}/valores?q=${encodeURIComponent(valor)}`)
                    .then(response => response.json())
                    .then(data => {
                        suggestionsDiv.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement('a');
                                div.href = '#';
                                div.className = 'list-group-item list-group-item-action';
                                div.textContent = item;
                                div.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    input.value = item;
                                    suggestionsDiv.style.display = 'none';
                                });
                                suggestionsDiv.appendChild(div);
                            });
                            suggestionsDiv.style.display = 'block';
                        } else {
                            suggestionsDiv.style.display = 'none';
                        }
                    });
            }, 300);
        });

        input.addEventListener('blur', function() {
            setTimeout(() => {
                const suggestionsDiv = this.closest('.etiqueta-row').querySelector('.autocomplete-suggestions');
                suggestionsDiv.style.display = 'none';
            }, 200);
        });
    }

    // Inicializar autocompletado en campos existentes
    document.querySelectorAll('.etiqueta-valor').forEach(setupAutocomplete);

    // Autocompletado para especificaciones (claves)
    let debounceTimerEspecClave;
    function setupAutocompleteEspecClave(input) {
        input.addEventListener('input', function() {
            clearTimeout(debounceTimerEspecClave);
            const valor = this.value;
            const suggestionsDiv = this.parentElement.querySelector('.autocomplete-suggestions');

            if (valor.length < 3) {
                suggestionsDiv.style.display = 'none';
                return;
            }

            debounceTimerEspecClave = setTimeout(() => {
                fetch(`/admin/especificaciones/claves?q=${encodeURIComponent(valor)}`)
                    .then(response => response.json())
                    .then(data => {
                        suggestionsDiv.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement('a');
                                div.href = '#';
                                div.className = 'list-group-item list-group-item-action';
                                div.textContent = item;
                                div.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    input.value = item;
                                    suggestionsDiv.style.display = 'none';
                                });
                                suggestionsDiv.appendChild(div);
                            });
                            suggestionsDiv.style.display = 'block';
                        } else {
                            suggestionsDiv.style.display = 'none';
                        }
                    });
            }, 300);
        });

        input.addEventListener('blur', function() {
            setTimeout(() => {
                const suggestionsDiv = this.parentElement.querySelector('.autocomplete-suggestions');
                suggestionsDiv.style.display = 'none';
            }, 200);
        });
    }

    // Autocompletado para especificaciones (valores)
    let debounceTimerEspecValor;
    function setupAutocompleteEspecValor(input) {
        input.addEventListener('input', function() {
            clearTimeout(debounceTimerEspecValor);
            const valor = this.value;
            const row = this.closest('.especificacion-row');
            const claveInput = row.querySelector('.especificacion-clave');
            const clave = claveInput ? claveInput.value : '';
            const suggestionsDiv = this.parentElement.querySelector('.autocomplete-suggestions');

            if (valor.length < 3) {
                suggestionsDiv.style.display = 'none';
                return;
            }

            debounceTimerEspecValor = setTimeout(() => {
                let url = `/admin/especificaciones/valores?q=${encodeURIComponent(valor)}`;
                if (clave) {
                    url += `&clave=${encodeURIComponent(clave)}`;
                }
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        suggestionsDiv.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement('a');
                                div.href = '#';
                                div.className = 'list-group-item list-group-item-action';
                                div.textContent = item;
                                div.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    input.value = item;
                                    suggestionsDiv.style.display = 'none';
                                });
                                suggestionsDiv.appendChild(div);
                            });
                            suggestionsDiv.style.display = 'block';
                        } else {
                            suggestionsDiv.style.display = 'none';
                        }
                    });
            }, 300);
        });

        input.addEventListener('blur', function() {
            setTimeout(() => {
                const suggestionsDiv = this.parentElement.querySelector('.autocomplete-suggestions');
                suggestionsDiv.style.display = 'none';
            }, 200);
        });
    }

    // Inicializar autocompletado en campos de especificaciones existentes
    document.querySelectorAll('.especificacion-clave').forEach(setupAutocompleteEspecClave);
    document.querySelectorAll('.especificacion-valor').forEach(setupAutocompleteEspecValor);

    document.getElementById('agregar-especificacion').addEventListener('click', function() {
        const container = document.getElementById('especificaciones-container');
        const newRow = document.createElement('div');
        newRow.className = 'row mb-2 especificacion-row';
        newRow.innerHTML = `
            <div class="col-md-5 position-relative">
                <input type="text" class="form-control especificacion-clave" name="especificaciones[${especificacionIndex}][clave]" placeholder="Clave (ej: Peso)" autocomplete="off">
                <div class="autocomplete-suggestions list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
            </div>
            <div class="col-md-5 position-relative">
                <input type="text" class="form-control especificacion-valor" name="especificaciones[${especificacionIndex}][valor]" placeholder="Valor (ej: 1.75)" autocomplete="off">
                <div class="autocomplete-suggestions list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger btn-eliminar-especificacion">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        container.appendChild(newRow);
        setupAutocompleteEspecClave(newRow.querySelector('.especificacion-clave'));
        setupAutocompleteEspecValor(newRow.querySelector('.especificacion-valor'));
        especificacionIndex++;
    });

    document.getElementById('agregar-etiqueta').addEventListener('click', function() {
        var proveedorId = document.getElementById('proveedor_id').value;
        agregarFilaEtiqueta(null, proveedorId);
        refrescarOpciones(proveedorId);
    });

    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-eliminar-especificacion')) {
            const rows = document.querySelectorAll('.especificacion-row');
            if (rows.length > 1) {
                e.target.closest('.especificacion-row').remove();
            } else {
                const row = e.target.closest('.especificacion-row');
                row.querySelector('.especificacion-clave').value = '';
                row.querySelector('.especificacion-valor').value = '';
            }
        }
        if (e.target.closest('.btn-eliminar-etiqueta')) {
            const btn = e.target.closest('.btn-eliminar-etiqueta');
            if (btn.classList.contains('disabled')) return;
            const rows = document.querySelectorAll('.etiqueta-row');
            if (rows.length > 1) {
                e.target.closest('.etiqueta-row').remove();
            } else {
                const row = e.target.closest('.etiqueta-row');
                row.querySelector('.etiqueta-select').value = '';
                row.querySelector('.etiqueta-valor').value = '';
            }
        }
    });

    // Preview imagen principal
    const imagenArchivo = document.getElementById('imagen_archivo');
    const imagenUrl = document.getElementById('imagen_url');
    const imagenPreview = document.getElementById('imagen-preview');
    const imagenPreviewImg = imagenPreview ? imagenPreview.querySelector('img') : null;
    const eliminarImagen = document.getElementById('eliminar_imagen');

    function mostrarPreview(src) {
        if (src && imagenPreviewImg) {
            imagenPreviewImg.src = src;
            imagenPreview.style.display = 'block';
        } else if (imagenPreview) {
            imagenPreview.style.display = 'none';
        }
    }

    if (imagenArchivo) {
        imagenArchivo.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { mostrarPreview(e.target.result); };
                reader.readAsDataURL(this.files[0]);
                if (imagenUrl) imagenUrl.value = '';
                if (eliminarImagen) eliminarImagen.checked = false;
            } else {
                mostrarPreview(null);
            }
        });
    }

    let debounceUrl;
    if (imagenUrl) {
        imagenUrl.addEventListener('input', function() {
            clearTimeout(debounceUrl);
            const url = this.value.trim();
            debounceUrl = setTimeout(() => {
                if (url && url.startsWith('http')) {
                    mostrarPreview(url);
                    if (imagenArchivo) imagenArchivo.value = '';
                    if (eliminarImagen) eliminarImagen.checked = false;
                } else {
                    mostrarPreview(null);
                }
            }, 500);
        });
    }

    const archivoTab = document.getElementById('archivo-tab');
    const urlTab = document.getElementById('url-tab');
    if (archivoTab) {
        archivoTab.addEventListener('shown.bs.tab', function() {
            if (imagenUrl) imagenUrl.value = '';
        });
    }
    if (urlTab) {
        urlTab.addEventListener('shown.bs.tab', function() {
            if (imagenArchivo) imagenArchivo.value = '';
            if (imagenPreview) imagenPreview.style.display = 'none';
        });
    }
    if (eliminarImagen) {
        eliminarImagen.addEventListener('change', function() {
            if (this.checked) {
                if (imagenArchivo) imagenArchivo.value = '';
                if (imagenUrl) imagenUrl.value = '';
                if (imagenPreview) imagenPreview.style.display = 'none';
            }
        });
    }

    // === Gestor de Imágenes Adicionales ===
    @if($imagenesAdicionalesActivas)
    (function() {
        var maxExtras  = {{ $maxImagenesAdicionales }};
        var totalActuales = {{ $producto->imagenes->count() }};
        var enCola = 0;
        var btnAbrir   = document.getElementById('btn-abrir-agregar');
        var btnCerrar  = document.getElementById('btn-cerrar-agregar');
        var panel      = document.getElementById('panel-agregar-extras');
        var inputUrl   = document.getElementById('input-url-nueva');
        var btnAgrUrl  = document.getElementById('btn-agregar-url');
        var prevWrap   = document.getElementById('preview-url-nueva');
        var prevImg    = document.getElementById('preview-url-img');
        var colaSection= document.getElementById('cola-urls');
        var colaItems  = document.getElementById('cola-urls-items');
        var inputFiles = document.getElementById('imagenes_nuevas');
        var prevArch   = document.getElementById('preview-archivos');
        var form       = document.getElementById('form-producto');
        var cid = 0, debUrl;

        if (btnAbrir) {
            btnAbrir.addEventListener('click', function() {
                panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
                if (panel.style.display === 'block' && inputUrl) inputUrl.focus();
            });
        }
        if (btnCerrar) {
            btnCerrar.addEventListener('click', function() { panel.style.display = 'none'; });
        }

        // Preview en vivo de URL
        if (inputUrl) {
            inputUrl.addEventListener('input', function() {
                clearTimeout(debUrl);
                var url = this.value.trim();
                debUrl = setTimeout(function() {
                    if (url && url.indexOf('http') === 0) {
                        prevImg.src = url;
                        prevWrap.style.display = 'block';
                    } else {
                        prevWrap.style.display = 'none';
                    }
                }, 400);
            });
            inputUrl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); agregarUrl(); }
            });
        }
        if (btnAgrUrl) btnAgrUrl.addEventListener('click', agregarUrl);

        function actualizarLimite() {
            var usadas = totalActuales + enCola;
            var restantes = maxExtras - usadas;
            if (btnAbrir) {
                if (restantes <= 0) {
                    btnAbrir.disabled = true;
                    btnAbrir.title = 'Límite de ' + maxExtras + ' imágenes adicionales alcanzado';
                } else {
                    btnAbrir.disabled = false;
                    btnAbrir.title = restantes === 1
                        ? 'Podés agregar 1 imagen más'
                        : 'Podés agregar hasta ' + restantes + ' imágenes más';
                }
            }
            if (btnAgrUrl) {
                btnAgrUrl.disabled = restantes <= 0;
            }
        }
        actualizarLimite();

        function agregarUrl() {
            var url = inputUrl.value.trim();
            if (!url || url.indexOf('http') !== 0) {
                inputUrl.classList.add('is-invalid');
                return;
            }
            if (totalActuales + enCola >= maxExtras) {
                inputUrl.classList.add('is-invalid');
                inputUrl.placeholder = 'Límite de ' + maxExtras + ' imágenes alcanzado';
                return;
            }
            inputUrl.classList.remove('is-invalid');
            var qid = ++cid;

            // Hidden input para el form
            var h = document.createElement('input');
            h.type = 'hidden'; h.name = 'imagenes_urls_nuevas[]'; h.value = url; h.id = 'cola_url_' + qid;
            form.appendChild(h);

            // Tarjeta visual en la cola
            var card = document.createElement('div');
            card.className = 'cola-card';
            var img = document.createElement('img');
            img.src = url;
            img.alt = '';
            img.onerror = function() { this.style.opacity = '.3'; };
            var del = document.createElement('button');
            del.type = 'button'; del.className = 'cola-del'; del.innerHTML = '&times;';
            del.addEventListener('click', (function(q, c) {
                return function() {
                    var hi = document.getElementById('cola_url_' + q);
                    if (hi) hi.remove();
                    c.remove();
                    enCola--;
                    actualizarLimite();
                    if (colaItems.children.length === 0) colaSection.style.display = 'none';
                };
            })(qid, card));
            card.appendChild(img); card.appendChild(del);
            colaItems.appendChild(card);
            colaSection.style.display = 'block';
            enCola++;
            actualizarLimite();

            // Esconder mensaje "sin imágenes" si está
            var msg = document.getElementById('msg-sin-extras');
            if (msg) msg.style.display = 'none';

            // Reset
            inputUrl.value = '';
            prevWrap.style.display = 'none';
            inputUrl.classList.add('border-success');
            setTimeout(function() { inputUrl.classList.remove('border-success'); }, 700);
            inputUrl.focus();
        }

        // Preview de archivos seleccionados
        if (inputFiles && prevArch) {
            inputFiles.addEventListener('change', function() {
                prevArch.innerHTML = '';
                Array.from(this.files).forEach(function(file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'border rounded';
                        img.style.cssText = 'width:80px;height:60px;object-fit:contain;';
                        prevArch.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                });
            });
        }

        // Eliminar imagen existente (toggle)
        document.querySelectorAll('.btn-eliminar-extra').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var card = this.closest('.img-extra-card');
                var hid = 'h_del_' + id;
                var ex = document.getElementById(hid);
                if (ex) {
                    ex.remove();
                    card.style.opacity = '1';
                    card.style.outline = '';
                    this.innerHTML = '<i class="bi bi-trash"></i>';
                    this.title = 'Eliminar';
                    totalActuales++;
                    actualizarLimite();
                } else {
                    var hh = document.createElement('input');
                    hh.type = 'hidden'; hh.name = 'imagenes_eliminar[]'; hh.value = id; hh.id = hid;
                    form.appendChild(hh);
                    card.style.opacity = '.3';
                    card.style.outline = '2px solid #dc3545';
                    this.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i>';
                    this.title = 'Deshacer';
                    totalActuales--;
                    actualizarLimite();
                }
            });
        });

        // Hacer portada
        document.querySelectorAll('.btn-hacer-portada').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('hacer_portada_id').value = this.getAttribute('data-id');
                form.submit();
            });
        });
    })();
    @endif {{-- imagenesAdicionalesActivas --}}

    // Preview en tiempo real del stock resultante en el modal de ajuste
    var stockBase = {{ $producto->stock }};
    var modalVariacion = document.getElementById('modal-variacion');
    var stockResultantePreview = document.getElementById('stock-resultante-preview');

    if (modalVariacion) {
        modalVariacion.addEventListener('input', function() {
            var variacion = parseInt(this.value) || 0;
            var resultante = stockBase + variacion;
            stockResultantePreview.textContent = resultante;
            stockResultantePreview.style.color = resultante < 0 ? '#dc3545' : '';
            stockResultantePreview.style.fontWeight = 'bold';
        });

        // Abrir modal con errores de validación si existen
        @if($errors->has('variacion'))
            var modalAjuste = new bootstrap.Modal(document.getElementById('modal-ajuste-stock'));
            modalAjuste.show();
        @endif
    }

    // Inicializar todos los tooltips de Bootstrap
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        new bootstrap.Tooltip(el, { trigger: 'hover focus' });
    });
</script>
@endpush
@endsection
