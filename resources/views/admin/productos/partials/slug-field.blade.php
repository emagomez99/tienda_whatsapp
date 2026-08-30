{{--
    Dirección web del producto. Compartido por create y edit.
    $producto viene sólo en edit (en el alta todavía no existe el id).

    Se muestra como una línea de sólo lectura con un "Editar" al lado, en vez de un
    campo abierto: casi siempre la dirección automática está bien y no hay nada que
    decidir. Al tocar "Editar" aparece el campo y se apaga la generación automática.
--}}
@php
    $productoEditado = isset($producto) ? $producto : null;
    $slugActual      = old('slug', $productoEditado ? $productoEditado->slug : '');

    // Si la dirección guardada no coincide con la que saldría del nombre, es porque
    // alguien la escribió a mano: en ese caso NO se regenera sola, o guardar sin tocar
    // nada le pisaría la dirección elegida.
    $esPersonalizada = $productoEditado
        && $productoEditado->slug !== \App\Models\Producto::generarSlug($productoEditado->descripcion);

    $automatica   = old('autogenerar_slug', $esPersonalizada ? '0' : '1') === '1';
    $abrirEdicion = $errors->has('slug');
@endphp

<div class="mb-3" id="slug-campo">
    <label class="form-label mb-1">Dirección web</label>

    <input type="hidden" name="autogenerar_slug" id="autogenerar_slug"
           value="{{ ($automatica && !$abrirEdicion) ? '1' : '0' }}">

    {{-- Vista compacta: se ve como un campo de sólo lectura con su acción al lado,
         igual que "Código Proveedor" en este mismo formulario. --}}
    <div id="slug-vista" class="{{ $abrirEdicion ? 'd-none' : '' }}">
        <div class="input-group input-group-sm">
            <span class="form-control bg-light text-truncate" style="cursor:default;">
                <span class="text-muted">/producto/</span><span id="slug-preview" class="fw-semibold text-dark">{{ $slugActual }}</span><span class="text-muted">{{ $productoEditado ? '/' . $productoEditado->id : '' }}</span>
            </span>
            <button type="button" class="btn btn-outline-secondary" id="slug-editar" data-bs-toggle="tooltip" title="Escribir una dirección propia">
                <i class="bi bi-pencil"></i> Editar
            </button>
        </div>
    </div>

    {{-- Vista de edición --}}
    <div id="slug-edicion" class="{{ $abrirEdicion ? '' : 'd-none' }}">
        <div class="input-group input-group-sm">
            <span class="input-group-text text-muted">/producto/</span>
            <input type="text" class="form-control @error('slug') is-invalid @enderror"
                   id="slug" name="slug" value="{{ $slugActual }}"
                   maxlength="100" placeholder="cubierta-camioneta"
                   data-url-preview="{{ route('admin.productos.preview-slug') }}">
            @if($productoEditado)
                <span class="input-group-text text-muted">/{{ $productoEditado->id }}</span>
            @endif
            {{-- Con texto visible y no sólo el icono: el admin se usa mucho desde el
                 celular, donde no hay hover y un tooltip no se ve nunca. --}}
            <button type="button" class="btn btn-outline-secondary text-nowrap" id="slug-auto">
                <i class="bi bi-arrow-clockwise"></i> Automática
            </button>
            @error('slug')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <small class="text-muted d-block mt-1">
            <i class="bi bi-info-circle"></i>
            Los enlaces que ya compartiste van a seguir funcionando.
        </small>
    </div>
</div>
