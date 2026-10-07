{{--
    Categorías del producto, como en Tiendanube: chips con las elegidas y un panel
    lateral con búsqueda y casillas para elegir, y "Crear categoría" ahí mismo.

    Al elegir una subcategoría, el producto aparece también en las de arriba (se
    calcula al mostrar la tienda): no hace falta marcarlas, y el panel lo indica.

    Recibe $categorias (Categoria::enOrdenDeArbol()) y $seleccionadas (ids).
    Envía categorias[] con los ids elegidos.
--}}
@php
    $puedeCrear = auth()->user()->puede('categorias.gestionar');
    $datosCategorias = $categorias->map(function ($c) {
        return ['id' => $c->id, 'nombre' => $c->nombre, 'camino' => $c->camino(), 'nivel' => $c->nivel(), 'padre' => $c->parent_id];
    })->values();
@endphp

<div class="card mb-4" id="card-categorias">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Categorías
            @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.categorias')])
        </h5>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2" id="categorias-chips"></div>
        <div id="categorias-ocultas"></div>
        @error('categorias')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        @error('categorias.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="panel-categorias" aria-labelledby="panel-categorias-titulo">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="panel-categorias-titulo">Categorías</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <div class="p-3 border-bottom">
            <input type="search" class="form-control" id="categorias-buscar" placeholder="Buscar categoría" autocomplete="off">
            @if($puedeCrear)
                <button type="button" class="btn btn-link px-0 mt-2" id="categorias-crear-abrir">
                    <i class="bi bi-plus-circle"></i> Crear categoría
                </button>
                <div class="d-none mt-2" id="categorias-crear">
                    <input type="text" class="form-control mb-2" id="categorias-crear-nombre" placeholder="Nombre (ej: Remeras)" maxlength="255">
                    <select class="form-select mb-2" id="categorias-crear-madre" aria-label="Dentro de"></select>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary btn-sm" id="categorias-crear-guardar">Crear y elegir</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="categorias-crear-cancelar">Cancelar</button>
                    </div>
                    <div class="text-danger small mt-1 d-none" id="categorias-crear-error"></div>
                </div>
            @endif
        </div>
        <div class="flex-grow-1 overflow-auto" id="categorias-lista"></div>
        <div class="p-3 border-top">
            <button type="button" class="btn btn-primary w-100" data-bs-dismiss="offcanvas">Listo</button>
        </div>
    </div>
</div>

@push('styles')
<style>
    .categoria-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .4rem .25rem .65rem; border-radius: 1rem;
                      background: #f1f3f5; font-size: .875rem; }
    .categoria-chip button { border: 0; background: none; padding: 0 .15rem; line-height: 1; color: #6c757d; }
    .categoria-chip button:hover { color: #dc3545; }
    .categoria-opcion { display: flex; align-items: center; justify-content: space-between; gap: .75rem;
                        padding: .6rem 1rem; border-bottom: 1px solid #f1f3f5; cursor: pointer; margin: 0; }
    .categoria-opcion:hover { background: #f8f9fa; }
    .categoria-opcion .camino-madre { color: #6c757d; }
    .categoria-opcion .incluida { font-size: .75rem; color: #6c757d; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var URL_CREAR   = @json(route('admin.categorias.rapida'));
    var NIVEL_MADRE = {{ App\Models\Categoria::NIVEL_MAXIMO_PADRE }};
    var categorias  = @json($datosCategorias);
    var elegidas    = @json(array_values(array_map('intval', $seleccionadas)));

    var chips   = document.getElementById('categorias-chips');
    var ocultas = document.getElementById('categorias-ocultas');
    var lista   = document.getElementById('categorias-lista');
    var buscar  = document.getElementById('categorias-buscar');

    function porId(id) {
        for (var i = 0; i < categorias.length; i++) if (categorias[i].id === id) return categorias[i];
        return null;
    }

    /** Ids de las madres de las elegidas: el producto ya aparece ahí sin marcarlas. */
    function incluidasPorHijas() {
        var mapa = {};
        elegidas.forEach(function (id) {
            var c = porId(id);
            while (c && c.padre) { mapa[c.padre] = c.nombre; c = porId(c.padre); }
        });
        return mapa;
    }

    function avisarCambio() {
        chips.dispatchEvent(new Event('change', { bubbles: true }));   // aviso de cambios sin guardar
    }

    function pintarChips() {
        chips.innerHTML = '';
        ocultas.innerHTML = '';

        elegidas.forEach(function (id) {
            var c = porId(id);
            if (!c) return;

            var chip = document.createElement('span');
            chip.className = 'categoria-chip';
            chip.appendChild(document.createTextNode(c.camino.replace(/ > /g, ' › ')));
            var quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.title = 'Quitar';
            quitar.setAttribute('aria-label', 'Quitar ' + c.nombre);
            quitar.innerHTML = '<i class="bi bi-x-lg"></i>';
            quitar.addEventListener('click', function () { alternar(id, false); });
            chip.appendChild(quitar);
            chips.appendChild(chip);

            var campo = document.createElement('input');
            campo.type = 'hidden';
            campo.name = 'categorias[]';
            campo.value = id;
            ocultas.appendChild(campo);
        });

        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn btn-sm btn-outline-primary';
        boton.setAttribute('data-bs-toggle', 'offcanvas');
        boton.setAttribute('data-bs-target', '#panel-categorias');
        boton.innerHTML = elegidas.length
            ? '<i class="bi bi-pencil"></i> Elegir categorías'
            : '<i class="bi bi-plus-lg"></i> Elegir categorías';
        chips.appendChild(boton);

        if (!elegidas.length) {
            var vacio = document.createElement('span');
            vacio.className = 'text-muted small';
            vacio.textContent = categorias.length
                ? 'Todavía no está en ninguna categoría.'
                : 'Todavía no hay categorías: creá la primera desde el panel.';
            chips.insertBefore(vacio, boton);
        }
    }

    function pintarLista() {
        var texto = (buscar.value || '').trim().toLowerCase();
        var incluidas = incluidasPorHijas();
        lista.innerHTML = '';

        categorias.forEach(function (c) {
            if (texto && c.camino.toLowerCase().indexOf(texto) === -1) return;

            var fila = document.createElement('label');
            fila.className = 'categoria-opcion';
            var izquierda = document.createElement('span');
            var partes = c.camino.split(' > ');
            if (partes.length > 1) {
                var madre = document.createElement('span');
                madre.className = 'camino-madre';
                madre.textContent = partes.slice(0, -1).join('/') + '/';
                izquierda.appendChild(madre);
            }
            izquierda.appendChild(document.createTextNode(c.nombre));
            if (incluidas[c.id] && elegidas.indexOf(c.id) === -1) {
                var nota = document.createElement('div');
                nota.className = 'incluida';
                nota.textContent = 'Ya aparece acá por «' + incluidas[c.id] + '»';
                izquierda.appendChild(nota);
            }
            fila.appendChild(izquierda);

            var casilla = document.createElement('input');
            casilla.type = 'checkbox';
            casilla.className = 'form-check-input flex-shrink-0';
            casilla.checked = elegidas.indexOf(c.id) !== -1;
            casilla.addEventListener('change', function () { alternar(c.id, casilla.checked); });
            fila.appendChild(casilla);

            lista.appendChild(fila);
        });

        if (!lista.children.length) {
            lista.innerHTML = '<div class="p-3 text-muted small">' + (categorias.length ? 'No hay categorías con ese nombre.' : 'Todavía no hay categorías.') + '</div>';
        }
    }

    function alternar(id, elegir) {
        var i = elegidas.indexOf(id);
        if (elegir && i === -1) elegidas.push(id);
        if (!elegir && i !== -1) elegidas.splice(i, 1);
        pintarChips();
        pintarLista();
        avisarCambio();
    }

    buscar.addEventListener('input', pintarLista);

    // ── Crear sin salir del producto ────────────────────────────────────────

    var abrir = document.getElementById('categorias-crear-abrir');
    if (abrir) {
        var caja    = document.getElementById('categorias-crear');
        var nombre  = document.getElementById('categorias-crear-nombre');
        var madre   = document.getElementById('categorias-crear-madre');
        var error   = document.getElementById('categorias-crear-error');
        var guardar = document.getElementById('categorias-crear-guardar');

        function opcionesDeMadre() {
            madre.innerHTML = '<option value="">Primer nivel</option>';
            categorias.forEach(function (c) {
                if (c.nivel > NIVEL_MADRE) return;
                var op = document.createElement('option');
                op.value = c.id;
                op.textContent = 'Dentro de ' + c.camino.replace(/ > /g, ' › ');
                madre.appendChild(op);
            });
        }

        function mostrarCrear(mostrar) {
            caja.classList.toggle('d-none', !mostrar);
            abrir.classList.toggle('d-none', mostrar);
            error.classList.add('d-none');
            if (mostrar) {
                opcionesDeMadre();
                nombre.value = buscar.value.trim();   // lo que se buscó y no estaba
                nombre.focus();
            }
        }

        abrir.addEventListener('click', function () { mostrarCrear(true); });
        document.getElementById('categorias-crear-cancelar').addEventListener('click', function () { mostrarCrear(false); });
        nombre.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); guardar.click(); }   // sin esto, Enter envía el producto
        });

        guardar.addEventListener('click', function () {
            guardar.disabled = true;
            fetch(URL_CREAR, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ nombre: nombre.value, parent_id: madre.value || null })
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, datos: d }; }); })
                .then(function (respuesta) {
                    if (!respuesta.ok) {
                        var errores = respuesta.datos.errors || {};
                        var primero = Object.keys(errores)[0];
                        error.textContent = primero ? errores[primero][0] : 'No se pudo crear la categoría.';
                        error.classList.remove('d-none');
                        return;
                    }
                    // Se ubica después de su madre (o al final) para que la lista siga el árbol.
                    var nueva = respuesta.datos, posicion = categorias.length;
                    if (nueva.padre) {
                        for (var i = 0; i < categorias.length; i++) {
                            if (categorias[i].id === nueva.padre || categorias[i].camino.indexOf(porId(nueva.padre).camino + ' > ') === 0) posicion = i + 1;
                        }
                    }
                    categorias.splice(posicion, 0, nueva);
                    buscar.value = '';
                    mostrarCrear(false);
                    alternar(nueva.id, true);
                })
                .catch(function () {
                    error.textContent = 'No se pudo crear la categoría. Revisá la conexión.';
                    error.classList.remove('d-none');
                })
                .finally(function () { guardar.disabled = false; });
        });
    }

    pintarChips();
    pintarLista();
})();
</script>
@endpush
