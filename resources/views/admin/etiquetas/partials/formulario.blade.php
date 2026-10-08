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
        {{-- Arriba, a la vista: el interruptor queda en el medio de la tarjeta. --}}
        @error('varios_valores')
            <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
        @enderror
        <div class="card mb-4">
            <div class="card-body">
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control @error('nombre') is-invalid @enderror"
                           id="nombre" name="nombre" value="{{ old('nombre', $etiqueta->nombre) }}"
                           placeholder="Ej: Marca, Sabor, Contenido" required maxlength="255">
                    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">El dato que se carga en cada producto. El valor (Arcor, Frutilla, 500 g…) se escribe en el producto.</div>
                </div>

                <input type="hidden" name="visible_usuarios" value="0">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="visible_usuarios" name="visible_usuarios" value="1"
                           {{ old('visible_usuarios', $etiqueta->exists ? $etiqueta->visible_usuarios : true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="visible_usuarios">Mostrarla en la tienda</label>
                    <div class="form-text">Apagada, sirve sólo para uso interno: el cliente no la ve en el producto ni en los filtros.</div>
                </div>

                <input type="hidden" name="varios_valores" value="0">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input @error('varios_valores') is-invalid @enderror" type="checkbox" role="switch" id="varios_valores" name="varios_valores" value="1"
                           {{-- Si no se pudo apagar, sigue prendido: es como quedó guardado. --}}
                           {{ ($errors->has('varios_valores') ? $etiqueta->varios_valores : old('varios_valores', $etiqueta->varios_valores)) ? 'checked' : '' }}>
                    <label class="form-check-label" for="varios_valores">Un producto puede tener varios</label>
                    <div class="form-text">Por ejemplo Talle: una remera viene en S, M y L. Si cada talle tiene su propio stock, lo vas a poder cargar en el producto. La Marca, en cambio, es una sola.</div>
                </div>

                <input type="hidden" name="global" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="global" name="global" value="1"
                           {{ old('global', $etiqueta->global) ? 'checked' : '' }}>
                    <label class="form-check-label" for="global">Pedirla en todos los productos</label>
                    <div class="form-text">Para datos que tiene cualquier producto, como Marca. Si no, la piden sólo las categorías que la elijan.</div>
                </div>
                {{-- El mismo control que en "Datos que se piden" de la categoría, para
                     que obligatoria/opcional se elija igual en los dos lugares. --}}
                @php $obligatoriaGlobal = (bool) old('obligatoria_global', $etiqueta->obligatoria_global); @endphp
                <div class="ms-5 mt-2" id="global-obligatoria">
                    <div class="small text-muted mb-1">En todos los productos, este dato es:</div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Opcional u obligatoria">
                        <input type="radio" class="btn-check" name="obligatoria_global" id="obligatoria_global_0" value="0" {{ $obligatoriaGlobal ? '' : 'checked' }}>
                        <label class="btn btn-outline-secondary" for="obligatoria_global_0">Opcional</label>
                        <input type="radio" class="btn-check" name="obligatoria_global" id="obligatoria_global_1" value="1" {{ $obligatoriaGlobal ? 'checked' : '' }}>
                        <label class="btn btn-outline-danger" for="obligatoria_global_1">Obligatoria</label>
                    </div>
                    <div class="form-text">Obligatoria: sin completarla no se puede guardar un producto.</div>
                </div>
            </div>
        </div>

        @isset($categoriasQueLaPiden)
            <div class="card mb-4">
                <div class="card-header">Qué categorías la piden</div>
                {{-- Muestra lo guardado: si es global la respuesta es "todos", y
                     decir "Ninguna" confundía. --}}
                @if($etiqueta->global)
                    <div class="card-body small {{ $categoriasQueLaPiden->isEmpty() ? '' : 'border-bottom' }}">
                        <i class="bi bi-globe2 text-primary"></i>
                        <strong>La piden todos los productos</strong>
                        ({{ $etiqueta->obligatoria_global ? 'obligatoria' : 'opcional' }}).
                    </div>
                @endif
                @if($categoriasQueLaPiden->isEmpty())
                    @unless($etiqueta->global)
                        <div class="card-body text-muted small">
                            Ninguna. Se agrega al editar una categoría, en "Datos que se piden al cargar un producto".
                        </div>
                    @endunless
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

    </div>
</div>

@include('admin.partials.barra-guardar', [
    'formId'       => 'form-caracteristica',
    'textoGuardar' => $etiqueta->exists ? 'Guardar cambios' : 'Crear característica',
    'cancelarUrl'  => route('admin.etiquetas.index'),
])
