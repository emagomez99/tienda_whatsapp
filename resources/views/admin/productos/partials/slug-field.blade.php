{{--
    Campo de dirección web del producto. Compartido por create y edit.
    $producto viene sólo en edit (en create no existe todavía el id).

    La URL pública es /producto/{slug}-{id} y resuelve por el id, así que este
    campo es puramente cosmético: cambiarlo nunca rompe una dirección publicada.
--}}
@php $productoEditado = isset($producto) ? $producto : null; @endphp

<div class="mb-3">
    <div class="d-flex justify-content-between align-items-center">
        <label for="slug" class="form-label mb-0">Dirección web del producto</label>
        <div class="form-check form-switch mb-0">
            <input type="hidden" name="autogenerar_slug" value="0">
            <input class="form-check-input" type="checkbox" role="switch"
                   id="autogenerar_slug" name="autogenerar_slug" value="1"
                   {{ old('autogenerar_slug', $productoEditado ? false : true) ? 'checked' : '' }}>
            <label class="form-check-label small text-muted" for="autogenerar_slug">Generar automáticamente</label>
        </div>
    </div>

    <div class="input-group">
        <span class="input-group-text text-muted small">/producto/</span>
        <input type="text" class="form-control @error('slug') is-invalid @enderror"
               id="slug" name="slug"
               value="{{ old('slug', $productoEditado ? $productoEditado->slug : '') }}"
               placeholder="ej: perfume-amber-100ml"
               data-url-preview="{{ route('admin.productos.preview-slug') }}">
        {{-- El id sólo se muestra en edición: en el alta todavía no existe y mostrar
             un número inventado sería mentirle al usuario. --}}
        @if($productoEditado)
            <span class="input-group-text text-muted small">/{{ $productoEditado->id }}</span>
        @endif
        @error('slug')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <small class="text-muted">
        Es la dirección donde vive el producto en la tienda online.
        @if($productoEditado)
            El número del final lo agregamos nosotros y es lo que identifica al producto: por eso
            podés cambiar este texto cuando quieras sin que se rompa ningún enlace ya compartido.
        @else
            Al guardar le agregamos un número al final, que es lo que identifica al producto:
            por eso vas a poder cambiar este texto más adelante sin romper ningún enlace.
        @endif
    </small>
</div>
