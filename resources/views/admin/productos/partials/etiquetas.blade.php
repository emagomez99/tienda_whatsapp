{{--
    Etiquetas del producto, compartido por alta y edición.

    Qué etiquetas lleva el producto lo define el proveedor (Etiquetas → Proveedores),
    así que no se eligen de una lista: se muestran como campos con nombre, primero las
    obligatorias (con *) y después las opcionales. Al cambiar de proveedor se rearman
    sin perder lo ya escrito en las etiquetas que siguen aplicando.

    Debajo de cada valor se avisa si ya existe (y cuántos productos lo usan) o si es
    nuevo, y en ese caso si se parece a uno existente: el error de tipeo ("Asuz") es
    la forma más común de duplicar valores.

    Recibe: $etiquetas, $etiquetasObligatorias (proveedor → [{id, nombre}]),
    $etiquetasAplicables (proveedor → [id]) y $valoresIniciales ([{etiqueta_id, valor}]).

    Envía lo mismo que antes: etiquetas[i][etiqueta_id] y etiquetas[i][valor].
--}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-tags"></i> Etiquetas
            @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.etiquetas'), 'lugar' => 'right', 'grande' => true])
        </h5>
        <small class="text-muted" id="etiquetas-origen"></small>
    </div>
    <div class="card-body">
        <div id="etiquetas-sin-proveedor" class="text-center text-muted py-4">
            <i class="bi bi-arrow-up-circle fs-4 d-block mb-1"></i>
            Elegí primero un <strong>proveedor</strong>.<br>
            <small>Las etiquetas que lleva el producto dependen de él.</small>
        </div>

        <div id="etiquetas-sin-configurar" class="text-center text-muted py-4 d-none">
            <i class="bi bi-tags fs-4 d-block mb-1"></i>
            Este proveedor no tiene etiquetas configuradas.
            @if(auth()->user()->puede('etiquetas.ver'))
                <br><small>Se definen en <a href="{{ route('admin.etiquetas.index') }}" target="_blank">Etiquetas</a>, eligiendo para cada una qué proveedores la usan.</small>
            @endif
        </div>

        <div id="etiquetas-container"></div>

        <small class="text-muted d-none" id="etiquetas-leyenda"><span class="text-danger">*</span> obligatoria para este proveedor: sin completarla no se puede guardar.</small>

        @error('etiquetas')
            <div class="text-danger small mt-2"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
        @enderror
    </div>
</div>

@push('styles')
<style>
    .etiqueta-row .col-form-label { font-weight: 500; }
    .etiqueta-estado { min-height: 1.1rem; }
    .etiqueta-estado a { cursor: pointer; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var URL_VALORES = @json(route('admin.etiquetas.valores', ['etiqueta' => '__ID__']));
    var URL_ESTADO  = @json(route('admin.etiquetas.valores.estado', ['etiqueta' => '__ID__']));

    var ETIQUETAS    = @json($etiquetas->map(function ($e) { return ['id' => $e->id, 'nombre' => $e->nombre, 'visible' => (bool) $e->visible_usuarios]; })->values());
    var OBLIGATORIAS = @json($etiquetasObligatorias);
    var APLICABLES   = @json($etiquetasAplicables);
    var INICIALES    = @json(array_values($valoresIniciales));

    var proveedor  = document.getElementById('proveedor_id');
    var contenedor = document.getElementById('etiquetas-container');
    var indice     = 0;

    // Lo escrito, por etiqueta: sobrevive a los cambios de proveedor.
    var valores = {};
    INICIALES.forEach(function (fila) {
        if (fila && fila.etiqueta_id) valores[String(fila.etiqueta_id)] = fila.valor || '';
    });

    var porId = {};
    ETIQUETAS.forEach(function (e) { porId[String(e.id)] = e; });

    function el(etiqueta, clase, texto) {
        var nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    }

    function guardarLoEscrito() {
        contenedor.querySelectorAll('.etiqueta-row').forEach(function (fila) {
            valores[fila.dataset.etiquetaId] = fila.querySelector('.etiqueta-valor').value;
        });
    }

    /**
     * Una fila: nombre de la etiqueta, campo de valor con sugerencias y el aviso de
     * abajo. Las que no aplican al proveedor (quedaron de otro) se pueden quitar.
     */
    function fila(etiqueta, tipo) {
        var id = String(etiqueta.id);
        var i  = indice++;

        var row = el('div', 'row g-2 mb-2 etiqueta-row');
        row.dataset.etiquetaId = id;
        row.dataset.nombre = etiqueta.nombre;

        var label = el('label', 'col-md-3 col-form-label', etiqueta.nombre);
        label.htmlFor = 'etiqueta-valor-' + i;
        if (tipo === 'obligatoria') {
            label.appendChild(el('span', 'text-danger ms-1', '*'));
        }
        if (!etiqueta.visible) {
            var ojo = el('i', 'bi bi-eye-slash text-muted ms-1');
            ojo.title = 'No se muestra en la tienda';
            label.appendChild(ojo);
        }
        row.appendChild(label);

        var col = el('div', tipo === 'no-aplica' ? 'col-md-8' : 'col-md-9');

        var oculto = el('input');
        oculto.type = 'hidden';
        oculto.name = 'etiquetas[' + i + '][etiqueta_id]';
        oculto.value = id;
        col.appendChild(oculto);

        var input = el('input', 'form-control etiqueta-valor');
        input.type = 'text';
        input.id = 'etiqueta-valor-' + i;
        input.name = 'etiquetas[' + i + '][valor]';
        input.value = valores[id] || '';
        input.maxLength = 255;
        input.autocomplete = 'off';
        input.placeholder = tipo === 'obligatoria' ? 'Escribí o elegí un valor' : 'Opcional';
        input.required = tipo === 'obligatoria';
        input.setAttribute('data-combo', URL_VALORES.replace('__ID__', id));
        col.appendChild(input);

        var estado = el('div', 'etiqueta-estado small mt-1');
        if (tipo === 'no-aplica') {
            estado.classList.add('text-warning');
            estado.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Este proveedor no usa esta etiqueta.';
        }
        col.appendChild(estado);
        row.appendChild(col);

        if (tipo === 'no-aplica') {
            var quitar = el('div', 'col-md-1');
            var boton = el('button', 'btn btn-outline-danger');
            boton.type = 'button';
            boton.title = 'Quitar del producto';
            boton.innerHTML = '<i class="bi bi-trash"></i>';
            boton.addEventListener('click', function () {
                delete valores[id];
                row.remove();
                input.dispatchEvent(new Event('input', { bubbles: true }));   // refresca la vista previa
            });
            quitar.appendChild(boton);
            row.appendChild(quitar);
        } else {
            vigilar(input, estado, id);
        }

        return row;
    }

    function pintar() {
        guardarLoEscrito();
        contenedor.innerHTML = '';

        var prov = proveedor.value;
        var obligatorias = prov ? (OBLIGATORIAS[prov] || []).map(function (e) { return String(e.id); }) : [];
        var aplicables   = prov ? (APLICABLES[prov] || []).map(String) : [];

        // Primero las obligatorias, en el orden del proveedor; después el resto.
        var orden = obligatorias.concat(aplicables.filter(function (id) { return obligatorias.indexOf(id) === -1; }));

        orden.forEach(function (id) {
            if (porId[id]) contenedor.appendChild(fila(porId[id], obligatorias.indexOf(id) !== -1 ? 'obligatoria' : 'opcional'));
        });

        // Valores de etiquetas que este proveedor no usa: se muestran para decidir
        // qué hacer con ellos en vez de perderlos en silencio.
        Object.keys(valores).forEach(function (id) {
            if (orden.indexOf(id) === -1 && porId[id] && (valores[id] || '').trim() !== '') {
                contenedor.appendChild(fila(porId[id], 'no-aplica'));
            }
        });

        var hayFilas = contenedor.children.length > 0;
        document.getElementById('etiquetas-sin-proveedor').classList.toggle('d-none', !!prov);
        document.getElementById('etiquetas-sin-configurar').classList.toggle('d-none', !prov || hayFilas);
        document.getElementById('etiquetas-leyenda').classList.toggle('d-none', obligatorias.length === 0);
        document.getElementById('etiquetas-origen').textContent = prov && hayFilas
            ? 'según el proveedor ' + proveedor.options[proveedor.selectedIndex].text.trim()
            : '';

        contenedor.dispatchEvent(new Event('input', { bubbles: true }));   // refresca la vista previa
    }

    // ── Aviso debajo del valor ───────────────────────────────────────────────

    // Lo que tenía cada etiqueta al abrir la página: si no se tocó no hay nada que
    // avisar (al editar, cada fila diría "valor existente" sin aportar nada).
    var alAbrir = {};
    INICIALES.forEach(function (fila) {
        if (fila && fila.etiqueta_id) alAbrir[String(fila.etiqueta_id)] = (fila.valor || '').trim();
    });

    function vigilar(input, estado, etiquetaId) {
        var espera = null, secuencia = 0;

        function consultar() {
            var escrito = input.value.trim();
            var mia = ++secuencia;

            if (escrito === '' || escrito === alAbrir[etiquetaId]) {
                estado.className = 'etiqueta-estado small mt-1';
                estado.innerHTML = '';
                return;
            }

            fetch(URL_ESTADO.replace('__ID__', etiquetaId) + '?valor=' + encodeURIComponent(escrito), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (datos) {
                    if (mia !== secuencia || !datos) return;
                    mostrar(datos);
                })
                .catch(function () {});
        }

        function productos(n) {
            return n + (n === 1 ? ' producto' : ' productos');
        }

        function mostrar(datos) {
            estado.className = 'etiqueta-estado small mt-1';
            estado.innerHTML = '';

            if (datos.estado === 'existente') {
                estado.classList.add('text-success');
                estado.appendChild(el('i', 'bi bi-check-circle me-1'));
                var usan = (datos.productos === 1 ? 'usa ' : 'usan ') + productos(datos.productos);
                estado.appendChild(document.createTextNode(
                    datos.valor !== input.value.trim()
                        ? 'Se guarda como «' + datos.valor + '», que ya ' + usan + '.'
                        : 'Valor existente: lo ' + usan + '.'
                ));
            } else if (datos.estado === 'nuevo' && datos.parecido) {
                estado.classList.add('text-warning-emphasis');
                estado.appendChild(el('i', 'bi bi-exclamation-triangle me-1'));
                estado.appendChild(document.createTextNode('Valor nuevo. ¿Quisiste decir '));
                var usar = el('a', 'fw-semibold', '«' + datos.parecido.valor + '»');
                usar.setAttribute('role', 'button');
                usar.addEventListener('click', function () {
                    input.value = datos.parecido.valor;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    consultar();
                });
                estado.appendChild(usar);
                estado.appendChild(document.createTextNode(' (' + productos(datos.parecido.productos) + ')?'));
            } else if (datos.estado === 'nuevo') {
                estado.classList.add('text-primary');
                estado.appendChild(el('i', 'bi bi-plus-circle me-1'));
                estado.appendChild(document.createTextNode('Valor nuevo: se crea al guardar.'));
            }
        }

        input.addEventListener('input', function () {
            clearTimeout(espera);
            espera = setTimeout(consultar, 400);
        });
        input.addEventListener('change', function () {   // al elegir una sugerencia
            clearTimeout(espera);
            consultar();
        });

        if (input.value.trim() !== '') consultar();
    }

    proveedor.addEventListener('change', pintar);
    pintar();
})();
</script>
@endpush
