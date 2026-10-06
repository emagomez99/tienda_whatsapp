{{--
    Formulario de menú, compartido por alta y edición.

    Ordenado por las preguntas que se hace quien arma el menú, una por bloque:
      1. Qué es y dónde va        (nombre, padre, activo)
      2. Qué productos muestra    (proveedor / etiqueta / nada, disponibilidad)
      3. Qué filtros ve el cliente
      SEO va plegado: casi nunca se toca y tiene valores por defecto razonables.

    Recibe: $menu (nuevo o existente), $menusParent, $proveedores, $etiquetas,
    $etiquetasPorProveedor, $filtrosHeredados. En alta, además, $siguienteOrdenRaiz y
    $siguienteOrdenPorPadre para sugerir el orden según el padre.
--}}
@php
    $tipoActual       = old('tipo_enlace', $menu->tipo_enlace);
    $filtrosActuales  = array_map('intval', old('filtros_etiquetas', $menu->filtros_etiquetas ?? []));
    $hayOld           = session()->hasOldInput();
    $hayErroresSeo    = $errors->hasAny(['slug', 'meta_title', 'meta_description']);
    // Especificación no la usa ningún menú: se ofrece sólo si este ya la tiene.
    $ofrecerEspecificacion = $tipoActual === \App\Models\Menu::TIPO_ESPECIFICACION;
@endphp

<input type="hidden" name="enlace_id" id="enlace_id" value="{{ old('enlace_id', $menu->enlace_id) }}">
<input type="hidden" name="enlace_valor" id="enlace_valor" value="{{ old('enlace_valor', $menu->enlace_valor) }}">

