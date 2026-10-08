{{--
    Etiquetas del producto, compartido por alta y edición.

    Qué datos lleva el producto lo definen sus categorías (Categorías → Características
    que pide, con lo heredado de las de arriba) más las que piden todos los productos.
    No se eligen de una lista: se muestran como campos con nombre, primero las
    obligatorias (con *) y después las opcionales. Al cambiar las categorías se rearman
    sin perder lo ya escrito.

    Debajo de cada valor se avisa si ya existe (y cuántos productos lo usan) o si es
    nuevo, y en ese caso si se parece a uno existente: el error de tipeo ("Asuz") es
    la forma más común de duplicar valores.

    Recibe: $etiquetas, $caracteristicasPorCategoria (CaracteristicasPorCategoria::
    mapaParaFormulario) y $valoresIniciales ([{etiqueta_id, valor}]). Las categorías
    elegidas las lee de los campos categorias[] del partial de categorías.

    Envía etiquetas[i][etiqueta_id] y etiquetas[i][valor]: una fila por valor. Una
    característica que admite varios (Talle: S, M, L) se carga con fichitas y manda
    una fila por fichita.
--}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-ui-checks"></i> Características
            @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.etiquetas'), 'lugar' => 'right', 'grande' => true])
        </h5>
        <small class="text-muted" id="etiquetas-origen"></small>
    </div>
    <div class="card-body">
        <div id="etiquetas-sin-categoria" class="text-center text-muted py-4">
            <i class="bi bi-arrow-up-circle fs-4 d-block mb-1"></i>
            Elegí una <strong>categoría</strong> para que te pidamos los datos que importan.
        </div>

        <div id="etiquetas-sin-configurar" class="text-center text-muted py-4 d-none">
            <i class="bi bi-ui-checks fs-4 d-block mb-1"></i>
            Sus categorías todavía no piden ningún dato.
            @if(auth()->user()->puede('categorias.gestionar'))
                <br><small>Se definen en <a href="{{ route('admin.categorias.index') }}" target="_blank">Categorías</a>, en "Datos que se piden al cargar un producto".</small>
            @endif
        </div>

        <div id="etiquetas-container"></div>

        <small class="text-muted d-none" id="etiquetas-leyenda"><span class="text-danger">*</span> obligatoria: sin completarla no se puede guardar.</small>

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

@php
    // Armado acá: el @json de Blade corta mal una expresión larga con varios paréntesis.
    $etiquetasParaElFormulario = $etiquetas->map(function ($e) {
        return ['id' => $e->id, 'nombre' => $e->nombre, 'visible' => (bool) $e->visible_usuarios, 'varios' => (bool) $e->varios_valores];
    })->values();
@endphp

@push('scripts')
<script>
(function () {
    var URL_VALORES = @json(route('admin.etiquetas.valores', ['etiqueta' => '__ID__']));
    var URL_ESTADO  = @json(route('admin.etiquetas.valores.estado', ['etiqueta' => '__ID__']));

    var ETIQUETAS    = @json($etiquetasParaElFormulario);
    var MAPA         = @json($caracteristicasPorCategoria);
    var INICIALES    = @json(array_values($valoresIniciales));

    var ocultasCategorias = document.getElementById('categorias-ocultas');
    var contenedor = document.getElementById('etiquetas-container');
    var indice     = 0;

    // Lo escrito, por etiqueta (una lista: puede tener varios valores): sobrevive a
    // los cambios de categoría.
    var valores = {};
    INICIALES.forEach(function (fila) {
        if (!fila || !fila.etiqueta_id || !(fila.valor || '').trim()) return;
        var id = String(fila.etiqueta_id);
        (valores[id] = valores[id] || []).push(fila.valor);
    });

    function tieneValor(id) {
        return (valores[id] || []).some(function (v) { return (v || '').trim() !== ''; });
    }

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
            var lista = Array.prototype.map.call(fila.querySelectorAll('.etiqueta-valor-chip'), function (c) { return c.value; });
            var escrito = fila.querySelector('.etiqueta-valor').value;
            if (escrito.trim() !== '') lista.push(escrito);
            valores[fila.dataset.etiquetaId] = lista;
        });
    }

    function campoOculto(nombre, valor, clase) {
        var campo = el('input', clase || '');
        campo.type = 'hidden';
        campo.name = nombre;
        campo.value = valor;
        return campo;
    }

    /**
     * Las fichitas de una característica con varios valores (Talle: S, M, L). Cada
     * una manda su propia fila etiquetas[i]; el campo de texto, la que se está escribiendo.
     */
    function fichas(id, input, lista) {
        var caja = el('div', 'd-flex flex-wrap gap-1 mb-1 etiqueta-fichas');

        function agregar(valor) {
            valor = (valor || '').trim();
            if (valor === '') return false;
            var repetido = Array.prototype.some.call(caja.querySelectorAll('.etiqueta-valor-chip'), function (c) {
                return c.value.trim().toLowerCase() === valor.toLowerCase();
            });
            if (repetido) return false;

            var i = indice++;
            var ficha = el('span', 'badge rounded-pill text-bg-light border d-inline-flex align-items-center gap-1 fw-normal fs-6');
            ficha.appendChild(el('span', '', valor));
            ficha.appendChild(campoOculto('etiquetas[' + i + '][etiqueta_id]', id));
            ficha.appendChild(campoOculto('etiquetas[' + i + '][valor]', valor, 'etiqueta-valor-chip'));
            var quitar = el('button', 'btn btn-link btn-sm p-0 text-danger lh-1');
            quitar.type = 'button';
            quitar.title = 'Quitar «' + valor + '»';
            quitar.setAttribute('aria-label', 'Quitar ' + valor);
            quitar.innerHTML = '<i class="bi bi-x-circle-fill"></i>';
            quitar.addEventListener('click', function () {
                ficha.remove();
                actualizarRequerido();
                input.dispatchEvent(new Event('input', { bubbles: true }));   // vista previa y "sin guardar"
            });
            ficha.appendChild(quitar);
            caja.appendChild(ficha);
            actualizarRequerido();
            return true;
        }

        // Obligatoria: alcanza con una fichita; el campo deja de exigirse.
        var obligatorio = input.required;
        function actualizarRequerido() {
            input.required = obligatorio && caja.children.length === 0;
        }

        function agregarLoEscrito() {
            if (agregar(input.value)) {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        lista.forEach(agregar);
        input.addEventListener('keydown', function (e) {
            // Enter con la lista de sugerencias abierta lo maneja el combo (elige y avisa con change).
            if (e.key === 'Enter' && !input.parentElement.querySelector('.combo-lista:not(.d-none) .activo')) {
                e.preventDefault();
                agregarLoEscrito();
            }
        });
        input.addEventListener('change', function () { setTimeout(agregarLoEscrito, 0); });

        return { caja: caja, agregarLoEscrito: agregarLoEscrito };
    }

    /**
     * Una fila: nombre de la etiqueta, campo de valor con sugerencias y el aviso de
     * abajo. Las que ya no pide ninguna de sus categorías se pueden quitar.
     */
    /** "Lo pide Gaseosas", "Lo piden Gaseosas y Ofertas" o "Todos los productos". */
    function quienLoPide(origenes) {
        if (origenes.indexOf(null) !== -1) return 'Se pide en todos los productos';
        var nombres = origenes.filter(Boolean);
        if (!nombres.length) return '';
        return (nombres.length === 1 ? 'Lo pide ' : 'Lo piden ') + nombres.join(' y ');
    }

    function fila(etiqueta, tipo, origen) {
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
        // Debajo del nombre, quién lo pide: así nadie se pregunta por qué aparece.
        if (origen) {
            label.appendChild(el('small', 'd-block text-muted fw-normal etiqueta-origen-dato', origen));
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
        input.maxLength = 255;
        input.autocomplete = 'off';
        input.required = tipo === 'obligatoria';
        input.setAttribute('data-combo', URL_VALORES.replace('__ID__', id));

        if (etiqueta.varios) {
            // Varios valores: fichitas arriba y el campo para sumar otro.
            input.placeholder = 'Escribí un valor y apretá Enter (podés poner varios)';
            var envoltorio = el('div');
            var widget = fichas(id, input, valores[id] || []);
            col.appendChild(widget.caja);
            envoltorio.appendChild(input);
            col.appendChild(envoltorio);
        } else {
            input.value = (valores[id] || [])[0] || '';
            input.placeholder = tipo === 'obligatoria' ? 'Escribí o elegí un valor' : 'Opcional';
            col.appendChild(input);
        }

        var estado = el('div', 'etiqueta-estado small mt-1');
        if (tipo === 'no-aplica') {
            estado.classList.add('text-warning');
            estado.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Ninguna de sus categorías pide este dato: podés quitarlo o dejarlo.';
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

    function categoriasElegidas() {
        return Array.prototype.map.call(ocultasCategorias.querySelectorAll('input[name="categorias[]"]'), function (campo) { return String(campo.value); });
    }

    /**
     * Las que piden las categorías elegidas, sin repetir: obligatoria si alguna la
     * exige (como CaracteristicasPorCategoria::deCategorias). Una categoría recién
     * creada desde el panel todavía no pide nada: quedan las globales.
     */
    function pedidas(categorias) {
        var resultado = [], porEtiqueta = {};
        function sumar(c) {
            var id = String(c.id);
            // Quién lo pide: el nombre de la categoría, o null = todos los productos.
            var origen = c.origen || null;
            if (porEtiqueta[id]) {
                porEtiqueta[id].obligatoria = porEtiqueta[id].obligatoria || c.obligatoria;
                if (porEtiqueta[id].origenes.indexOf(origen) === -1) porEtiqueta[id].origenes.push(origen);
                return;
            }
            porEtiqueta[id] = { id: id, obligatoria: !!c.obligatoria, origenes: [origen] };
            resultado.push(porEtiqueta[id]);
        }
        MAPA.globales.forEach(sumar);
        categorias.forEach(function (cat) { (MAPA.porCategoria[cat] || []).forEach(sumar); });
        return resultado;
    }

    function pintar() {
        guardarLoEscrito();
        contenedor.innerHTML = '';

        var categorias = categoriasElegidas();
        var lista = pedidas(categorias);
        // Primero las obligatorias, después el resto, cada grupo en el orden de sus categorías.
        lista = lista.filter(function (c) { return c.obligatoria; }).concat(lista.filter(function (c) { return !c.obligatoria; }));
        var ids = lista.map(function (c) { return c.id; });

        lista.forEach(function (c) {
            if (porId[c.id]) contenedor.appendChild(fila(porId[c.id], c.obligatoria ? 'obligatoria' : 'opcional', quienLoPide(c.origenes)));
        });

        // Valores de características que sus categorías no piden: se muestran para
        // decidir qué hacer con ellos en vez de perderlos en silencio.
        Object.keys(valores).forEach(function (id) {
            if (ids.indexOf(id) === -1 && porId[id] && tieneValor(id)) {
                contenedor.appendChild(fila(porId[id], 'no-aplica'));
            }
        });

        var hayFilas = contenedor.children.length > 0;
        // Sin categoría se avisa siempre; con campos (los globales) en chico, arriba de ellos.
        var sinCategoria = document.getElementById('etiquetas-sin-categoria');
        sinCategoria.classList.toggle('d-none', categorias.length > 0);
        sinCategoria.classList.toggle('py-4', !hayFilas);
        sinCategoria.classList.toggle('text-center', !hayFilas);
        sinCategoria.classList.toggle('small', hayFilas);
        sinCategoria.classList.toggle('mb-3', hayFilas);
        document.getElementById('etiquetas-sin-configurar').classList.toggle('d-none', categorias.length === 0 || hayFilas);
        document.getElementById('etiquetas-leyenda').classList.toggle('d-none', !lista.some(function (c) { return c.obligatoria; }));
        document.getElementById('etiquetas-origen').textContent = categorias.length && hayFilas ? 'según sus categorías' : '';

        contenedor.dispatchEvent(new Event('input', { bubbles: true }));   // refresca la vista previa
    }

    // ── Aviso debajo del valor ───────────────────────────────────────────────

    // Lo que tenía cada etiqueta al abrir la página: si no se tocó no hay nada que
    // avisar (al editar, cada fila diría "valor existente" sin aportar nada).
    var alAbrir = {};
    INICIALES.forEach(function (fila) {
        if (fila && fila.etiqueta_id && alAbrir[String(fila.etiqueta_id)] === undefined) {
            alAbrir[String(fila.etiqueta_id)] = (fila.valor || '').trim();
        }
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

    // El partial de categorías avisa con un "change" cada vez que se elige o se quita una.
    document.getElementById('categorias-chips').addEventListener('change', pintar);
    pintar();
})();
</script>
@endpush
