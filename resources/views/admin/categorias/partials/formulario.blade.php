{{--
    Formulario de categoría, compartido por alta y edición.
    Recibe $categoria (nueva o existente) y $madres (las que pueden ser "Dentro de").
--}}
@php $hayErroresSeo = $errors->hasAny(['meta_title', 'meta_description']); @endphp

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre"
                               value="{{ old('nombre', $categoria->nombre) }}" required maxlength="255" placeholder="Ej: Gaseosas" autofocus>
                        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="parent_id" class="form-label">Dentro de</label>
                        <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                            <option value="">En ninguna (va suelta)</option>
                            @foreach($madres as $madre)
                                <option value="{{ $madre->id }}" {{ (string) old('parent_id', $categoria->parent_id) === (string) $madre->id ? 'selected' : '' }}>
                                    {{ str_repeat('— ', $madre->nivel()) }}{{ $madre->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Ej: «Gaseosas» dentro de «Bebidas». Hasta {{ App\Models\Categoria::NIVEL_MAXIMO + 1 }} niveles.</small>
                    </div>
                </div>

                <input type="hidden" name="visible" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="visible" name="visible" value="1"
                           {{ old('visible', $categoria->exists ? $categoria->marcadaVisible() : true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="visible">Mostrar esta categoría en la tienda</label>
                    <small class="d-block text-muted">Si la ocultás, también se ocultan sus subcategorías. Los productos no se ocultan: siguen en la búsqueda y en sus otras categorías.</small>
                </div>
            </div>
        </div>

        @include('admin.categorias.partials.caracteristicas')

        <div class="card mb-3">
            <div class="card-header" role="button" data-bs-toggle="collapse" data-bs-target="#seccion-seo"
                 aria-expanded="{{ $hayErroresSeo ? 'true' : 'false' }}">
                <i class="bi bi-search"></i> SEO <small class="text-muted">(opcional)</small>
                <i class="bi bi-chevron-down float-end"></i>
            </div>
            <div class="collapse {{ $hayErroresSeo ? 'show' : '' }}" id="seccion-seo">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="meta_title" class="form-label">Título para Google</label>
                        <input type="text" class="form-control @error('meta_title') is-invalid @enderror" id="meta_title" name="meta_title" maxlength="60"
                               value="{{ old('meta_title', $categoria->meta_title) }}" placeholder="Si lo dejás vacío, se usa el nombre">
                        @error('meta_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="meta_description" class="form-label">Descripción para Google</label>
                        <textarea class="form-control @error('meta_description') is-invalid @enderror" id="meta_description" name="meta_description" rows="2" maxlength="160">{{ old('meta_description', $categoria->meta_description) }}</textarea>
                        @error('meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Guardar siempre a mano, abajo (la tarjeta fija de arriba quedaba tapada por la barra del panel). --}}
@include('admin.partials.barra-guardar', [
    'formId'       => 'form-categoria',
    'textoGuardar' => $categoria->exists ? 'Guardar cambios' : 'Crear categoría',
    'cancelarUrl'  => route('admin.categorias.index'),
])
