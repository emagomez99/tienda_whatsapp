{{--
    Tarjeta de SEO. Compartida por el alta y la edición.
    $producto viene sólo en edición.

    Va colapsada y al final del formulario a propósito: "meta title" y "meta
    description" no le dicen nada a la mayoría de los usuarios, y si se dejan vacías
    el sistema las completa solo (ver Producto::getMetaTitleAttribute). Ocupaba el
    segundo lugar del formulario, arriba de la imagen y del precio.

    El chevron lo rota el manejador global de layouts/admin.blade.php, que busca la
    clase .filtros-chevron en el botón que dispara el colapso.
--}}
@php
    $productoEditado = isset($producto) ? $producto : null;
    // Con un error de validación tiene que abrirse, o el mensaje queda escondido.
    $abrirSeo = $errors->hasAny(['meta_title', 'meta_description']);
@endphp

<div class="card mb-4">
    <div class="card-header p-0">
        <button class="btn w-100 text-start d-flex align-items-center justify-content-between py-3 px-3"
                type="button" data-bs-toggle="collapse" data-bs-target="#card-seo-body"
                aria-expanded="{{ $abrirSeo ? 'true' : 'false' }}" aria-controls="card-seo-body">
            <span class="fw-semibold">
                <i class="bi bi-search"></i> Cómo se ve en Google
                <span class="text-muted fw-normal small ms-1">· opcional</span>
            </span>
            <i class="bi bi-chevron-down filtros-chevron text-muted"
               style="transition:transform .2s;{{ $abrirSeo ? 'transform:rotate(180deg);' : '' }}"></i>
        </button>
    </div>

    <div class="collapse {{ $abrirSeo ? 'show' : '' }}" id="card-seo-body">
        <div class="card-body pt-0">
            <p class="text-muted small">
                Si dejás estos campos vacíos los completamos solos con el nombre y la descripción
                del producto. Sólo cargalos si querés que Google muestre otra cosa.
            </p>

            <div class="mb-3">
                <label for="meta_title" class="form-label">Título en Google</label>
                <input type="text" class="form-control @error('meta_title') is-invalid @enderror"
                       id="meta_title" name="meta_title" maxlength="60"
                       @if($productoEditado) placeholder="{{ $productoEditado->meta_title }}" @endif
                       value="{{ old('meta_title', $productoEditado ? $productoEditado->getRawOriginal('meta_title') : '') }}">
                @error('meta_title')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted contador-caracteres" data-max="60">0/60</small>
            </div>

            <div class="mb-0">
                <label for="meta_description" class="form-label">Descripción en Google</label>
                <textarea class="form-control @error('meta_description') is-invalid @enderror"
                          id="meta_description" name="meta_description" rows="2" maxlength="160"
                          @if($productoEditado) placeholder="{{ $productoEditado->meta_description }}" @endif
                >{{ old('meta_description', $productoEditado ? $productoEditado->getRawOriginal('meta_description') : '') }}</textarea>
                @error('meta_description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted contador-caracteres" data-max="160">0/160</small>
            </div>
        </div>
    </div>
</div>
