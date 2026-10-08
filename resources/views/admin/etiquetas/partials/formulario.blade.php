{{--
    Formulario de característica (tabla etiquetas), compartido por alta y edición.

    Qué productos la piden ya no se define acá (antes era por proveedor): la piden
    las categorías ("Datos que se piden al cargar un producto") o, si es global, todos los productos.
    En edición se listan las categorías que la piden, para ir a cambiarlo.

    Recibe $etiqueta (nueva o existente) y, en edición, $categoriasQueLaPiden
    con $obligatoriaEn (categoria_id => obligatoria).
--}}
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control @error('nombre') is-invalid @enderror"
                           id="nombre" name="nombre" value="{{ old('nombre', $etiqueta->nombre) }}"
                           placeholder="Ej: Talle, Color, Marca" required maxlength="255">
                    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">El dato que se carga en cada producto. El valor (M, Rojo, Nike…) se escribe en el producto.</div>
                </div>

                <input type="hidden" name="visible_usuarios" value="0">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="visible_usuarios" name="visible_usuarios" value="1"
                           {{ old('visible_usuarios', $etiqueta->exists ? $etiqueta->visible_usuarios : true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="visible_usuarios">Mostrarla en la tienda</label>
                    <div class="form-text">Apagada, sirve sólo para uso interno: el cliente no la ve en el producto ni en los filtros.</div>
                </div>

                <input type="hidden" name="global" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="global" name="global" value="1"
                           {{ old('global', $etiqueta->global) ? 'checked' : '' }}>
                    <label class="form-check-label" for="global">Pedirla en todos los productos</label>
                    <div class="form-text">Para datos que tiene cualquier producto, como Marca. Si no, la piden sólo las categorías que la elijan.</div>
                </div>
                <div class="ms-5 mt-2" id="global-obligatoria">
                    <input type="hidden" name="obligatoria_global" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="obligatoria_global" name="obligatoria_global" value="1"
                               {{ old('obligatoria_global', $etiqueta->obligatoria_global) ? 'checked' : '' }}>
                        <label class="form-check-label" for="obligatoria_global">Obligatoria: sin completarla no se puede guardar un producto</label>
                    </div>
                </div>
            </div>
        </div>

        @isset($categoriasQueLaPiden)
            <div class="card mb-4">
                <div class="card-header">Qué categorías la piden</div>
                @if($categoriasQueLaPiden->isEmpty())
                    <div class="card-body text-muted small">
                        Ninguna. Se agrega al editar una categoría, en "Datos que se piden al cargar un producto".
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($categoriasQueLaPiden as $categoria)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    {{ str_replace(' > ', ' › ', $categoria->camino()) }}
                                    <span class="badge {{ $obligatoriaEn[$categoria->id] ? 'text-bg-danger' : 'text-bg-light' }} ms-1">{{ $obligatoriaEn[$categoria->id] ? 'Obligatoria' : 'Opcional' }}</span>
                                </span>
                                @if(auth()->user()->puede('categorias.gestionar'))
                                    <a href="{{ route('admin.categorias.edit', $categoria) }}" class="btn btn-sm btn-outline-primary" title="Editar la categoría"><i class="bi bi-pencil"></i></a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endisset

        @push('scripts')
        <script>
            (function () {
                var global = document.getElementById('global'), extra = document.getElementById('global-obligatoria');
                function mostrar() { extra.classList.toggle('d-none', !global.checked); }
                global.addEventListener('change', mostrar);
                mostrar();
            })();
        </script>
        @endpush

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle"></i> {{ $etiqueta->exists ? 'Guardar cambios' : 'Crear característica' }}
            </button>
            <a href="{{ route('admin.etiquetas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </div>
</div>
