{{--
    Variantes del producto: lo que el cliente elige al comprar (Talle M, Color Rojo),
    cada una con su propio stock. Ver App\Services\VariantesDeProducto.

    Se arman solas a partir de las características con varios valores (sección
    Características, las fichitas): se elige por cuáles ("Talle", "Talle y Color") y
    aparece una fila por combinación, para poner el stock (y si hace falta, un código
    o un precio distinto). Lo escrito en una fila se conserva aunque se agreguen o
    quiten valores.

    Recibe $usaVariantes y $variantesIniciales ([{id, valores: {etiqueta_id: valor},
    codigo, precio, stock, activa}]).
--}}
@php
    $teniaVariantes = isset($producto) && $producto->variantes->isNotEmpty();
    // Un producto que ya tiene stock y pasa a tener variantes: ese stock se reemplaza.
    $stockSinVariantes = (isset($producto) && !$teniaVariantes) ? (int) $producto->stock : 0;
@endphp

<div class="card mb-4" id="card-variantes">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-grid-3x3-gap"></i> Variantes
            @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.variantes'), 'lugar' => 'right', 'grande' => true])
        </h5>
    </div>
    <div class="card-body">
        <input type="hidden" name="variantes_enviadas" value="1">
        <input type="hidden" name="usa_variantes" value="0">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="usa_variantes" name="usa_variantes" value="1" {{ $usaVariantes ? 'checked' : '' }}>
            <label class="form-check-label" for="usa_variantes">El cliente elige al comprar (por ejemplo, el talle) y cada opción tiene su propio stock</label>
        </div>

        @if($teniaVariantes)
            <div class="alert alert-warning small mt-2 mb-0 d-none" id="variantes-se-borran">
                <i class="bi bi-exclamation-triangle"></i> Si lo apagás, al guardar se borran las variantes y su stock se suma al producto ({{ $producto->stock }} en total). Queda anotado en el historial.
            </div>
        @endif

        @error('variantes')
            <div class="text-danger small mt-2"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
        @enderror

        <div id="variantes-cuerpo" class="mt-3 {{ $usaVariantes ? '' : 'd-none' }}">
            @if($stockSinVariantes > 0)
                <div class="alert alert-info small py-2" id="variantes-stock-anterior">
                    <i class="bi bi-info-circle"></i> Hoy tiene <strong>{{ $stockSinVariantes }}</strong> en stock. Con variantes, el stock es el de cada opción:
                    repartilo abajo (por ejemplo, cuántos hay de cada talle). Al guardar, los {{ $stockSinVariantes }} se reemplazan por esa suma y queda anotado en el historial.
                </div>
            @endif
            <div id="variantes-sin-caracteristicas" class="text-muted small d-none">
                <i class="bi bi-info-circle"></i>
                Primero cargá, en <strong>Características</strong>, una que tenga varios valores: por ejemplo Talle con S, M y L.
                <br>La característica tiene que tener prendido <em>«Un producto puede tener varios»</em>.
            </div>

            <div id="variantes-ejes" class="mb-3 d-none">
                <div class="small fw-semibold mb-1">¿Qué elige el cliente?</div>
                <div class="d-flex flex-wrap gap-3" id="variantes-ejes-lista"></div>
            </div>

            <div class="table-responsive d-none" id="variantes-tabla-envoltorio">
                <table class="table table-sm align-middle mb-1">
                    <thead class="table-light">
                        <tr>
                            <th>Variante</th>
                            <th style="width: 18%;">Stock *</th>
                            <th style="width: 20%;">Código</th>
                            <th style="width: 20%;">Precio</th>
                            <th class="text-center" style="width: 1%;">Se vende</th>
                        </tr>
                    </thead>
                    <tbody id="variantes-filas"></tbody>
                </table>
                <small class="text-muted d-block">
                    <strong>Stock:</strong> cuántas hay ahora; si lo cambiás, queda anotado en el historial.
                    <strong>Precio</strong> y <strong>código</strong> vacíos: los del producto.
                    Una variante con <strong>Se vende</strong> apagado no aparece en la tienda.
                </small>
            </div>
        </div>
    </div>
</div>

@php
    $variantesParaElFormulario = array_map(function ($v) {
        return [
            'id'      => $v['id'] ?? null,
            'valores' => (array) ($v['valores'] ?? []),
            'codigo'  => $v['codigo'] ?? '',
            'precio'  => isset($v['precio']) && $v['precio'] !== '' && $v['precio'] !== null ? (string) $v['precio'] : '',
            'stock'   => isset($v['stock']) && $v['stock'] !== '' && $v['stock'] !== null ? (string) $v['stock'] : '',
            'activa'  => !array_key_exists('activa', $v) || (bool) $v['activa'],
        ];
    }, $variantesIniciales);
@endphp