<div class="row">
    <div class="col-lg-8">

        {{-- ① Qué es --}}
        <div class="card mb-3">
            <div class="card-header"><span class="paso">1</span> Qué es y dónde va</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre"
                               value="{{ old('nombre', $menu->nombre) }}" required placeholder="Ej: Notebooks">
                        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">El texto que ve el cliente en el menú.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="parent_id" class="form-label">Dentro de</label>
                        <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                            <option value="">Menú principal (primer nivel)</option>
                            @foreach($menusParent as $menuParent)
                                <option value="{{ $menuParent->id }}" {{ old('parent_id', $menu->parent_id) == $menuParent->id ? 'selected' : '' }}>
                                    {{ str_repeat('— ', $menuParent->parent_id ? 1 : 0) }}{{ $menuParent->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="d-flex align-items-center gap-4">
                    <div class="form-check form-switch mb-0">
                        <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" {{ old('activo', $menu->activo) ? 'checked' : '' }}>
                        <label class="form-check-label" for="activo">Visible en la tienda</label>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="orden" class="form-label mb-0 small text-muted">Posición</label>
                        <input type="number" class="form-control form-control-sm @error('orden') is-invalid @enderror" id="orden" name="orden"
                               value="{{ old('orden', $menu->orden) }}" min="0" style="width:80px;">
                    </div>
                </div>
            </div>
        </div>

        {{-- ② Qué productos muestra --}}
        <div class="card mb-3">
            <div class="card-header"><span class="paso">2</span> Qué productos muestra</div>
            <div class="card-body">
                <div class="alert alert-info py-2 small d-none" id="aviso-herencia">
                    <i class="bi bi-diagram-2"></i> Al estar dentro de otro menú, además se aplica: <strong id="texto-herencia"></strong>
                </div>

                <div class="tipo-opciones">
                    <label class="tipo-opcion">
                        <input class="form-check-input" type="radio" name="tipo_enlace" value="ninguno" {{ $tipoActual === 'ninguno' ? 'checked' : '' }}>
                        <span>
                            <strong>Ninguno</strong>
                            <small class="d-block text-muted">Sólo agrupa submenús. Al hacer clic se despliegan sus opciones.</small>
                        </span>
                    </label>

                    <label class="tipo-opcion">
                        <input class="form-check-input" type="radio" name="tipo_enlace" value="etiqueta" {{ $tipoActual === 'etiqueta' ? 'checked' : '' }}>
                        <span>
                            <strong>Los que tienen una etiqueta</strong>
                            <small class="d-block text-muted">Ej: Marca = Asus, o todos los que tengan Categoría.</small>
                        </span>
                    </label>
                    <div class="tipo-campos row g-2" data-tipo="etiqueta">
                        <div class="col-md-6">
                            <select class="form-select" id="etiqueta_select" aria-label="Etiqueta">
                                <option value="">Elegí la etiqueta...</option>
                                @foreach($etiquetas as $etiqueta)
                                    <option value="{{ $etiqueta->id }}" {{ old('enlace_id', $menu->tipo_enlace === 'etiqueta' ? $menu->enlace_id : '') == $etiqueta->id ? 'selected' : '' }}>
                                        {{ $etiqueta->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="etiqueta_valor_input" aria-label="Valor"
                                   value="{{ old('enlace_valor', $menu->tipo_enlace === 'etiqueta' ? $menu->enlace_valor : '') }}"
                                   placeholder="Cualquier valor"
                                   data-combo
                                   data-combo-sin-nuevo
                                   data-combo-fila=".tipo-campos"
                                   data-combo-desde="#etiqueta_select"
                                   data-combo-url-con="{{ route('admin.menus.etiqueta.valores', ['etiqueta' => '__ID__']) }}">
                        </div>
                    </div>

                    <label class="tipo-opcion">
                        <input class="form-check-input" type="radio" name="tipo_enlace" value="proveedor" {{ $tipoActual === 'proveedor' ? 'checked' : '' }}>
                        <span>
                            <strong>Los de un proveedor</strong>
                            <small class="d-block text-muted">Todo lo que te provee, ej: el catálogo de Hercules.</small>
                        </span>
                    </label>
                    <div class="tipo-campos" data-tipo="proveedor">
                        <select class="form-select" id="proveedor_select" aria-label="Proveedor">
                            <option value="">Elegí el proveedor...</option>
                            @foreach($proveedores as $proveedor)
                                <option value="{{ $proveedor->id }}" {{ old('enlace_id', $menu->tipo_enlace === 'proveedor' ? $menu->enlace_id : '') == $proveedor->id ? 'selected' : '' }}>
                                    {{ $proveedor->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($ofrecerEspecificacion)
                        <label class="tipo-opcion">
                            <input class="form-check-input" type="radio" name="tipo_enlace" value="especificacion" checked>
                            <span>
                                <strong>Los que tienen un valor en sus especificaciones</strong>
                                <small class="d-block text-muted">Busca el texto en cualquier especificación del producto.</small>
                            </span>
                        </label>
                        <div class="tipo-campos" data-tipo="especificacion">
                            <input type="text" class="form-control" id="especificacion_valor_input" aria-label="Valor de especificación"
                                   value="{{ old('enlace_valor', $menu->enlace_valor) }}" placeholder="Ej: 1kg, Rojo...">
                        </div>
                    @endif
                </div>

                <div class="row mt-3 seccion-con-productos">
                    <div class="col-md-6">
                        <label for="filtro_stock" class="form-label">Disponibilidad</label>
                        <select name="filtro_stock" id="filtro_stock" class="form-select">
                            <option value="todos" {{ old('filtro_stock', $menu->filtro_stock) === 'todos' ? 'selected' : '' }}>Todos los productos</option>
                            <option value="con_stock" {{ old('filtro_stock', $menu->filtro_stock) === 'con_stock' ? 'selected' : '' }}>Sólo con stock</option>
                            <option value="con_stock_y_encargue" {{ old('filtro_stock', $menu->filtro_stock) === 'con_stock_y_encargue' ? 'selected' : '' }}>Con stock o por encargue</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ③ Filtros para el cliente --}}
        <div class="card mb-3 seccion-con-productos" id="seccion-filtros">
            <div class="card-header"><span class="paso">3</span> Filtros para el cliente <small class="text-muted">(opcional)</small></div>
            <div class="card-body">
                <p class="text-muted small mb-2">Desplegables que el cliente usa para achicar la lista, en este orden. Ej: Fabricante → Aplicación → Modelo.</p>

                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label small">Etiquetas disponibles</label>
                        <div class="border rounded p-2 bg-white" style="min-height: 120px;">
                            <ul class="list-group list-group-flush" id="etiquetas-disponibles"></ul>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small">Filtros elegidos</label>
                        <div class="border rounded p-2 bg-light" style="min-height: 120px;">
                            <ul class="list-group list-group-flush" id="filtros-seleccionados">
                                @foreach($filtrosActuales as $filtroId)
                                    @php $etiquetaFiltro = $etiquetas->firstWhere('id', $filtroId); @endphp
                                    @if($etiquetaFiltro)
                                        @php
                                            $permitirTodos = $hayOld
                                                ? (bool) old('filtros_todos.' . $filtroId)
                                                : ($menu->filtros_config[(string) $filtroId] ?? true);
                                        @endphp
                                        @include('admin.menus.partials.filtro-elegido', ['etiqueta' => $etiquetaFiltro, 'permitirTodos' => $permitirTodos])
                                    @endif
                                @endforeach
                            </ul>
                            <div class="text-center text-muted small py-3" id="filtros-vacio">
                                <i class="bi bi-inbox"></i> Sin filtros: se muestran todos los productos del menú.
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="filtros_requeridos" value="0">
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="filtros_requeridos" id="filtros_requeridos" value="1"
                           {{ old('filtros_requeridos', $menu->filtros_requeridos) ? 'checked' : '' }}>
                    <label class="form-check-label" for="filtros_requeridos">
                        El cliente tiene que completar todos los filtros para ver productos
                    </label>
                    <small class="d-block text-muted">Útil cuando sin elegir todo la lista es demasiado larga, como en un buscador de repuestos por modelo.</small>
                </div>
            </div>
        </div>

        {{-- SEO --}}
        <div class="card mb-3">
            <div class="card-header" role="button" data-bs-toggle="collapse" data-bs-target="#seccion-seo"
                 aria-expanded="{{ $hayErroresSeo ? 'true' : 'false' }}">
                <i class="bi bi-search"></i> SEO y dirección web <small class="text-muted">(opcional)</small>
                <i class="bi bi-chevron-down float-end"></i>
            </div>
            <div class="collapse {{ $hayErroresSeo ? 'show' : '' }}" id="seccion-seo">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="slug" class="form-label">Dirección</label>
                        <div class="input-group">
                            <span class="input-group-text text-muted small">/catalogo/</span>
                            <input type="text" class="form-control @error('slug') is-invalid @enderror"
                                   id="slug" name="slug" value="{{ old('slug', $menu->slug) }}" placeholder="se arma con el nombre">
                            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <small class="text-muted">Sólo letras minúsculas, números y guiones.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="meta_title" class="form-label">Título para Google</label>
                            <input type="text" class="form-control @error('meta_title') is-invalid @enderror" id="meta_title" name="meta_title" maxlength="60"
                                   placeholder="{{ $menu->exists ? $menu->meta_title : 'Si lo dejás vacío, se usa el nombre' }}"
                                   value="{{ old('meta_title', $menu->getRawOriginal('meta_title')) }}">
                            @error('meta_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted contador-caracteres" data-max="60">0/60</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="meta_description" class="form-label">Descripción para Google</label>
                            <textarea class="form-control @error('meta_description') is-invalid @enderror" id="meta_description" name="meta_description" rows="2" maxlength="160">{{ old('meta_description', $menu->meta_description) }}</textarea>
                            @error('meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted contador-caracteres" data-max="160">0/160</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3 position-sticky" style="top: 1rem;">
            <div class="card-body">
                <button type="submit" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-check-circle"></i> {{ $menu->exists ? 'Guardar cambios' : 'Crear menú' }}
                </button>
                <a href="{{ route('admin.menus.index') }}" class="btn btn-outline-secondary w-100">Cancelar</a>
            </div>
        </div>

        @if($menu->exists && $menu->children->count() > 0)
            <div class="card">
                <div class="card-header bg-light">
                    <i class="bi bi-diagram-3"></i> Submenús ({{ $menu->children->count() }})
                </div>
                <ul class="list-group list-group-flush">
                    @foreach($menu->children as $child)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ $child->nombre }}
                            <a href="{{ route('admin.menus.edit', $child) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>

<template id="plantilla-filtro-elegido">
    @include('admin.menus.partials.filtro-elegido', ['etiqueta' => null, 'permitirTodos' => true])
</template>

@push('styles')
<style>
    .paso {
        display: inline-flex; align-items: center; justify-content: center;
        width: 1.5rem; height: 1.5rem; border-radius: 50%;
        background: var(--bs-primary); color: #fff; font-size: .8rem; font-weight: 600; margin-right: .4rem;
    }
    .tipo-opcion {
        display: flex; gap: .75rem; align-items: flex-start;
        border: 1px solid #dee2e6; border-radius: .5rem; padding: .65rem .85rem; margin-bottom: .5rem; cursor: pointer;
    }
    .tipo-opcion:has(input:checked) { border-color: var(--bs-primary); background: rgba(13,110,253,.04); }
    .tipo-opcion input { margin-top: .2rem; flex-shrink: 0; }
    .tipo-campos { margin: -.25rem 0 .75rem 2.1rem; }
</style>
@endpush

@push('scripts')
@include('admin.productos.partials.combo-sugerencias')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = function (id) { return document.getElementById(id); };

    var etiquetasData         = @json($etiquetas->map(function ($e) { return ['id' => $e->id, 'nombre' => $e->nombre]; })->values());
    var etiquetasPorProveedor = @json($etiquetasPorProveedor);
    var filtrosHeredados      = @json($filtrosHeredados);

    var enlaceId    = el('enlace_id');
    var enlaceValor = el('enlace_valor');
    var proveedor   = el('proveedor_select');
    var etiqueta    = el('etiqueta_select');
    var valor       = el('etiqueta_valor_input');
    var especif     = el('especificacion_valor_input');
    var padre       = el('parent_id');
    var disponibles = el('etiquetas-disponibles');
    var elegidos    = el('filtros-seleccionados');

    function tipo() {
        return document.querySelector('input[name="tipo_enlace"]:checked').value;
    }

    // ── Bloque 2: qué productos muestra ─────────────────────────────────────

    // Lo elegido en los campos visibles va a enlace_id / enlace_valor, que es lo
    // que guarda el servidor.
    function volcarEnlace() {
        enlaceId.value = '';
        enlaceValor.value = '';
        if (tipo() === 'proveedor') {
            enlaceId.value = proveedor.value;
        } else if (tipo() === 'etiqueta') {
            enlaceId.value = etiqueta.value;
            enlaceValor.value = valor.value.trim();
        } else if (tipo() === 'especificacion' && especif) {
            enlaceValor.value = especif.value.trim();
        }
    }

    function mostrarSegunTipo() {
        document.querySelectorAll('.tipo-campos').forEach(function (campos) {
            campos.classList.toggle('d-none', campos.dataset.tipo !== tipo());
        });
        // Un menú que sólo agrupa no tiene productos: ni disponibilidad ni filtros.
        document.querySelectorAll('.seccion-con-productos').forEach(function (seccion) {
            seccion.classList.toggle('d-none', tipo() === 'ninguno');
        });
        volcarEnlace();
        reconstruirDisponibles();
    }

    function mostrarHerencia() {
        var filtros = padre.value ? (filtrosHeredados[padre.value] || []) : [];
        el('aviso-herencia').classList.toggle('d-none', filtros.length === 0);
        el('texto-herencia').textContent = filtros.join(' y ');
    }

    document.querySelectorAll('input[name="tipo_enlace"]').forEach(function (radio) {
        radio.addEventListener('change', mostrarSegunTipo);
    });
    proveedor.addEventListener('change', function () { volcarEnlace(); reconstruirDisponibles(); });
    etiqueta.addEventListener('change', function () { valor.value = ''; volcarEnlace(); });
    valor.addEventListener('input', volcarEnlace);
    valor.addEventListener('change', volcarEnlace);
    if (especif) especif.addEventListener('input', volcarEnlace);
    padre.addEventListener('change', mostrarHerencia);
    el('menu-form').addEventListener('submit', volcarEnlace);

    // ── Bloque 3: filtros para el cliente ───────────────────────────────────

    // Si el proveedor tiene etiquetas configuradas, sólo esas tienen sentido como
    // filtro: las demás no las tiene ningún producto suyo.
    function etiquetasAplicables() {
        if (tipo() !== 'proveedor' || !proveedor.value) return null;
        var cfg = etiquetasPorProveedor[String(proveedor.value)];
        return cfg && cfg.configured ? cfg.ids : null;
    }

    function renumerar() {
        var items = elegidos.querySelectorAll('li');
        items.forEach(function (li, i) { li.querySelector('.badge-num').textContent = i + 1; });
        el('filtros-vacio').classList.toggle('d-none', items.length > 0);
    }

    function reconstruirDisponibles() {
        var aplicables = etiquetasAplicables();

        if (aplicables !== null) {
            elegidos.querySelectorAll('li').forEach(function (li) {
                if (aplicables.indexOf(parseInt(li.dataset.id, 10)) === -1) li.remove();
            });
        }

        var yaElegidos = Array.prototype.map.call(elegidos.querySelectorAll('li'), function (li) {
            return parseInt(li.dataset.id, 10);
        });

        disponibles.innerHTML = '';
        etiquetasData.forEach(function (e) {
            if (yaElegidos.indexOf(e.id) !== -1) return;
            if (aplicables !== null && aplicables.indexOf(e.id) === -1) return;

            var li = document.createElement('li');
            li.className = 'list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center';
            li.dataset.id = e.id;
            li.dataset.nombre = e.nombre;
            li.textContent = e.nombre;
            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'btn btn-sm btn-outline-success';
            boton.dataset.accion = 'agregar';
            boton.title = 'Agregar';
            boton.innerHTML = '<i class="bi bi-plus"></i>';
            li.appendChild(boton);
            disponibles.appendChild(li);
        });

        renumerar();
    }

    function agregar(id, nombre) {
        var nodo = el('plantilla-filtro-elegido').content.firstElementChild.cloneNode(true);
        nodo.dataset.id = id;
        nodo.querySelector('.filtro-nombre').textContent = nombre;
        nodo.querySelector('input[type="hidden"]').value = id;
        var todos = nodo.querySelector('input[type="checkbox"]');
        todos.name = 'filtros_todos[' + id + ']';
        todos.id = 'todos_' + id;
        nodo.querySelector('label').htmlFor = todos.id;
        elegidos.appendChild(nodo);
        reconstruirDisponibles();
    }

    document.addEventListener('click', function (e) {
        var boton = e.target.closest('[data-accion]');
        if (!boton) return;
        var li = boton.closest('li');

        if (boton.dataset.accion === 'agregar') {
            agregar(li.dataset.id, li.dataset.nombre);
        } else if (boton.dataset.accion === 'quitar') {
            li.remove();
            reconstruirDisponibles();
        } else if (boton.dataset.accion === 'subir' && li.previousElementSibling) {
            li.parentNode.insertBefore(li, li.previousElementSibling);
            renumerar();
        } else if (boton.dataset.accion === 'bajar' && li.nextElementSibling) {
            li.parentNode.insertBefore(li.nextElementSibling, li);
            renumerar();
        }
    });

    // ── Datos generales y SEO ───────────────────────────────────────────────

    var nombre = el('nombre');
    var slug   = el('slug');
    var slugTocado = slug.value !== '';

    slug.addEventListener('input', function () {
        slugTocado = this.value !== '';
        var pos = this.selectionStart;
        this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
        this.setSelectionRange(pos, pos);
    });

    // En alta la dirección se arma sola con el nombre, hasta que se la toque a mano.
    nombre.addEventListener('input', function () {
        if (slugTocado) return;
        slug.value = this.value.toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/[^a-z0-9\s-]/g, '').trim().replace(/\s+/g, '-');
    });

    @isset($siguienteOrdenPorPadre)
        // En alta se propone quedar último entre los hermanos del padre elegido.
        var siguienteOrdenRaiz = {{ $siguienteOrdenRaiz }};
        var siguienteOrdenPorPadre = @json($siguienteOrdenPorPadre);
        padre.addEventListener('change', function () {
            el('orden').value = !this.value
                ? siguienteOrdenRaiz
                : (siguienteOrdenPorPadre[this.value] !== undefined ? siguienteOrdenPorPadre[this.value] : 0);
        });
    @endisset

    document.querySelectorAll('.contador-caracteres').forEach(function (contador) {
        var campo = contador.parentElement.querySelector('input, textarea');
        var max = parseInt(contador.dataset.max, 10);
        function actualizar() {
            contador.textContent = campo.value.length + '/' + max;
            contador.classList.toggle('text-danger', campo.value.length > max);
        }
        campo.addEventListener('input', actualizar);
        actualizar();
    });

    mostrarSegunTipo();
    mostrarHerencia();
});
</script>
@endpush
