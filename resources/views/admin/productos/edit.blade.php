@extends('layouts.admin')

@section('title', 'Editar Producto')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-pencil"></i> Editar Producto</h2>
    <a href="{{ route('admin.productos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form action="{{ route('admin.productos.update', $producto) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Información del Producto</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="descripcion" class="form-label">Descripción *</label>
                            <input type="text" class="form-control @error('descripcion') is-invalid @enderror" id="descripcion" name="descripcion" value="{{ old('descripcion', $producto->descripcion) }}" required>
                            @error('descripcion')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="id_proveedor" class="form-label">Código Proveedor</label>
                            <input type="text" class="form-control @error('id_proveedor') is-invalid @enderror" id="id_proveedor" name="id_proveedor" value="{{ old('id_proveedor', $producto->id_proveedor) }}">
                            @error('id_proveedor')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="proveedor_id" class="form-label">Proveedor *</label>
                            <select class="form-select @error('proveedor_id') is-invalid @enderror" id="proveedor_id" name="proveedor_id" required>
                                <option value="">Seleccionar proveedor</option>
                                @foreach($proveedores as $proveedor)
                                    <option value="{{ $proveedor->id }}" {{ old('proveedor_id', $producto->proveedor_id) == $proveedor->id ? 'selected' : '' }}>
                                        {{ $proveedor->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            @error('proveedor_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="moneda_id" class="form-label">Moneda</label>
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
                            <label for="precio" class="form-label">Precio *</label>
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
                            <label for="stock" class="form-label">Stock *</label>
                            <input type="number" min="0" class="form-control @error('stock') is-invalid @enderror" id="stock" name="stock" value="{{ old('stock', $producto->stock) }}" required>
                            @error('stock')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label d-block">Disponible</label>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="disponible" name="disponible" value="1" {{ old('disponible', $producto->disponible) ? 'checked' : '' }}>
                                <label class="form-check-label" for="disponible">Mostrar en tienda</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label d-block">Por Encargue</label>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="por_encargue" name="por_encargue" value="1" {{ old('por_encargue', $producto->por_encargue) ? 'checked' : '' }}>
                                <label class="form-check-label" for="por_encargue">Disponible sin stock</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card de Imagen -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-image"></i> Imagen del Producto</h5>
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
                        <p class="text-muted mb-2"><small>Cambiar imagen:</small></p>
                    @endif

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
                    <!-- Preview de nueva imagen -->
                    <div id="imagen-preview" class="mt-3 text-center" style="display: none;">
                        <p class="text-muted mb-1"><small>Nueva imagen:</small></p>
                        <img src="" alt="Preview" class="img-thumbnail" style="max-height: 200px;">
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Etiquetas</h5>
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
                    <small class="text-muted">Selecciona una etiqueta y asigna un valor especifico para este producto.</small>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Especificaciones</h5>
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
                    <a href="{{ route('admin.productos.index') }}" class="btn btn-outline-secondary w-100">
                        Cancelar
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

@push('styles')
<style>
    .autocomplete-suggestions .list-group-item {
        cursor: pointer;
        padding: 0.5rem 0.75rem;
    }
    .autocomplete-suggestions .list-group-item:hover {
        background-color: #e9ecef;
    }
</style>
@endpush

@push('scripts')
<script>
    let especificacionIndex = {{ $producto->especificaciones->count() ?: 1 }};
    let etiquetaIndex = {{ $producto->etiquetas->count() ?: 1 }};

    const etiquetasOptions = `
        <option value="">Seleccionar etiqueta</option>
        @foreach($etiquetas as $etiqueta)
            <option value="{{ $etiqueta->id }}">{{ $etiqueta->nombre }}</option>
        @endforeach
    `;

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
        const container = document.getElementById('etiquetas-container');
        const newRow = document.createElement('div');
        newRow.className = 'row mb-2 etiqueta-row';
        newRow.innerHTML = `
            <div class="col-md-5">
                <select class="form-select etiqueta-select" name="etiquetas[${etiquetaIndex}][etiqueta_id]" data-index="${etiquetaIndex}">
                    ${etiquetasOptions}
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
        container.appendChild(newRow);
        setupAutocomplete(newRow.querySelector('.etiqueta-valor'));
        etiquetaIndex++;
    });

    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-eliminar-especificacion')) {
            const rows = document.querySelectorAll('.especificacion-row');
            if (rows.length > 1) {
                e.target.closest('.especificacion-row').remove();
            }
        }
        if (e.target.closest('.btn-eliminar-etiqueta')) {
            const rows = document.querySelectorAll('.etiqueta-row');
            if (rows.length > 1) {
                e.target.closest('.etiqueta-row').remove();
            }
        }
    });

    // Preview de imagen
    const imagenArchivo = document.getElementById('imagen_archivo');
    const imagenUrl = document.getElementById('imagen_url');
    const imagenPreview = document.getElementById('imagen-preview');
    const imagenPreviewImg = imagenPreview.querySelector('img');
    const eliminarImagen = document.getElementById('eliminar_imagen');

    function mostrarPreview(src) {
        if (src) {
            imagenPreviewImg.src = src;
            imagenPreview.style.display = 'block';
        } else {
            imagenPreview.style.display = 'none';
        }
    }

    imagenArchivo.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                mostrarPreview(e.target.result);
            };
            reader.readAsDataURL(this.files[0]);
            // Limpiar URL y desmarcar eliminar
            imagenUrl.value = '';
            if (eliminarImagen) eliminarImagen.checked = false;
        } else {
            mostrarPreview(null);
        }
    });

    let debounceUrl;
    imagenUrl.addEventListener('input', function() {
        clearTimeout(debounceUrl);
        const url = this.value.trim();
        debounceUrl = setTimeout(() => {
            if (url && url.startsWith('http')) {
                mostrarPreview(url);
                // Limpiar archivo y desmarcar eliminar
                imagenArchivo.value = '';
                if (eliminarImagen) eliminarImagen.checked = false;
            } else {
                mostrarPreview(null);
            }
        }, 500);
    });

    // Limpiar el otro campo al cambiar de tab
    document.getElementById('archivo-tab').addEventListener('shown.bs.tab', function() {
        imagenUrl.value = '';
    });
    document.getElementById('url-tab').addEventListener('shown.bs.tab', function() {
        imagenArchivo.value = '';
        imagenPreview.style.display = 'none';
    });

    // Ocultar preview y limpiar campos si se marca eliminar
    if (eliminarImagen) {
        eliminarImagen.addEventListener('change', function() {
            if (this.checked) {
                imagenArchivo.value = '';
                imagenUrl.value = '';
                imagenPreview.style.display = 'none';
            }
        });
    }
</script>
@endpush
@endsection
