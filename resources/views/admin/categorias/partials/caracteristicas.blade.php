{{--
    "Datos que se piden al cargar un producto" en el formulario de categoría (maqueta B del plan).

    Una sola lista con todo lo que se va a pedir al cargar un producto en esta
    categoría. Cada fila dice de dónde sale: las que vienen de una categoría de
    arriba o de "todos los productos" se ven bloqueadas, con un enlace a donde se
    cambian; las de esta categoría se marcan opcional u obligatoria, se ordenan y
    se quitan. Se agregan eligiendo una existente o escribiendo una nueva.

    Opcionales: $explicacionCaracteristicas (el texto de arriba) y $ordenable (false
    oculta las flechas, para "Todos los productos", que no guarda orden).

    Recibe $todasLasCaracteristicas, $caracteristicasPropias, $heredablesPorMadre
    (madre → [{id, nombre, obligatoria, origen}]) y $caracteristicasGlobales.
    Envía caracteristicas[i][etiqueta_id | nueva] y caracteristicas[i][obligatoria].
--}}
@php
    $propiasIniciales = old('caracteristicas') !== null
        ? array_values(array_map(function ($f) use ($todasLasCaracteristicas) {
            $id = isset($f['etiqueta_id']) ? (int) $f['etiqueta_id'] : null;
            $e = $id ? $todasLasCaracteristicas->firstWhere('id', $id) : null;
            return ['etiqueta_id' => $id, 'nombre' => $e ? $e->nombre : ($f['nueva'] ?? ''), 'obligatoria' => !empty($f['obligatoria'])];
        }, old('caracteristicas', [])))
        : $caracteristicasPropias;
@endphp

<div class="card mb-3" id="card-caracteristicas">
    <div class="card-header">
        <i class="bi bi-ui-checks"></i> Datos que se piden al cargar un producto
        <small class="text-muted d-block">{{ $explicacionCaracteristicas ?? 'Cuando cargues un producto en esta categoría, el formulario te va a pedir estos datos. Las obligatorias hay que completarlas para poder guardar.' }}</small>
    </div>
    <div class="card-body">
        <ul class="list-group mb-2" id="caracteristicas-propias"></ul>
        <div class="text-muted small mb-2" id="caracteristicas-vacio">Todavía no se pide ningún dato. Agregá uno abajo (ej: Sabor, Contenido).</div>

        <div class="input-group" style="max-width: 420px;">
            <input type="text" class="form-control" id="caracteristica-nueva" list="caracteristicas-existentes"
                   placeholder="Agregar un dato: elegí uno o escribí uno nuevo" maxlength="255" autocomplete="off"
                   aria-label="Característica a agregar">
            <button type="button" class="btn btn-outline-primary" id="caracteristica-agregar"><i class="bi bi-plus-lg"></i> Agregar</button>
        </div>
        <datalist id="caracteristicas-existentes">
            @foreach($todasLasCaracteristicas as $c)
                <option value="{{ $c->nombre }}"></option>
            @endforeach
        </datalist>
        <div class="small text-danger mt-1 d-none" id="caracteristica-aviso"></div>
        @error('caracteristicas')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        <div id="caracteristicas-campos"></div>
    </div>
</div>