@push('scripts')
<script>
(function () {
    var INICIALES = @json($variantesParaElFormulario);

    var interruptor = document.getElementById('usa_variantes');
    var cuerpo      = document.getElementById('variantes-cuerpo');
    var sinCaract   = document.getElementById('variantes-sin-caracteristicas');
    var ejesCaja    = document.getElementById('variantes-ejes');
    var ejesLista   = document.getElementById('variantes-ejes-lista');
    var tablaCaja   = document.getElementById('variantes-tabla-envoltorio');
    var filas       = document.getElementById('variantes-filas');
    var seBorran    = document.getElementById('variantes-se-borran');
    var caracteristicas = document.getElementById('etiquetas-container');
    var stockProducto = document.getElementById('stock-del-producto');
    var stockVariantes = document.getElementById('stock-en-variantes');
    var campoStock  = document.getElementById('stock');   // sólo en el alta

    function normal(texto) {
        return String(texto || '').trim().replace(/\s+/g, ' ').toLowerCase();
    }

    // Lo escrito en cada combinación (id, código, precio, stock, se vende), por clave.
    var porCombinacion = {};
    function clave(valores) {
        return Object.keys(valores).sort().map(function (id) { return id + '=' + normal(valores[id]); }).join('|');
    }
    INICIALES.forEach(function (v) { porCombinacion[clave(v.valores)] = v; });

    // Por cuáles se elige: las de las variantes guardadas; si no hay, se decide al prender.
    var ejesElegidos = {};
    if (INICIALES.length) {
        Object.keys(INICIALES[0].valores).forEach(function (id) { ejesElegidos[id] = true; });
    }

    /** Las características con varios valores cargados en la sección de arriba, en su orden. */
    function disponibles() {
        var lista = [];
        caracteristicas.querySelectorAll('.etiqueta-row[data-varios="1"]').forEach(function (fila) {
            var valores = [];
            fila.querySelectorAll('.etiqueta-valor-chip').forEach(function (c) {
                if (c.value.trim() !== '') valores.push(c.value.trim());
            });
            if (valores.length) lista.push({ id: fila.dataset.etiquetaId, nombre: fila.dataset.nombre, valores: valores });
        });
        return lista;
    }

    function guardarLoEscrito() {
        filas.querySelectorAll('tr[data-clave]').forEach(function (tr) {
            var dato = porCombinacion[tr.dataset.clave] || {};
            dato.codigo = tr.querySelector('.variante-codigo').value;
            dato.precio = tr.querySelector('.variante-precio').value;
            dato.stock  = tr.querySelector('.variante-stock').value;
            dato.activa = tr.querySelector('.variante-activa').checked;
            porCombinacion[tr.dataset.clave] = dato;
        });
    }

    /** Todas las combinaciones: S, M, L × Rojo, Azul = 6. */
    function combinaciones(ejes) {
        var resultado = [{}];
        ejes.forEach(function (eje) {
            var siguiente = [];
            resultado.forEach(function (parcial) {
                eje.valores.forEach(function (valor) {
                    var c = Object.assign({}, parcial);
                    c[eje.id] = valor;
                    siguiente.push(c);
                });
            });
            resultado = siguiente;
        });
        return resultado;
    }

    function campo(tipo, nombre, valor, clase, extra) {
        var input = document.createElement('input');
        input.type = tipo;
        input.name = nombre;
        input.value = valor === null || valor === undefined ? '' : valor;
        input.className = clase;
        Object.keys(extra || {}).forEach(function (k) { input.setAttribute(k, extra[k]); });
        return input;
    }

    function pintar() {
        guardarLoEscrito();

        var activo = interruptor.checked;
        cuerpo.classList.toggle('d-none', !activo);
        if (seBorran) seBorran.classList.toggle('d-none', activo);
        if (stockProducto) stockProducto.classList.toggle('d-none', activo);
        if (stockVariantes) stockVariantes.classList.toggle('d-none', !activo);
        if (campoStock) campoStock.required = !activo;

        var lista = disponibles();
        sinCaract.classList.toggle('d-none', lista.length > 0);
        ejesCaja.classList.toggle('d-none', lista.length === 0);

        // Al prender por primera vez, se elige por todas las que hay.
        var hayElegido = lista.some(function (e) { return ejesElegidos[e.id]; });
        if (activo && !hayElegido) lista.forEach(function (e) { ejesElegidos[e.id] = true; });

        ejesLista.innerHTML = '';
        lista.forEach(function (eje) {
            var envoltorio = document.createElement('div');
            envoltorio.className = 'form-check';
            var check = campo('checkbox', '', '', 'form-check-input', { id: 'variante-eje-' + eje.id });
            check.removeAttribute('name');   // no se manda: lo dicen las variantes
            check.checked = !!ejesElegidos[eje.id];
            check.addEventListener('change', function () {
                ejesElegidos[eje.id] = check.checked;
                pintar();
            });
            var etiqueta = document.createElement('label');
            etiqueta.className = 'form-check-label';
            etiqueta.htmlFor = check.id;
            etiqueta.textContent = eje.nombre + ' (' + eje.valores.join(', ') + ')';
            envoltorio.appendChild(check);
            envoltorio.appendChild(etiqueta);
            ejesLista.appendChild(envoltorio);
        });

        var ejes = lista.filter(function (e) { return ejesElegidos[e.id]; });
        filas.innerHTML = '';
        tablaCaja.classList.toggle('d-none', !activo || ejes.length === 0);
        if (!activo || !ejes.length) return;

        combinaciones(ejes).forEach(function (valores, i) {
            var k = clave(valores);
            var dato = porCombinacion[k] || { activa: true };
            var tr = document.createElement('tr');
            tr.dataset.clave = k;

            var nombre = document.createElement('td');
            nombre.className = 'fw-semibold';
            nombre.textContent = ejes.map(function (e) { return valores[e.id]; }).join(' · ');
            ejes.forEach(function (e) {
                nombre.appendChild(campo('hidden', 'variantes[' + i + '][valores][' + e.id + ']', valores[e.id], ''));
            });
            if (dato.id) nombre.appendChild(campo('hidden', 'variantes[' + i + '][id]', dato.id, ''));
            tr.appendChild(nombre);

            var tdStock = document.createElement('td');
            tdStock.appendChild(campo('number', 'variantes[' + i + '][stock]', dato.stock === undefined || dato.stock === '' ? '0' : dato.stock,
                'form-control form-control-sm variante-stock', { min: 0, step: 1, required: 'required', 'aria-label': 'Stock de ' + nombre.textContent }));
            tr.appendChild(tdStock);

            var tdCodigo = document.createElement('td');
            tdCodigo.appendChild(campo('text', 'variantes[' + i + '][codigo]', dato.codigo, 'form-control form-control-sm variante-codigo',
                { maxlength: 100, placeholder: 'Opcional', title: 'Vacío: el código del producto', 'aria-label': 'Código de ' + nombre.textContent }));
            tr.appendChild(tdCodigo);

            var tdPrecio = document.createElement('td');
            tdPrecio.appendChild(campo('number', 'variantes[' + i + '][precio]', dato.precio, 'form-control form-control-sm variante-precio',
                { min: 0, step: '0.01', placeholder: 'Igual', title: 'Vacío: el precio del producto', 'aria-label': 'Precio de ' + nombre.textContent }));
            tr.appendChild(tdPrecio);

            var tdActiva = document.createElement('td');
            tdActiva.className = 'text-center';
            var envoltorio = document.createElement('div');
            envoltorio.className = 'form-check form-switch d-inline-block m-0';
            envoltorio.appendChild(campo('hidden', 'variantes[' + i + '][activa]', '0', ''));
            var activa = campo('checkbox', 'variantes[' + i + '][activa]', '1', 'form-check-input variante-activa',
                { 'aria-label': 'Se vende ' + nombre.textContent });
            activa.checked = dato.activa !== false;
            envoltorio.appendChild(activa);
            tdActiva.appendChild(envoltorio);
            tr.appendChild(tdActiva);

            filas.appendChild(tr);
        });
    }

    interruptor.addEventListener('change', pintar);

    // Apagarlas borra las variantes: se pide confirmación antes de guardar.
    var teniaVariantes = @json($teniaVariantes);
    var confirmado = false;
    var formulario = interruptor.form;
    if (formulario && teniaVariantes) {
        formulario.addEventListener('submit', function (e) {
            if (confirmado || interruptor.checked) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            window.confirmarAccion({
                texto:   '¿Dejar de usar variantes?',
                detalle: 'Se borran las opciones (talles, colores…) y su stock se suma al producto. Queda anotado en el historial.',
                boton:   'Sí, quitar variantes'
            }, function () {
                confirmado = true;
                if (formulario.requestSubmit) { formulario.requestSubmit(); } else { formulario.submit(); }
            });
        }, true);
    }
    // Las fichitas de Características avisan con "input" al agregar o quitar un valor.
    var espera = null;
    ['input', 'change'].forEach(function (evento) {
        caracteristicas.addEventListener(evento, function (e) {
            if (e.target && e.target.closest && e.target.closest('#variantes-filas')) return;
            clearTimeout(espera);
            espera = setTimeout(pintar, 150);
        });
    });
    document.getElementById('categorias-chips').addEventListener('change', function () { setTimeout(pintar, 50); });

    // Las fichitas se arman al cargar la página (partial de etiquetas): pintar después.
    setTimeout(pintar, 0);
})();
</script>
@endpush
