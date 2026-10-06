{{--
    Formulario de producto, compartido por alta y edición. $producto existe sólo en
    edición (los parciales lo detectan con isset, igual que antes).

    Orden de las secciones: primero lo que describe al producto y después lo
    comercial.
      Producto         nombre, dirección web, descripción
      Inventario       proveedor, código, stock, disponible, por encargue
      Etiquetas        dependen del proveedor: van justo debajo de él
      Especificaciones
      Precio
      Imágenes
      SEO
--}}
@php
    $editando = isset($producto);

    $etiquetasIniciales = (session()->hasOldInput() || !$editando)
        ? old('etiquetas', [])
        : $producto->etiquetas->map(function ($e) { return ['etiqueta_id' => $e->id, 'valor' => $e->pivot->valor]; })->all();

    $especificacionesIniciales = (session()->hasOldInput() || !$editando)
        ? old('especificaciones', [])
        : $producto->especificaciones->map(function ($e) { return ['clave' => $e->clave, 'valor' => $e->valor]; })->all();

    // En el alta, si hay un solo proveedor ya viene elegido.
    $proveedorElegido = old('proveedor_id', $editando
        ? $producto->proveedor_id
        : ($proveedores->count() === 1 ? $proveedores->first()->id : ''));
@endphp

@if($errors->any())
    <div class="alert alert-danger mb-3">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row">
    <div class="col-lg-8 col-xl-9">

        {{-- Producto --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-box-seam"></i> Producto</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="descripcion" class="form-label">Nombre *</label>
                    <input type="text" class="form-control @error('descripcion') is-invalid @enderror" id="descripcion" name="descripcion"
                           value="{{ old('descripcion', $editando ? $producto->descripcion : '') }}" required>
                    @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @include('admin.productos.partials.slug-field')
                <div class="mb-0">
                    <label class="form-label">Descripción detallada</label>
                    <div id="detalle-editor" class="@error('detalle') is-invalid @enderror"></div>
                    <input type="hidden" name="detalle" id="detalle" value="{{ old('detalle', $editando ? $producto->detalle : '') }}">
                    @error('detalle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Inventario --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-boxes"></i> Inventario</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="proveedor_id" class="form-label">Proveedor * @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.proveedor')])</label>
                        <select class="form-select @error('proveedor_id') is-invalid @enderror" id="proveedor_id" name="proveedor_id" required>
                            <option value="">Seleccionar proveedor</option>
                            @foreach($proveedores as $proveedor)
                                <option value="{{ $proveedor->id }}" data-prefijo="{{ $proveedor->prefijo }}" {{ $proveedorElegido == $proveedor->id ? 'selected' : '' }}>
                                    {{ $proveedor->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('proveedor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="id_proveedor" class="form-label">Código Proveedor @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.id_proveedor')])</label>
                        <div class="input-group">
                            <input type="text" class="form-control @error('id_proveedor') is-invalid @enderror" id="id_proveedor" name="id_proveedor"
                                   value="{{ old('id_proveedor', $editando ? $producto->id_proveedor : '') }}">
                            <button type="button" class="btn btn-outline-secondary" id="btn-generar-codigo" title="Generar código">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        @error('id_proveedor')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3 mb-md-0">
                        @if($editando)
                            <label class="form-label">Stock actual @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.stock_edicion')])</label>
                            <div class="input-group">
                                <span class="form-control bg-light fw-semibold text-center" style="max-width: 80px;">{{ $producto->stock }}</span>
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-ajuste-stock">
                                    <i class="bi bi-arrow-left-right"></i> Ajustar
                                </button>
                                <a href="{{ route('admin.productos.historial', $producto) }}" class="btn btn-outline-info" title="Ver historial de movimientos">
                                    <i class="bi bi-clock-history"></i>
                                </a>
                            </div>
                            <small class="text-muted">Se modifica con movimientos, que quedan registrados.</small>
                        @else
                            <label for="stock" class="form-label">Stock inicial * @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.stock_inicial')])</label>
                            <input type="number" min="0" class="form-control @error('stock') is-invalid @enderror" id="stock" name="stock" value="{{ old('stock', 0) }}" required>
                            @error('stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @endif
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <label class="form-label d-block">Disponible @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.disponible')])</label>
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="disponible" name="disponible" value="1" {{ old('disponible', $editando ? $producto->disponible : true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="disponible">Mostrar en tienda</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label d-block">Por Encargue @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.por_encargue')])</label>
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="por_encargue" name="por_encargue" value="1" {{ old('por_encargue', $editando ? $producto->por_encargue : App\Models\Configuracion::porEncarguePorDefecto()) ? 'checked' : '' }}>
                            <label class="form-check-label" for="por_encargue">Disponible sin stock</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('admin.productos.partials.etiquetas', ['valoresIniciales' => $etiquetasIniciales])
        @include('admin.productos.partials.especificaciones', ['especificacionesIniciales' => $especificacionesIniciales])
        @include('admin.productos.partials.card-precio')
        @include('admin.productos.partials.galeria')
        @include('admin.productos.partials.card-seo')
    </div>

    <div class="col-lg-4 col-xl-3">
        @include('admin.productos.partials.vista-previa')
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/quill/quill.snow.css') }}">
<style>
    #detalle-editor { min-height: 120px; background: #fff; }
    .ql-toolbar { border-radius: 6px 6px 0 0; }
    .ql-container { border-radius: 0 0 6px 6px; font-size: 1rem; }
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

    document.getElementById('form-producto').addEventListener('submit', function () {
        var contenido = quill.root.innerHTML;
        document.getElementById('detalle').value = contenido === '<p><br></p>' ? '' : contenido;
    });

    document.getElementById('btn-generar-codigo').addEventListener('click', function () {
        var sel = document.getElementById('proveedor_id');
        var option = sel.options[sel.selectedIndex];
        var prefijo = option ? (option.dataset.prefijo || '').trim().toUpperCase() : '';
        var numero = Math.floor(100000 + Math.random() * 900000);
        document.getElementById('id_proveedor').value = prefijo ? prefijo + '-' + numero : String(numero);
    });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
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
