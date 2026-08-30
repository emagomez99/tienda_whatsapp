@extends('layouts.admin')

@section('title', 'Nuevo Producto')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-plus-circle"></i> Nuevo Producto</h3>
    <a href="{{ route('admin.productos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form id="form-producto" action="{{ route('admin.productos.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-box-seam"></i> Información del Producto</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Nombre del Producto *</label>
                        <input type="text" class="form-control @error('descripcion') is-invalid @enderror" id="descripcion" name="descripcion" value="{{ old('descripcion') }}" required>
                        @error('descripcion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @include('admin.productos.partials.slug-field')
                    <div class="mb-3">
                        <label class="form-label">Descripción detallada</label>
                        <div id="detalle-editor" class="@error('detalle') is-invalid @enderror"></div>
                        <input type="hidden" name="detalle" id="detalle" value="{{ old('detalle') }}">
                        @error('detalle')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="proveedor_id" class="form-label">Proveedor * @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.proveedor')])</label>
                            <select class="form-select @error('proveedor_id') is-invalid @enderror" id="proveedor_id" name="proveedor_id" required>
                                <option value="">Seleccionar proveedor</option>
                                @php $defaultProveedor = old('proveedor_id', $proveedores->count() === 1 ? $proveedores->first()->id : ''); @endphp
                                @foreach($proveedores as $proveedor)
                                    <option value="{{ $proveedor->id }}" data-prefijo="{{ $proveedor->prefijo }}" {{ $defaultProveedor == $proveedor->id ? 'selected' : '' }}>
                                        {{ $proveedor->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            @error('proveedor_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="id_proveedor" class="form-label">Código Proveedor @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.id_proveedor')])</label>
                            <div class="input-group">
                                <input type="text" class="form-control @error('id_proveedor') is-invalid @enderror" id="id_proveedor" name="id_proveedor" value="{{ old('id_proveedor') }}">
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
                            <label for="moneda_id" class="form-label">Moneda @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.moneda')])</label>
                            <select class="form-select @error('moneda_id') is-invalid @enderror" id="moneda_id" name="moneda_id">
                                <option value="">Sin moneda</option>
                                @foreach($monedas as $moneda)
                                    <option value="{{ $moneda->id }}" {{ old('moneda_id', $monedaDefaultId) == $moneda->id ? 'selected' : '' }}>
                                        {{ $moneda->nombre }} ({{ $moneda->codigo }})
                                    </option>
                                @endforeach
                            </select>
                            @error('moneda_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="precio" class="form-label">Precio * @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.precio')])</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" class="form-control @error('precio') is-invalid @enderror" id="precio" name="precio" value="{{ old('precio', 0) }}" required>
                            </div>
                            @error('precio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="stock" class="form-label">Stock * @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.stock_inicial')])</label>
                            <input type="number" min="0" class="form-control @error('stock') is-invalid @enderror" id="stock" name="stock" value="{{ old('stock', 0) }}" required>
                            @error('stock')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label d-block">Disponible @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.disponible')])</label>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="disponible" name="disponible" value="1" {{ old('disponible', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="disponible">Mostrar en tienda</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label d-block">Por Encargue @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.por_encargue')])</label>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="por_encargue" name="por_encargue" value="1" {{ old('por_encargue') ? 'checked' : '' }}>
                                <label class="form-check-label" for="por_encargue">Disponible sin stock</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Imagen del producto -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-image"></i> Imagen del Producto @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.imagen_principal'), 'grande' => true])</h5>
                </div>
                <div class="card-body">
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

                    <!-- Preview de imagen -->
                    <div id="imagen-preview" class="mt-3 text-center" style="display: none;">
                        <img src="" alt="Preview" class="img-thumbnail" style="max-height: 200px;">
                    </div>
                </div>
            </div>

            @if($imagenesAdicionalesActivas)
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-images"></i> Imágenes Adicionales @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.imagenes_adicionales'), 'grande' => true])</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-abrir-extras"
                            data-bs-toggle="collapse" data-bs-target="#panel-extras">
                        <i class="bi bi-plus"></i> Agregar
                    </button>
                </div>
                <div class="card-body">
                    <div id="msg-limite-extras" class="alert alert-warning py-2 small d-none">
                        <i class="bi bi-exclamation-triangle"></i> Se alcanzó el límite de {{ $maxImagenesAdicionales }} imagen(es) adicional(es).
                    </div>

                    <div id="cola-urls" class="mb-3" style="display:none;">
                        <p class="text-muted small mb-2">Imágenes a agregar al guardar:</p>
                        <div id="cola-urls-items" class="d-flex flex-wrap gap-2"></div>
                    </div>

                    <div class="collapse" id="panel-extras">
                        <div class="border rounded p-3 bg-light">
                            @if($modoImagen !== 'solo_archivo')
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">URL de imagen</label>
                                <div class="input-group">
                                    <input type="url" id="extra-url-input" class="form-control form-control-sm"
                                           placeholder="https://ejemplo.com/imagen.jpg">
                                    <button type="button" class="btn btn-sm btn-primary" id="btn-agregar-extra-url">
                                        <i class="bi bi-plus-lg"></i> Agregar
                                    </button>
                                </div>
                                <div id="extra-url-preview" class="mt-2" style="display:none;">
                                    <img src="" alt="Preview" style="height:70px;object-fit:contain;border-radius:4px;border:1px solid #dee2e6;">
                                </div>
                            </div>
                            @endif
                            @if($modoImagen !== 'solo_url')
                            <div>
                                <label class="form-label small fw-semibold">Subir archivo(s)</label>
                                <input type="file" id="extra-archivo-input" name="imagenes_nuevas[]"
                                       class="form-control form-control-sm" accept="image/*" multiple>
                                <small class="text-muted">Podés seleccionar varios archivos a la vez.</small>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tags"></i> Etiquetas @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.etiquetas'), 'lugar' => 'right', 'grande' => true])</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="agregar-etiqueta">
                        <i class="bi bi-plus"></i> Agregar
                    </button>
                </div>
                <div class="card-body">
                    @include('admin.productos.partials.etiquetas-bloqueo')
                    <div id="etiquetas-container">
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
                                <input type="text" class="form-control etiqueta-valor" name="etiquetas[0][valor]" placeholder="Valor (ej: Filtro, Auto)" data-combo data-combo-fila=".etiqueta-row" data-combo-desde=".etiqueta-select" data-combo-url-con="{{ route('admin.etiquetas.valores', ['etiqueta' => '__ID__']) }}" autocomplete="off">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-danger btn-eliminar-etiqueta">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="d-none" id="etiquetas-hint">
                        <small class="text-muted d-block">Elegí una etiqueta y escribí el valor que le corresponde a este producto.</small>
                        <small class="text-muted d-block mt-1">
                            Las marcadas como <span class="badge bg-danger">Obligatoria</span>
                            las exige el proveedor: sin completarlas no vas a poder guardar.
                        </small>
                    </div>
                    @error('etiquetas')
                        <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Especificaciones @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.especificaciones'), 'lugar' => 'right', 'grande' => true])</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="agregar-especificacion">
                        <i class="bi bi-plus"></i> Agregar
                    </button>
                </div>
                <div class="card-body">
                    <div id="especificaciones-container">
                        <div class="row mb-2 especificacion-row">
                            <div class="col-md-5 position-relative">
                                <input type="text" class="form-control especificacion-clave" name="especificaciones[0][clave]" placeholder="Clave (ej: Peso)" data-combo="{{ route('admin.especificaciones.claves') }}" autocomplete="off">
                            </div>
                            <div class="col-md-5 position-relative">
                                <input type="text" class="form-control especificacion-valor" name="especificaciones[0][valor]" placeholder="Valor (ej: 1.75)" data-combo="{{ route('admin.especificaciones.valores') }}" data-combo-fila=".especificacion-row" data-combo-param="clave" data-combo-desde=".especificacion-clave" autocomplete="off">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-danger btn-eliminar-especificacion">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @include('admin.productos.partials.card-seo')
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-circle"></i> Guardar Producto
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
<link rel="stylesheet" href="{{ asset('vendor/quill/quill.snow.css') }}">
<style>
    #detalle-editor { min-height: 120px; background: #fff; }
    .ql-toolbar { border-radius: 6px 6px 0 0; }
    .ql-container { border-radius: 0 0 6px 6px; font-size: 1rem; }
    .etiqueta-bloqueada {
        pointer-events: none;
        background-color: #fff5f5;
        border-color: #dc3545;
        color: #495057;
    }
    .cola-card { position:relative; width:90px; height:70px; border-radius:6px; overflow:hidden; border:1px solid #dee2e6; background:#f8f9fa; flex-shrink:0; }
    .cola-card img { width:100%;height:100%;object-fit:contain; }
    .cola-del { position:absolute;top:2px;right:2px;width:20px;height:20px;border-radius:50%;background:rgba(220,53,69,.85);color:#fff;border:none;display:flex;align-items:center;justify-content:center;font-size:.65rem;cursor:pointer;line-height:1;padding:0; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/quill/quill.min.js') }}"></script>
@include('admin.productos.partials.slug-script')
@include('admin.productos.partials.combo-sugerencias')
<script>
    {{-- URLs para las plantillas de fila que se arman dentro de JS --}}
    const COMBO_URL_ETIQUETA      = @json(route('admin.etiquetas.valores', ['etiqueta' => '__ID__']));
    const COMBO_URL_ESPEC_CLAVES  = @json(route('admin.especificaciones.claves'));
    const COMBO_URL_ESPEC_VALORES = @json(route('admin.especificaciones.valores'));
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

    let especificacionIndex = 1;
    let etiquetaIndex = 1;

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
                <input type="text" class="form-control etiqueta-valor" name="etiquetas[${etiquetaIndex}][valor]" placeholder="Valor (ej: Filtro, Auto)" data-combo data-combo-fila=".etiqueta-row" data-combo-desde=".etiqueta-select" data-combo-url-con="${COMBO_URL_ETIQUETA}" autocomplete="off">
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

    // Si hay proveedor preseleccionado (old() tras error de validación)
    (function() {
        var sel = document.getElementById('proveedor_id');
        if (sel.value) {
            limpiarTodasEtiquetas(sel.value);
            actualizarObligatorias(sel.value);
        }
    })();

    // Autocompletado para valores de etiquetas
    let debounceTimer;

    // Inicializar autocompletado en campos existentes

    // Autocompletado para especificaciones (claves)
    let debounceTimerEspecClave;

    // Autocompletado para especificaciones (valores)
    let debounceTimerEspecValor;

    // Inicializar autocompletado en campos de especificaciones existentes

    document.getElementById('agregar-especificacion').addEventListener('click', function() {
        const container = document.getElementById('especificaciones-container');
        const newRow = document.createElement('div');
        newRow.className = 'row mb-2 especificacion-row';
        newRow.innerHTML = `
            <div class="col-md-5 position-relative">
                <input type="text" class="form-control especificacion-clave" name="especificaciones[${especificacionIndex}][clave]" placeholder="Clave (ej: Peso)" data-combo="${COMBO_URL_ESPEC_CLAVES}" autocomplete="off">
            </div>
            <div class="col-md-5 position-relative">
                <input type="text" class="form-control especificacion-valor" name="especificaciones[${especificacionIndex}][valor]" placeholder="Valor (ej: 1.75)" data-combo="${COMBO_URL_ESPEC_VALORES}" data-combo-fila=".especificacion-row" data-combo-param="clave" data-combo-desde=".especificacion-clave" autocomplete="off">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger btn-eliminar-especificacion">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        container.appendChild(newRow);
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
            }
        }
        if (e.target.closest('.btn-eliminar-etiqueta')) {
            const btn = e.target.closest('.btn-eliminar-etiqueta');
            if (btn.classList.contains('disabled')) return;
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

    function mostrarPreview(src) {
        if (src) {
            imagenPreviewImg.src = src;
            imagenPreview.style.display = 'block';
        } else {
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
            imagenPreview.style.display = 'none';
        });
    }

    @if($imagenesAdicionalesActivas)
    (function() {
        var maxExtras = {{ $maxImagenesAdicionales }};
        var enCola = 0;

        function actualizarLimite() {
            var lleno = enCola >= maxExtras;
            var msgLimite = document.getElementById('msg-limite-extras');
            var btnAbrir = document.getElementById('btn-abrir-extras');
            var btnAgregarUrl = document.getElementById('btn-agregar-extra-url');
            var inputArchivo = document.getElementById('extra-archivo-input');
            if (msgLimite) msgLimite.classList.toggle('d-none', !lleno);
            if (btnAbrir) btnAbrir.disabled = lleno;
            if (btnAgregarUrl) btnAgregarUrl.disabled = lleno;
            if (inputArchivo) inputArchivo.disabled = lleno;
            if (lleno) {
                var panel = document.getElementById('panel-extras');
                if (panel && panel.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(panel).hide();
                }
            }
        }

        // Preview URL en tiempo real
        var inputUrl = document.getElementById('extra-url-input');
        var previewDiv = document.getElementById('extra-url-preview');
        var debounceExtra;
        if (inputUrl) {
            inputUrl.addEventListener('input', function() {
                clearTimeout(debounceExtra);
                var url = this.value.trim();
                debounceExtra = setTimeout(function() {
                    if (url.startsWith('http') && previewDiv) {
                        previewDiv.querySelector('img').src = url;
                        previewDiv.style.display = 'block';
                    } else if (previewDiv) {
                        previewDiv.style.display = 'none';
                    }
                }, 400);
            });
        }

        function crearTarjeta(src, hidden) {
            var card = document.createElement('div');
            card.className = 'cola-card';
            var img = document.createElement('img');
            img.src = src;
            img.onerror = function() { this.src = '/img/no-image.svg'; };
            var delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'cola-del';
            delBtn.innerHTML = '<i class="bi bi-x"></i>';
            delBtn.addEventListener('click', function() {
                card.remove();
                if (hidden && hidden.parentNode) hidden.remove();
                enCola--;
                actualizarLimite();
                if (document.getElementById('cola-urls-items').children.length === 0) {
                    document.getElementById('cola-urls').style.display = 'none';
                }
            });
            card.appendChild(img);
            card.appendChild(delBtn);
            return card;
        }

        // Agregar URL a la cola
        var btnAgregarUrl = document.getElementById('btn-agregar-extra-url');
        if (btnAgregarUrl) {
            btnAgregarUrl.addEventListener('click', function() {
                var url = inputUrl ? inputUrl.value.trim() : '';
                if (!url || !url.startsWith('http') || enCola >= maxExtras) return;
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'imagenes_urls_nuevas[]';
                hidden.value = url;
                document.getElementById('form-producto').appendChild(hidden);
                var items = document.getElementById('cola-urls-items');
                items.appendChild(crearTarjeta(url, hidden));
                document.getElementById('cola-urls').style.display = 'block';
                inputUrl.value = '';
                if (previewDiv) previewDiv.style.display = 'none';
                enCola++;
                actualizarLimite();
            });
        }

        // Preview de archivos seleccionados
        var inputArchivo = document.getElementById('extra-archivo-input');
        if (inputArchivo) {
            inputArchivo.addEventListener('change', function() {
                var files = this.files;
                if (!files || files.length === 0) return;
                var disponible = maxExtras - enCola;
                var items = document.getElementById('cola-urls-items');
                Array.prototype.slice.call(files, 0, disponible).forEach(function(file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var card = document.createElement('div');
                        card.className = 'cola-card';
                        var img = document.createElement('img');
                        img.src = e.target.result;
                        card.appendChild(img);
                        items.appendChild(card);
                        enCola++;
                        actualizarLimite();
                        document.getElementById('cola-urls').style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                });
            });
        }

        actualizarLimite();
    })();
    @endif

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        new bootstrap.Tooltip(el, { trigger: 'hover focus' });
    });

    // Contador de caracteres para los campos de SEO
    document.querySelectorAll('.contador-caracteres').forEach(function (contador) {
        var campo = contador.parentElement.querySelector('input, textarea');
        if (!campo) return;
        var max = contador.dataset.max;
        function actualizarContador() {
            contador.textContent = campo.value.length + '/' + max;
            contador.classList.toggle('text-danger', campo.value.length > max);
        }
        campo.addEventListener('input', actualizarContador);
        actualizarContador();
    });
</script>
@endpush
@endsection