@push('styles')
<style>
    #caracteristicas-propias .list-group-item { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
    #caracteristicas-propias .nombre { flex: 1 1 8rem; font-weight: 500; }
    #caracteristicas-propias .heredada { background: #f8f9fa; }
    #caracteristicas-propias .heredada .nombre { color: #495057; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var TODAS     = @json($todasLasCaracteristicas->map(function ($c) { return ['id' => $c->id, 'nombre' => $c->nombre]; })->values());
    var POR_MADRE = @json((object) $heredablesPorMadre);
    var GLOBALES  = @json($caracteristicasGlobales);
    var propias   = @json(array_values($propiasIniciales));
    var ORDENABLE = @json($ordenable ?? true);   // "Todos los productos" no guarda orden

    var madre     = document.getElementById('parent_id');
    var lista     = document.getElementById('caracteristicas-propias');
    var URL_EDITAR = @json(route('admin.categorias.edit', ['categoria' => '__ID__']));
    var campos    = document.getElementById('caracteristicas-campos');
    var entrada   = document.getElementById('caracteristica-nueva');
    var aviso     = document.getElementById('caracteristica-aviso');

    function normalizar(texto) { return (texto || '').trim().replace(/\s+/g, ' ').toLowerCase(); }

    function deLaMadre() {
        return madre && madre.value ? (POR_MADRE[madre.value] || []) : GLOBALES;
    }

    function cambio() {
        lista.dispatchEvent(new Event('change', { bubbles: true }));   // aviso de cambios sin guardar
    }

    function etiquetaDeEstado(obligatoria) {
        var badge = document.createElement('span');
        badge.className = 'badge ' + (obligatoria ? 'text-bg-danger' : 'text-bg-secondary');
        badge.textContent = obligatoria ? 'Obligatoria' : 'Opcional';
        return badge;
    }

    /** Una que viene de arriba: se ve, pero se cambia donde se definió. */
    function filaHeredada(c) {
        var item = document.createElement('li');
        item.className = 'list-group-item heredada';

        var nombre = document.createElement('span');
        nombre.className = 'nombre';
        nombre.appendChild(document.createTextNode(c.nombre));
        var nota = document.createElement('small');
        nota.className = 'd-block text-muted fw-normal';
        nota.appendChild(el('i', 'bi bi-lock me-1'));
        if (c.categoria_id) {
            nota.appendChild(document.createTextNode('Viene de '));
            var enlace = document.createElement('a');
            enlace.href = URL_EDITAR.replace('__ID__', c.categoria_id);
            enlace.textContent = c.origen;
            enlace.title = 'Se cambia en «' + c.origen + '»';
            nota.appendChild(enlace);
        } else {
            nota.appendChild(document.createTextNode('Se pide en todos los productos (se cambia en Características)'));
        }
        nombre.appendChild(nota);
        item.appendChild(nombre);
        item.appendChild(etiquetaDeEstado(c.obligatoria));
        return item;
    }

    function el(tag, clase) {
        var nodo = document.createElement(tag);
        nodo.className = clase;
        return nodo;
    }

    function pintar() {
        lista.innerHTML = '';
        campos.innerHTML = '';
        var heredables = {};
        deLaMadre().forEach(function (c) { heredables[normalizar(c.nombre)] = c; });

        // Primero lo que viene de arriba (salvo lo que esta categoría cambia), después lo suyo.
        var propiasPorNombre = {};
        propias.forEach(function (c) { propiasPorNombre[normalizar(c.nombre)] = true; });
        deLaMadre().forEach(function (c) {
            if (!propiasPorNombre[normalizar(c.nombre)]) lista.appendChild(filaHeredada(c));
        });

        propias.forEach(function (c, i) {
            var item = document.createElement('li');
            item.className = 'list-group-item';

            var nombre = document.createElement('span');
            nombre.className = 'nombre';
            nombre.textContent = c.nombre;
            if (!c.etiqueta_id) {
                var nueva = document.createElement('span');
                nueva.className = 'badge text-bg-primary ms-1';
                nueva.textContent = 'nueva';
                nombre.appendChild(nueva);
            }
            var pisa = heredables[normalizar(c.nombre)];
            if (pisa) {
                var nota = document.createElement('small');
                nota.className = 'd-block text-muted fw-normal';
                nota.textContent = (pisa.origen ? 'En «' + pisa.origen + '»' : 'Para todos los productos') + ' es ' + (pisa.obligatoria ? 'obligatoria' : 'opcional') + '; en esta categoría vale lo que elijas acá.';
                nombre.appendChild(nota);
            }
            item.appendChild(nombre);

            // Opcional / Obligatoria
            var grupo = document.createElement('div');
            grupo.className = 'btn-group btn-group-sm';
            grupo.setAttribute('role', 'group');
            [['0', 'Opcional'], ['1', 'Obligatoria']].forEach(function (op) {
                var id = 'car-' + i + '-' + op[0];
                var radio = document.createElement('input');
                radio.type = 'radio';
                radio.className = 'btn-check';
                radio.name = 'car-obligatoria-' + i;
                radio.id = id;
                radio.checked = (op[0] === '1') === !!c.obligatoria;
                radio.addEventListener('change', function () { c.obligatoria = op[0] === '1'; pintar(); cambio(); });
                var label = document.createElement('label');
                label.className = 'btn btn-outline-' + (op[0] === '1' ? 'danger' : 'secondary');
                label.htmlFor = id;
                label.textContent = op[1];
                grupo.appendChild(radio);
                grupo.appendChild(label);
            });
            item.appendChild(grupo);

            (ORDENABLE ? [['arriba', 'bi-arrow-up', i > 0], ['abajo', 'bi-arrow-down', i < propias.length - 1]] : []).forEach(function (b) {
                var boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'btn btn-sm btn-light';
                boton.title = b[0] === 'arriba' ? 'Subir' : 'Bajar';
                boton.disabled = !b[2];
                boton.innerHTML = '<i class="bi ' + b[1] + '"></i>';
                boton.addEventListener('click', function () {
                    var j = b[0] === 'arriba' ? i - 1 : i + 1;
                    var tmp = propias[i]; propias[i] = propias[j]; propias[j] = tmp;
                    pintar(); cambio();
                });
                item.appendChild(boton);
            });

            var quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.className = 'btn btn-sm btn-outline-danger';
            quitar.title = 'Quitar de esta categoría';
            quitar.setAttribute('aria-label', 'Quitar ' + c.nombre);
            quitar.innerHTML = '<i class="bi bi-x-lg"></i>';
            quitar.addEventListener('click', function () { propias.splice(i, 1); pintar(); cambio(); });
            item.appendChild(quitar);

            lista.appendChild(item);

            // Lo que se envía.
            var campoId = document.createElement('input');
            campoId.type = 'hidden';
            if (c.etiqueta_id) { campoId.name = 'caracteristicas[' + i + '][etiqueta_id]'; campoId.value = c.etiqueta_id; }
            else { campoId.name = 'caracteristicas[' + i + '][nueva]'; campoId.value = c.nombre; }
            var campoOblig = document.createElement('input');
            campoOblig.type = 'hidden';
            campoOblig.name = 'caracteristicas[' + i + '][obligatoria]';
            campoOblig.value = c.obligatoria ? '1' : '0';
            campos.appendChild(campoId);
            campos.appendChild(campoOblig);
        });

        document.getElementById('caracteristicas-vacio').classList.toggle('d-none', lista.children.length > 0);
    }

    function agregar() {
        var escrito = entrada.value.trim().replace(/\s+/g, ' ');
        aviso.classList.add('d-none');
        if (!escrito) return;

        if (propias.some(function (c) { return normalizar(c.nombre) === normalizar(escrito); })) {
            aviso.textContent = '«' + escrito + '» ya está en la lista.';
            aviso.classList.remove('d-none');
            return;
        }
        var existente = TODAS.filter(function (c) { return normalizar(c.nombre) === normalizar(escrito); })[0];
        // Si ya viene de arriba, arranca igual que allá: agregarla no la afloja sin querer.
        var deArriba = deLaMadre().filter(function (c) { return normalizar(c.nombre) === normalizar(escrito); })[0];
        var obligatoria = deArriba ? !!deArriba.obligatoria : false;
        propias.push(existente
            ? { etiqueta_id: existente.id, nombre: existente.nombre, obligatoria: obligatoria }
            : { etiqueta_id: null, nombre: escrito, obligatoria: false });
        entrada.value = '';
        pintar(); cambio();
        entrada.focus();
    }

    document.getElementById('caracteristica-agregar').addEventListener('click', agregar);
    entrada.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); agregar(); }   // sin esto, Enter envía la categoría
    });
    if (madre) madre.addEventListener('change', pintar);

    pintar();
})();
</script>
@endpush
