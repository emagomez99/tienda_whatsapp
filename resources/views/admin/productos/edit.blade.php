@extends('layouts.admin')

@section('title', 'Editar Producto')

@section('content')
@php
    $backUrl = request('_back_url')
        ? request('_back_url')
        : route('admin.productos.index') . (request('_back') ? '?' . request('_back') : '');
@endphp
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0"><i class="bi bi-pencil"></i> Editar Producto <span class="text-muted fw-normal">#{{ $producto->id }}</span></h3>
    <div class="d-flex gap-2">
        {{-- Pestaña nueva: así no se pierde lo que se esté editando acá. --}}
        <a href="{{ $producto->url() }}" target="_blank" rel="noopener" class="btn btn-outline-primary"
           title="{{ $producto->disponible ? 'Abrir la página del producto en la tienda' : 'No está disponible: los clientes no lo ven en los listados' }}">
            <i class="bi bi-box-arrow-up-right"></i> Ver en la tienda
            @unless($producto->disponible)
                <span class="badge bg-secondary ms-1">oculto</span>
            @endunless
        </a>
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
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
        <div class="col-lg-8 col-xl-9">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-box-seam"></i> Información del Producto</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Nombre del Producto *</label>
                        <input type="text" class="form-control @error('descripcion') is-invalid @enderror" id="descripcion" name="descripcion" value="{{ old('descripcion', $producto->descripcion) }}" required>
                        @error('descripcion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @include('admin.productos.partials.slug-field')
                    <div class="mb-3">
                        <label class="form-label">Descripción detallada</label>
                        <div id="detalle-editor" class="@error('detalle') is-invalid @enderror"></div>
                        <input type="hidden" name="detalle" id="detalle" value="{{ old('detalle', $producto->detalle) }}">
                        @error('detalle')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="proveedor_id" class="form-label">Proveedor * @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.proveedor')])</label>
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
                                @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.id_proveedor')])
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
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock actual
                                @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.stock_edicion')])
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
                                @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.disponible')])
                            </label>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="disponible" name="disponible" value="1" {{ old('disponible', $producto->disponible) ? 'checked' : '' }}>
                                <label class="form-check-label" for="disponible">Mostrar en tienda</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label d-block">Por Encargue
                                @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.por_encargue')])
                            </label>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="por_encargue" name="por_encargue" value="1" {{ old('por_encargue', $producto->por_encargue) ? 'checked' : '' }}>
                                <label class="form-check-label" for="por_encargue">Disponible sin stock</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @include('admin.productos.partials.card-precio')

            <!-- Card: Imagen principal -->
            @php $modoImagen = App\Models\Configuracion::modoImagenProducto(); @endphp
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-image"></i> Imagen Principal
                        @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.imagen_principal'), 'grande' => true])
                    </h5>
                    @if($producto->url_imagen)
                        <button type="button" id="btn-cambiar-principal" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i> Cambiar
                        </button>
                    @else
                        <button type="button" id="btn-cambiar-principal" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus-lg"></i> Agregar imagen
                        </button>
                    @endif
                </div>
                <div class="card-body">

                    {{-- Tarjeta de imagen actual --}}
                    <div class="mb-3">
                        <div id="card-img-principal" class="img-extra-card position-relative d-inline-block" style="width:150px;">
                            <div class="rounded border overflow-hidden bg-light" style="height:115px;">
                                <img id="img-principal-thumb"
                                     src="{{ $producto->url_imagen ? $producto->imagen_url : '' }}"
                                     style="width:100%;height:100%;object-fit:contain;{{ $producto->url_imagen ? '' : 'display:none;' }}"
                                     onerror="this.src='/img/no-image.svg';">
                                <div id="img-principal-placeholder"
                                     class="d-flex flex-column align-items-center justify-content-center h-100 text-muted"
                                     style="{{ $producto->url_imagen ? 'display:none;' : '' }}">
                                    <i class="bi bi-image fs-2"></i>
                                    <span style="font-size:.75rem;">Sin imagen</span>
                                </div>
                                @if($producto->url_imagen)
                                <div class="img-overlay">
                                    <button type="button" id="btn-cambiar-principal"
                                            class="btn btn-light btn-sm px-2 py-1" title="Cambiar imagen">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" id="btn-del-principal"
                                            class="btn btn-danger btn-sm px-2 py-1" title="Eliminar imagen">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                @endif
                            </div>
                            <div class="text-center mt-1" style="font-size:.65rem;color:#888;">
                                @if($producto->url_imagen)
                                    <span class="badge bg-warning text-dark" style="font-size:.6rem;">
                                        <i class="bi bi-star-fill"></i> Principal
                                    </span>
                                    <span class="text-muted ms-1">
                                        {{ $producto->esImagenExterna() ? '· URL' : '· Local' }}
                                    </span>
                                @endif
                            </div>
                        </div>

                    </div>

                    {{-- Hidden inputs para el form --}}
                    <input type="hidden" name="eliminar_imagen" id="eliminar_imagen" value="">

                    {{-- Panel cambiar/agregar --}}
                    <div id="panel-imagen-principal" class="border rounded p-3" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-semibold small">
                                {{ $producto->url_imagen ? 'Cambiar imagen principal' : 'Agregar imagen principal' }}
                            </span>
                            <button type="button" id="btn-cerrar-principal" class="btn-close" aria-label="Cerrar"></button>
                        </div>

                        @if($modoImagen !== 'solo_archivo')
                            <div class="mb-3">
                                <label class="form-label small fw-semibold mb-1">
                                    <i class="bi bi-link-45deg"></i> Pegar URL
                                </label>
                                <input type="url" id="imagen_url" name="imagen_url"
                                       class="form-control @error('imagen_url') is-invalid @enderror"
                                       placeholder="https://ejemplo.com/imagen.jpg"
                                       value="{{ old('imagen_url') }}">
                                @error('imagen_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div id="preview-url-p" class="mt-2" style="display:none;">
                                    <img id="preview-url-p-img" src=""
                                         style="height:64px;object-fit:contain;border-radius:4px;border:1px solid #dee2e6;">
                                </div>
                            </div>
                        @endif

                        @if($modoImagen !== 'solo_url')
                            <div class="mb-0">
                                <label class="form-label small fw-semibold mb-1">
                                    <i class="bi bi-upload"></i> Subir archivo
                                </label>
                                <input type="file" id="imagen_archivo" name="imagen_archivo"
                                       class="form-control @error('imagen_archivo') is-invalid @enderror"
                                       accept="image/*">
                                @error('imagen_archivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div id="preview-arch-p" class="mt-2" style="display:none;">
                                    <img id="preview-arch-p-img" src=""
                                         style="height:64px;object-fit:contain;border-radius:4px;border:1px solid #dee2e6;">
                                </div>
                            </div>
                        @endif
                    </div>

                </div>
            </div>

            <!-- Card: Imágenes adicionales -->
            @if($imagenesAdicionalesActivas)
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-images"></i> Imágenes Adicionales
                        @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.imagenes_adicionales'), 'grande' => true])
                    </h5>
                    <button type="button" id="btn-abrir-agregar" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus"></i> Agregar
                    </button>
                </div>
                <div class="card-body">

                    {{-- Grid unificado: existentes + pendientes en la misma fila --}}
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
                        {{-- Las tarjetas de cola se insertan aquí via JS --}}
                    </div>

                    {{-- Alerta de límite --}}
                    <div id="msg-limite-extras" class="alert alert-warning align-items-center gap-2 mt-3 mb-0 py-2" style="display:none;">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                        <span>Límite de <strong>{{ $maxImagenesAdicionales }}</strong> imágenes adicionales alcanzado. Eliminá una para poder agregar otra.</span>
                    </div>

                    {{-- Panel agregar (colapsable) --}}
                    <div id="panel-agregar-extras" class="mt-3 border rounded p-3" style="display:none;">
                        @if($modoImagen !== 'solo_archivo')
                            <div class="mb-3">
                                <label class="form-label small fw-semibold mb-1">
                                    <i class="bi bi-link-45deg"></i> URL de imagen
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
                            <div>
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
                    </div>

                    <input type="hidden" name="hacer_portada_id" id="hacer_portada_id" value="">
                </div>
            </div>
            @endif {{-- imagenesAdicionalesActivas --}}

            @include('admin.productos.partials.etiquetas', ['valoresIniciales' => session()->hasOldInput() ? old('etiquetas', []) : $producto->etiquetas->map(function ($e) { return ['etiqueta_id' => $e->id, 'valor' => $e->pivot->valor]; })->all()])

            @include('admin.productos.partials.especificaciones', ['especificacionesIniciales' => session()->hasOldInput() ? old('especificaciones', []) : $producto->especificaciones->map(function ($e) { return ['clave' => $e->clave, 'valor' => $e->valor]; })->all()])
            @include('admin.productos.partials.card-seo')
        </div>

        <div class="col-lg-4 col-xl-3">
            @include('admin.productos.partials.vista-previa')
        </div>
    </div>

    @if(request('_back_url'))
        <input type="hidden" name="_back_url" value="{{ request('_back_url') }}">
    @else
        <input type="hidden" name="_back" value="{{ request('_back', '') }}">
    @endif
    @include('admin.productos.partials.barra-guardar', ['textoGuardar' => 'Guardar cambios', 'cancelarUrl' => $backUrl])
</form>

<!-- Modal: ajuste de stock -->
@include('admin.productos.partials.modal-ajuste-stock')

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/quill/quill.snow.css') }}">
<style>
    #detalle-editor { min-height: 120px; background: #fff; }
    .ql-toolbar { border-radius: 6px 6px 0 0; }
    .ql-container { border-radius: 0 0 6px 6px; font-size: 1rem; }
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
@include('admin.productos.partials.slug-script')
@include('admin.productos.partials.combo-sugerencias')
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

    document.getElementById('btn-generar-codigo').addEventListener('click', function() {
        var sel = document.getElementById('proveedor_id');
        var option = sel.options[sel.selectedIndex];
        var prefijo = option ? (option.dataset.prefijo || '').trim().toUpperCase() : '';
        var numero = Math.floor(100000 + Math.random() * 900000);
        document.getElementById('id_proveedor').value = prefijo ? prefijo + '-' + numero : String(numero);
    });


    // === Gestor de Imagen Principal ===
    (function() {
        var thumb       = document.getElementById('img-principal-thumb');
        var placeholder = document.getElementById('img-principal-placeholder');
        var card        = document.getElementById('card-img-principal');
        var panel       = document.getElementById('panel-imagen-principal');
        var inputElim   = document.getElementById('eliminar_imagen');
        var inputUrl    = document.getElementById('imagen_url');
        var inputArch   = document.getElementById('imagen_archivo');
        var prevUrlWrap = document.getElementById('preview-url-p');
        var prevUrlImg  = document.getElementById('preview-url-p-img');
        var prevArchWrap= document.getElementById('preview-arch-p');
        var prevArchImg = document.getElementById('preview-arch-p-img');
        var marcadoElim = false;
        var debUrl;

        // Todos los botones con id="btn-cambiar-principal" (hay dos: overlay + abajo)
        document.querySelectorAll('#btn-cambiar-principal').forEach(function(btn) {
            btn.addEventListener('click', function() {
                cancelarEliminacion();
                panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
                if (panel.style.display === 'block' && inputUrl) inputUrl.focus();
            });
        });

        var btnCerrar = document.getElementById('btn-cerrar-principal');
        if (btnCerrar) {
            btnCerrar.addEventListener('click', function() { panel.style.display = 'none'; });
        }

        var btnDel = document.getElementById('btn-del-principal');
        if (btnDel) {
            btnDel.addEventListener('click', function() {
                if (marcadoElim) {
                    cancelarEliminacion();
                } else {
                    marcarEliminacion();
                }
            });
        }

        function marcarEliminacion() {
            marcadoElim = true;
            inputElim.value = '1';
            panel.style.display = 'none';
            if (inputUrl) inputUrl.value = '';
            if (inputArch) inputArch.value = '';
            if (prevUrlWrap) prevUrlWrap.style.display = 'none';
            if (prevArchWrap) prevArchWrap.style.display = 'none';
            if (card) { card.style.opacity = '.3'; card.style.outline = '2px solid #dc3545'; }
            if (btnDel) { btnDel.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i>'; btnDel.title = 'Deshacer'; }
        }

        function cancelarEliminacion() {
            marcadoElim = false;
            inputElim.value = '';
            if (card) { card.style.opacity = ''; card.style.outline = ''; }
            if (btnDel) { btnDel.innerHTML = '<i class="bi bi-trash"></i>'; btnDel.title = 'Eliminar imagen'; }
        }

        // Preview URL en vivo
        if (inputUrl) {
            inputUrl.addEventListener('input', function() {
                clearTimeout(debUrl);
                var url = this.value.trim();
                cancelarEliminacion();
                debUrl = setTimeout(function() {
                    if (url && url.indexOf('http') === 0) {
                        prevUrlImg.src = url;
                        prevUrlWrap.style.display = 'block';
                        // Actualizar thumb en tiempo real
                        thumb.src = url;
                        thumb.style.display = '';
                        if (placeholder) placeholder.style.display = 'none';
                        if (inputArch) inputArch.value = '';
                        if (prevArchWrap) prevArchWrap.style.display = 'none';
                    } else {
                        if (prevUrlWrap) prevUrlWrap.style.display = 'none';
                    }
                }, 400);
            });
        }

        // Preview archivo en vivo
        if (inputArch) {
            inputArch.addEventListener('change', function() {
                cancelarEliminacion();
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        prevArchImg.src = e.target.result;
                        prevArchWrap.style.display = 'block';
                        // Actualizar thumb en tiempo real
                        thumb.src = e.target.result;
                        thumb.style.display = '';
                        if (placeholder) placeholder.style.display = 'none';
                        if (inputUrl) inputUrl.value = '';
                        if (prevUrlWrap) prevUrlWrap.style.display = 'none';
                    };
                    reader.readAsDataURL(this.files[0]);
                } else {
                    if (prevArchWrap) prevArchWrap.style.display = 'none';
                }
            });
        }
    })();

    // === Gestor de Imágenes Adicionales ===
    @if($imagenesAdicionalesActivas)
    (function() {
        var maxExtras     = {{ $maxImagenesAdicionales }};
        var totalActuales = {{ $producto->imagenes->count() }};
        var enCola        = 0;
        var cid           = 0;
        var debUrl;

        var btnAbrir    = document.getElementById('btn-abrir-agregar');
        var panel       = document.getElementById('panel-agregar-extras');
        var inputUrl    = document.getElementById('input-url-nueva');
        var btnAgrUrl   = document.getElementById('btn-agregar-url');
        var prevWrap    = document.getElementById('preview-url-nueva');
        var prevImg     = document.getElementById('preview-url-img');
        var gridExtras  = document.getElementById('grid-extras');
        var inputFiles  = document.getElementById('imagenes_nuevas');
        var prevArch    = document.getElementById('preview-archivos');
        var msgLimite   = document.getElementById('msg-limite-extras');
        var form        = document.getElementById('form-producto');

        // ── Estado del límite ────────────────────────────────────────────────
        function actualizarLimite() {
            var usadas    = totalActuales + enCola;
            var lleno     = usadas >= maxExtras;
            var restantes = maxExtras - usadas;

            if (btnAbrir) {
                btnAbrir.disabled = lleno;
                btnAbrir.title    = lleno
                    ? 'Límite de ' + maxExtras + ' imágenes alcanzado'
                    : 'Podés agregar ' + (restantes === 1 ? '1 imagen más' : 'hasta ' + restantes + ' imágenes más');
            }
            if (btnAgrUrl) btnAgrUrl.disabled = lleno;
            if (inputUrl)  inputUrl.disabled  = lleno;
            if (msgLimite) msgLimite.style.display = lleno ? 'flex' : 'none';
        }

        // ── Abrir / cerrar panel ─────────────────────────────────────────────
        if (btnAbrir) {
            btnAbrir.addEventListener('click', function() {
                var abierto = panel.style.display !== 'none';
                panel.style.display = abierto ? 'none' : 'block';
                if (!abierto && inputUrl) inputUrl.focus();
            });
        }
        // ── Preview de URL en vivo ───────────────────────────────────────────
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

        // ── Agregar URL a la cola ────────────────────────────────────────────
        function agregarUrl() {
            var url = inputUrl ? inputUrl.value.trim() : '';
            if (!url || url.indexOf('http') !== 0) {
                if (inputUrl) inputUrl.classList.add('is-invalid');
                return;
            }
            if (totalActuales + enCola >= maxExtras) return;

            inputUrl.classList.remove('is-invalid');
            var qid = ++cid;

            // Hidden input que viaja con el form
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'imagenes_urls_nuevas[]';
            hidden.value = url;
            hidden.id = 'cola_url_' + qid;
            form.appendChild(hidden);

            // Tarjeta visual — mismo formato que las existentes
            var card = document.createElement('div');
            card.className = 'img-extra-card position-relative';
            card.style.width = '120px';

            var inner = document.createElement('div');
            inner.className = 'rounded overflow-hidden';
            inner.style.cssText = 'height:90px;border:2px dashed #6c757d;';

            var img = document.createElement('img');
            img.src = url;
            img.style.cssText = 'width:100%;height:100%;object-fit:contain;';
            img.onerror = function() { this.src = '/img/no-image.svg'; };

            var overlay = document.createElement('div');
            overlay.className = 'img-overlay';

            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'btn btn-danger btn-sm px-2 py-1';
            del.title = 'Quitar de la cola';
            del.innerHTML = '<i class="bi bi-trash"></i>';
            del.addEventListener('click', function() {
                var hi = document.getElementById('cola_url_' + qid);
                if (hi) hi.remove();
                card.remove();
                enCola--;
                actualizarLimite();
            });

            overlay.appendChild(del);
            inner.appendChild(img);
            inner.appendChild(overlay);
            card.appendChild(inner);

            var label = document.createElement('div');
            label.className = 'text-center mt-1';
            label.style.cssText = 'font-size:.65rem;color:#888;';
            label.innerHTML = '<i class="bi bi-clock-history"></i> Pendiente';
            card.appendChild(label);

            gridExtras.appendChild(card);

            var msgSin = document.getElementById('msg-sin-extras');
            if (msgSin) msgSin.style.display = 'none';

            enCola++;
            actualizarLimite();

            // Feedback y reset
            inputUrl.value = '';
            prevWrap.style.display = 'none';
            inputUrl.classList.add('border-success');
            setTimeout(function() { inputUrl.classList.remove('border-success'); }, 600);
            inputUrl.focus();
        }

        // ── Preview de archivos seleccionados ────────────────────────────────
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

        // ── Eliminar imagen existente (toggle) ───────────────────────────────
        document.querySelectorAll('.btn-eliminar-extra').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id   = this.getAttribute('data-id');
                var card = this.closest('.img-extra-card');
                var hid  = 'h_del_' + id;
                var ex   = document.getElementById(hid);
                if (ex) {
                    ex.remove();
                    card.style.opacity = '';
                    card.style.outline = '';
                    this.innerHTML = '<i class="bi bi-trash"></i>';
                    this.title = 'Eliminar';
                    totalActuales++;
                } else {
                    var hh = document.createElement('input');
                    hh.type = 'hidden';
                    hh.name = 'imagenes_eliminar[]';
                    hh.value = id;
                    hh.id = hid;
                    form.appendChild(hh);
                    card.style.opacity = '.35';
                    card.style.outline = '2px solid #dc3545';
                    this.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i>';
                    this.title = 'Deshacer';
                    totalActuales--;
                }
                actualizarLimite();
            });
        });

        // ── Hacer portada ────────────────────────────────────────────────────
        document.querySelectorAll('.btn-hacer-portada').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('hacer_portada_id').value = this.getAttribute('data-id');
                form.submit();
            });
        });

        actualizarLimite();
    })();
    @endif {{-- imagenesAdicionalesActivas --}}

    // Inicializar todos los tooltips de Bootstrap
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
