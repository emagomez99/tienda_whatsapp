{{--
    Variantes del producto, como en Tiendanube: "¿Tu producto tiene color, talle o
    sabor? Creá propiedades." → Agregar → panel a la derecha donde se elige Color,
    Talle o una propiedad nueva y se tildan los valores (talles y colores ya vienen
    listos). Con eso se arma sola una fila por combinación, para el stock (y si hace
    falta un precio o un código distinto). Ver App\Services\VariantesDeProducto.

    No hace falta pasar por Características: la propiedad se crea sola con su nombre.
    Si se le quitan todas las propiedades, deja de tener variantes (pide confirmación
    y el stock vuelve al producto).

    Recibe $variantesIniciales ([{id, valores: {Talle: M}, codigo, precio, stock,
    activa}]) y $etiquetas (para ofrecer las propiedades que la tienda ya usa).
--}}
@php
    $teniaVariantes = isset($producto) && $producto->variantes->isNotEmpty();
    // Un producto que ya tiene stock y pasa a tener variantes: ese stock se reemplaza.
    $stockSinVariantes = (isset($producto) && !$teniaVariantes) ? (int) $producto->stock : 0;

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

    // Las propiedades que la tienda ya usa en otros productos (Sabor, Material…).
    $propiedadesDeLaTienda = $etiquetas->filter(function ($e) { return $e->varios_valores; })
        ->map(function ($e) {
            return ['id' => $e->id, 'nombre' => $e->nombre, 'url' => route('admin.etiquetas.valores', $e)];
        })->values();
@endphp

<div class="card mb-4" id="card-variantes">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-grid-3x3-gap"></i> Variantes
            @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.variantes'), 'lugar' => 'right', 'grande' => true])
        </h5>
        <button type="button" class="btn btn-link text-decoration-none p-0" id="variantes-agregar">
            <i class="bi bi-plus-circle"></i> Agregar
        </button>
    </div>
    <div class="card-body">
        <input type="hidden" name="variantes_enviadas" value="1">

        <p class="text-muted mb-0" id="variantes-vacio">¿Tu producto tiene color, talle o sabor? Creá propiedades.</p>

        @if($stockSinVariantes > 0)
            <div class="alert alert-info small py-2 d-none" id="variantes-stock-anterior">
                <i class="bi bi-info-circle"></i> Hoy tiene <strong>{{ $stockSinVariantes }}</strong> en stock. Con variantes, el stock es el de cada una:
                poné abajo cuántas hay de cada una. Al guardar, los {{ $stockSinVariantes }} se reemplazan por esa suma (queda anotado en el historial).
            </div>
        @endif
        @if($teniaVariantes)
            <div class="alert alert-warning small py-2 d-none" id="variantes-se-borran">
                <i class="bi bi-exclamation-triangle"></i> Sin propiedades, al guardar se borran las variantes y su stock se suma al producto ({{ $producto->stock }} en total).
            </div>
        @endif

        @error('variantes')
            <div class="text-danger small mb-2"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
        @enderror

        <div id="variantes-propiedades" class="d-flex flex-column gap-2 mb-3"></div>

        {{-- En el celular cada variante es una ficha (nombre arriba y sus datos abajo,
             cada uno con su rótulo): como tabla, los casilleros quedaban diminutos y
             había que deslizarla. Ver .tabla-variantes en admin.css. --}}
        <div class="table-responsive d-none" id="variantes-tabla-envoltorio">
            <table class="table table-sm align-middle mb-1 tabla-variantes">
                <thead class="table-light">
                    <tr>
                        <th>Variante</th>
                        <th style="width: 18%;">Stock *</th>
                        <th style="width: 20%;">Precio</th>
                        <th style="width: 20%;">Código</th>
                        <th class="text-center" style="width: 1%;">Se vende</th>
                    </tr>
                </thead>
                <tbody id="variantes-filas"></tbody>
            </table>
            <small class="text-muted d-block">
                <strong>Precio</strong> y <strong>código</strong> vacíos: los del producto.
                Con <strong>Se vende</strong> apagado, esa no aparece en la tienda.
            </small>
        </div>
    </div>
</div>

{{-- Panel de la derecha: crear o editar una propiedad (como "Crear propiedad" de Tiendanube). --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="panel-propiedad" aria-labelledby="panel-propiedad-titulo" style="width: 420px; max-width: 100%;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="panel-propiedad-titulo">Crear propiedad</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body">
        <select class="form-select mb-3" id="propiedad-tipo" aria-label="Propiedad">
            <option value="">Seleccionar</option>
            <option value="Color">Color</option>
            <option value="Talle">Talle</option>
            @foreach($propiedadesDeLaTienda as $p)
                @if(!in_array(mb_strtolower($p['nombre']), ['color', 'talle'], true))
                    <option value="{{ $p['nombre'] }}" data-url="{{ $p['url'] }}">{{ $p['nombre'] }}</option>
                @endif
            @endforeach
            <option value="__nueva">+ Nueva propiedad</option>
        </select>

        <div id="propiedad-nombre-caja" class="mb-3 d-none">
            <label for="propiedad-nombre" class="form-label">Nombre</label>
            <input type="text" class="form-control" id="propiedad-nombre" maxlength="60" placeholder="Ej.: Sabor, Material, Aroma">
        </div>

        <div id="propiedad-valores-caja" class="d-none">
            <p class="small text-muted mb-2" id="propiedad-indicacion">Seleccioná todos los valores que vas a ofrecer para este producto.</p>
            <div class="input-group input-group-sm mb-3">
                <input type="text" class="form-control" id="propiedad-nuevo-valor" maxlength="60" placeholder="Agregar otro…" aria-label="Agregar otro valor">
                <button type="button" class="btn btn-outline-primary" id="propiedad-agregar-valor"><i class="bi bi-plus-circle"></i> Agregar</button>
            </div>
            <div id="propiedad-grupos"></div>
        </div>
    </div>
    <div class="offcanvas-footer border-top p-3 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">Cancelar</button>
        <button type="button" class="btn btn-primary" id="propiedad-crear">Crear</button>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var INICIALES = @json($variantesParaElFormulario);
    var MAXIMO_PROPIEDADES = 3;

    // Listos para tildar, como en Tiendanube.
    var SUGERIDOS = {
        talle: [
            { titulo: 'Adultos', valores: ['XS', 'S', 'M', 'L', 'XL', 'XXL'] },
            { titulo: 'Niños', valores: ['2', '4', '6', '8', '10', '12', '14'] },
            { titulo: 'Calzado', valores: ['34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'] }
        ],
        color: [
            { titulo: 'Colores sugeridos', valores: ['Amarillo', 'Azul', 'Beige', 'Blanco', 'Bordó', 'Celeste', 'Fucsia', 'Gris', 'Marrón', 'Naranja', 'Negro', 'Plata', 'Rojo', 'Rosa', 'Verde', 'Violeta'] }
        ]
    };

    var vacio       = document.getElementById('variantes-vacio');
    var lista       = document.getElementById('variantes-propiedades');
    var tablaCaja   = document.getElementById('variantes-tabla-envoltorio');
    var filas       = document.getElementById('variantes-filas');
    var botonAgregar = document.getElementById('variantes-agregar');
    var stockAnterior = document.getElementById('variantes-stock-anterior');
    var seBorran    = document.getElementById('variantes-se-borran');
    var stockProducto = document.getElementById('stock-del-producto');
    var stockVariantes = document.getElementById('stock-en-variantes');
    var campoStock  = document.getElementById('stock');   // sólo en el alta

    function normal(texto) {
        return String(texto || '').trim().replace(/\s+/g, ' ').toLowerCase();
    }
    function limpiarNombre(texto) {
        return String(texto || '').replace(/[\[\]]/g, '').trim().replace(/\s+/g, ' ');
    }

    // Las propiedades del producto, en orden: [{nombre: 'Talle', valores: ['S', 'M']}].
    var propiedades = [];
    INICIALES.forEach(function (v) {
        Object.keys(v.valores).forEach(function (nombre) {
            var p = propiedades.filter(function (x) { return normal(x.nombre) === normal(nombre); })[0];
            if (!p) { p = { nombre: nombre, valores: [] }; propiedades.push(p); }
            if (p.valores.map(normal).indexOf(normal(v.valores[nombre])) === -1) p.valores.push(v.valores[nombre]);
        });
    });

    // Lo escrito en cada combinación (id, stock, precio, código, se vende), por clave.
    var porCombinacion = {};
    function clave(valores) {
        return Object.keys(valores).map(function (n) { return normal(n) + '=' + normal(valores[n]); }).sort().join('|');
    }
    INICIALES.forEach(function (v) { porCombinacion[clave(v.valores)] = v; });

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

    function combinaciones() {
        var resultado = [{}];
        propiedades.forEach(function (p) {
            var siguiente = [];
            resultado.forEach(function (parcial) {
                p.valores.forEach(function (valor) {
                    var c = Object.assign({}, parcial);
                    c[p.nombre] = valor;
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
        if (nombre) input.name = nombre;
        input.value = valor === null || valor === undefined ? '' : valor;
        input.className = clase;
        Object.keys(extra || {}).forEach(function (k) { input.setAttribute(k, extra[k]); });
        return input;
    }

    // Las filas que se ven ahora, con lo escrito: [{clave, valores, dato}].
    function filasActuales() {
        return Array.prototype.map.call(filas.querySelectorAll('tr[data-clave]'), function (tr) {
            return { clave: tr.dataset.clave, valores: JSON.parse(tr.dataset.valores), dato: porCombinacion[tr.dataset.clave] || {} };
        });
    }

    function contiene(grande, chico) {
        return Object.keys(chico).every(function (p) {
            return Object.prototype.hasOwnProperty.call(grande, p) && normal(grande[p]) === normal(chico[p]);
        });
    }

    function stockDe(dato) {
        var n = parseInt(dato.stock, 10);
        return isNaN(n) ? 0 : n;
    }

    /**
     * Una combinación nueva que sale de las que había, para no perder stock:
     * - Se quitó una propiedad (Color): "S · Azul" y "S · Blanco" se juntan en "S",
     *   que suma su stock.
     * - Se agregó una propiedad: "S" pasa entera (stock, precio, código) a la primera
     *   de sus combinaciones ("S · Azul"); las demás empiezan en 0.
     * Anota en `heredadas` las filas anteriores que pasaron lo suyo a otra.
     */
    function heredar(valores, anteriores, heredadas) {
        var n = Object.keys(valores).length;

        var juntadas = anteriores.filter(function (a) { return Object.keys(a.valores).length > n && contiene(a.valores, valores); });
        if (juntadas.length) {
            var stock = 0, precios = {}, activa = false;
            juntadas.forEach(function (a) {
                stock += stockDe(a.dato);
                precios[a.dato.precio || ''] = true;
                activa = activa || a.dato.activa !== false;
                heredadas[a.clave] = true;
            });
            var conId = juntadas.filter(function (a) { return a.dato.id; })[0];
            var unPrecio = Object.keys(precios);
            return {
                id: conId ? conId.dato.id : null,
                stock: String(stock),
                precio: unPrecio.length === 1 ? unPrecio[0] : '',
                codigo: juntadas.length === 1 ? (juntadas[0].dato.codigo || '') : '',
                activa: activa
            };
        }

        var madre = anteriores.filter(function (a) { return Object.keys(a.valores).length < n && contiene(valores, a.valores); })[0];
        if (madre) {
            if (!heredadas[madre.clave]) {
                heredadas[madre.clave] = true;
                return Object.assign({}, madre.dato);
            }
            return { activa: madre.dato.activa !== false, precio: madre.dato.precio || '' };
        }
        return null;
    }

    function pintar() {
        guardarLoEscrito();
        var anteriores = filasActuales();
        var heredadas = {};
        var nuevasClaves = {};
        var hay = propiedades.length > 0;

        vacio.classList.toggle('d-none', hay);
        botonAgregar.classList.toggle('d-none', propiedades.length >= MAXIMO_PROPIEDADES);
        if (stockAnterior) stockAnterior.classList.toggle('d-none', !hay);
        if (seBorran) seBorran.classList.toggle('d-none', hay);
        if (stockProducto) stockProducto.classList.toggle('d-none', hay);
        if (stockVariantes) stockVariantes.classList.toggle('d-none', !hay);
        if (campoStock) campoStock.required = !hay;

        // Las propiedades: "Talle  S, M, L   Editar  Quitar".
        lista.innerHTML = '';
        propiedades.forEach(function (p, i) {
            var fila = document.createElement('div');
            fila.className = 'd-flex align-items-center gap-2 border rounded px-3 py-2';
            var texto = document.createElement('div');
            texto.className = 'flex-grow-1';
            var nombre = document.createElement('strong');
            nombre.textContent = p.nombre;
            texto.appendChild(nombre);
            texto.appendChild(document.createTextNode('  ' + p.valores.join(', ')));
            fila.appendChild(texto);

            var editar = document.createElement('button');
            editar.type = 'button';
            editar.className = 'btn btn-sm btn-outline-secondary';
            editar.innerHTML = '<i class="bi bi-pencil"></i><span class="d-none d-sm-inline"> Editar</span>';
            editar.addEventListener('click', function () { abrirPanel(i); });
            fila.appendChild(editar);

            var quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.className = 'btn btn-sm btn-outline-danger';
            quitar.title = 'Quitar ' + p.nombre;
            quitar.setAttribute('aria-label', 'Quitar ' + p.nombre);
            quitar.innerHTML = '<i class="bi bi-x-lg"></i>';
            quitar.addEventListener('click', function () {
                propiedades.splice(i, 1);
                pintar();
            });
            fila.appendChild(quitar);
            lista.appendChild(fila);
        });

        // Una fila por combinación.
        filas.innerHTML = '';
        tablaCaja.classList.toggle('d-none', !hay);
        if (hay) {
            combinaciones().forEach(function (valores, i) {
                var k = clave(valores);
                var dato = porCombinacion[k] || heredar(valores, anteriores, heredadas) || { activa: true };
                porCombinacion[k] = dato;
                nuevasClaves[k] = true;
                var tr = document.createElement('tr');
                tr.dataset.clave = k;
                tr.dataset.valores = JSON.stringify(valores);

                var td = document.createElement('td');
                td.className = 'fw-semibold';
                td.textContent = propiedades.map(function (p) { return valores[p.nombre]; }).join(' · ');
                propiedades.forEach(function (p) {
                    td.appendChild(campo('hidden', 'variantes[' + i + '][valores][' + p.nombre + ']', valores[p.nombre], ''));
                });
                if (dato.id) td.appendChild(campo('hidden', 'variantes[' + i + '][id]', dato.id, ''));
                tr.appendChild(td);
                var nombre = td.textContent;

                var tdStock = document.createElement('td');
                tdStock.dataset.rotulo = 'Stock *';
                tdStock.appendChild(campo('number', 'variantes[' + i + '][stock]', dato.stock === undefined || dato.stock === '' ? '0' : dato.stock,
                    'form-control form-control-sm variante-stock', { min: 0, step: 1, required: 'required', 'aria-label': 'Stock de ' + nombre }));
                tr.appendChild(tdStock);

                var tdPrecio = document.createElement('td');
                tdPrecio.dataset.rotulo = 'Precio';
                tdPrecio.appendChild(campo('number', 'variantes[' + i + '][precio]', dato.precio, 'form-control form-control-sm variante-precio',
                    { min: 0, step: '0.01', placeholder: 'Igual', title: 'Vacío: el precio del producto', 'aria-label': 'Precio de ' + nombre }));
                tr.appendChild(tdPrecio);

                var tdCodigo = document.createElement('td');
                tdCodigo.dataset.rotulo = 'Código';
                tdCodigo.appendChild(campo('text', 'variantes[' + i + '][codigo]', dato.codigo, 'form-control form-control-sm variante-codigo',
                    { maxlength: 100, placeholder: 'Opcional', title: 'Vacío: el código del producto', 'aria-label': 'Código de ' + nombre }));
                tr.appendChild(tdCodigo);

                var tdActiva = document.createElement('td');
                tdActiva.className = 'text-center';
                tdActiva.dataset.rotulo = 'Se vende';
                var envoltorio = document.createElement('div');
                envoltorio.className = 'form-check form-switch d-inline-block m-0';
                envoltorio.appendChild(campo('hidden', 'variantes[' + i + '][activa]', '0', ''));
                var activa = campo('checkbox', 'variantes[' + i + '][activa]', '1', 'form-check-input variante-activa', { 'aria-label': 'Se vende ' + nombre });
                activa.checked = dato.activa !== false;
                envoltorio.appendChild(activa);
                tdActiva.appendChild(envoltorio);
                tr.appendChild(tdActiva);

                filas.appendChild(tr);
            });

            // Lo que pasó a otra fila ya no se recupera al volver a tildarla: el stock
            // se contaría dos veces.
            Object.keys(heredadas).forEach(function (k) {
                if (!nuevasClaves[k]) delete porCombinacion[k];
            });

            // Una fila que se va con stock (se destildó M): avisar antes de guardar.
            var seVan = anteriores.filter(function (a) {
                return stockDe(a.dato) > 0 && !nuevasClaves[a.clave] && !heredadas[a.clave];
            });
            if (seVan.length && typeof showToast === 'function') {
                showToast('Al guardar, se quita del stock: ' + seVan.map(function (a) {
                    return Object.keys(a.valores).map(function (n) { return a.valores[n]; }).join(' · ') + ' (' + stockDe(a.dato) + ')';
                }).join(', ') + '.', 'warning');
            }
        }

        // Características no vuelve a pedir lo que se carga acá (ej. Talle).
        document.dispatchEvent(new CustomEvent('propiedades-de-variantes', {
            detail: propiedades.map(function (p) { return normal(p.nombre); })
        }));
        // Para el aviso de "Cambios sin guardar".
        lista.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // ── Panel "Crear propiedad" ─────────────────────────────────────────────
    var panel     = document.getElementById('panel-propiedad');
    var titulo    = document.getElementById('panel-propiedad-titulo');
    var tipo      = document.getElementById('propiedad-tipo');
    var cajaNombre = document.getElementById('propiedad-nombre-caja');
    var campoNombre = document.getElementById('propiedad-nombre');
    var cajaValores = document.getElementById('propiedad-valores-caja');
    var indicacion = document.getElementById('propiedad-indicacion');
    var grupos    = document.getElementById('propiedad-grupos');
    var nuevoValor = document.getElementById('propiedad-nuevo-valor');
    var botonCrear = document.getElementById('propiedad-crear');
    var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(panel);
    var editando  = null;   // índice de la propiedad que se edita, o null al crear

    function casilla(contenedor, valor, marcada) {
        var id = 'propiedad-valor-' + Math.random().toString(36).slice(2);
        var envoltorio = document.createElement('div');
        envoltorio.className = 'form-check py-1 border-bottom';
        var check = campo('checkbox', '', valor, 'form-check-input propiedad-valor', { id: id });
        check.checked = !!marcada;
        var etiqueta = document.createElement('label');
        etiqueta.className = 'form-check-label w-100';
        etiqueta.htmlFor = id;
        etiqueta.textContent = valor;
        envoltorio.appendChild(check);
        envoltorio.appendChild(etiqueta);
        contenedor.appendChild(envoltorio);
        return check;
    }

    function grupo(tituloGrupo, valores, marcados) {
        var caja = document.createElement('div');
        caja.className = 'mb-3';
        var cabeza = document.createElement('div');
        cabeza.className = 'd-flex justify-content-between align-items-center mb-1';
        var nombre = document.createElement('strong');
        nombre.className = 'small';
        nombre.textContent = tituloGrupo;
        cabeza.appendChild(nombre);
        var todos = document.createElement('button');
        todos.type = 'button';
        todos.className = 'btn btn-link btn-sm p-0 text-decoration-none';
        todos.textContent = 'Seleccionar todos';
        cabeza.appendChild(todos);
        caja.appendChild(cabeza);
        var checks = valores.map(function (v) { return casilla(caja, v, marcados.indexOf(normal(v)) !== -1); });
        todos.addEventListener('click', function () { checks.forEach(function (c) { c.checked = true; }); });
        grupos.appendChild(caja);
        return caja;
    }

    function valoresMarcados() {
        var marcadas = Array.prototype.filter.call(grupos.querySelectorAll('.propiedad-valor'), function (c) { return c.checked; });
        // Los escritos a mano van al final, como al editar: "S, M, L, Único" y no "Único, S, M, L".
        var escritas = marcadas.filter(function (c) { return c.closest('.grupo-nuevos'); });
        return marcadas.filter(function (c) { return escritas.indexOf(c) === -1; }).concat(escritas)
            .map(function (c) { return c.value; });
    }

    function yaListado(valor) {
        return Array.prototype.some.call(grupos.querySelectorAll('.propiedad-valor'), function (c) { return normal(c.value) === normal(valor); });
    }

    /** Arma la lista de casillas para el tipo elegido, con lo ya cargado tildado. */
    function armarValores(actuales) {
        grupos.innerHTML = '';
        var marcados = actuales.map(normal);
        var elegido = tipo.value;
        cajaNombre.classList.toggle('d-none', elegido !== '__nueva');
        cajaValores.classList.toggle('d-none', elegido === '');
        if (elegido === '') return;

        var sugeridos = SUGERIDOS[normal(elegido)];
        indicacion.textContent = normal(elegido) === 'talle'
            ? 'Seleccioná todos los talles que vas a ofrecer para este producto.'
            : normal(elegido) === 'color'
                ? 'Seleccioná todos los colores que vas a ofrecer para este producto.'
                : 'Agregá los valores de esta propiedad (por ejemplo, para Sabor: Frutilla, Limón).';
        nuevoValor.placeholder = normal(elegido) === 'talle' ? 'Agregar nuevo talle…' : (normal(elegido) === 'color' ? 'Agregar nuevo color…' : 'Agregar valor…');

        if (sugeridos) {
            sugeridos.forEach(function (g) { grupo(g.titulo, g.valores, marcados); });
        }
        // Lo que ya tenía y no está entre los sugeridos (un talle "Único", un color "Coral").
        var propios = actuales.filter(function (v) { return !yaListado(v); });
        if (propios.length) grupo('Tuyos', propios, marcados);

        // Una propiedad que la tienda ya usa: sus valores de otros productos.
        var opcion = tipo.options[tipo.selectedIndex];
        if (opcion && opcion.dataset.url) {
            fetch(opcion.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (datos) {
                    var usados = (Array.isArray(datos) ? datos : []).map(function (d) { return typeof d === 'string' ? d : d.valor; })
                        .filter(function (v) { return v && !yaListado(v); });
                    if (usados.length) grupo('Usados en otros productos', usados, marcados);
                })
                .catch(function () {});
        }
    }

    function agregarValorEscrito() {
        var valor = limpiarNombre(nuevoValor.value);
        if (valor === '') return;
        if (!yaListado(valor)) {
            var caja = grupos.querySelector('.grupo-nuevos');
            if (!caja) {
                caja = document.createElement('div');
                caja.className = 'mb-3 grupo-nuevos';
                grupos.insertBefore(caja, grupos.firstChild);
            }
            casilla(caja, valor, true);
        } else {
            Array.prototype.forEach.call(grupos.querySelectorAll('.propiedad-valor'), function (c) {
                if (normal(c.value) === normal(valor)) c.checked = true;
            });
        }
        nuevoValor.value = '';
        nuevoValor.focus();
    }

    function abrirPanel(indice) {
        editando = indice;
        var actual = indice === null ? null : propiedades[indice];
        titulo.textContent = actual ? 'Editar ' + actual.nombre : 'Crear propiedad';
        botonCrear.textContent = actual ? 'Listo' : 'Crear';

        // Al crear, no se ofrece una que ya tiene el producto.
        var usadas = propiedades.map(function (p) { return normal(p.nombre); });
        Array.prototype.forEach.call(tipo.options, function (o) {
            o.hidden = !actual && o.value !== '' && o.value !== '__nueva' && usadas.indexOf(normal(o.value)) !== -1;
        });

        if (actual) {
            var existe = Array.prototype.some.call(tipo.options, function (o) { return normal(o.value) === normal(actual.nombre); });
            if (!existe) {
                var o = document.createElement('option');
                o.value = actual.nombre;
                o.textContent = actual.nombre;
                tipo.insertBefore(o, tipo.lastElementChild);
            }
            tipo.value = Array.prototype.filter.call(tipo.options, function (o) { return normal(o.value) === normal(actual.nombre); })[0].value;
        } else {
            tipo.value = '';
        }
        tipo.disabled = !!actual;
        campoNombre.value = '';
        armarValores(actual ? actual.valores : []);
        offcanvas.show();
    }

    botonAgregar.addEventListener('click', function () { abrirPanel(null); });
    tipo.addEventListener('change', function () { armarValores([]); if (tipo.value === '__nueva') campoNombre.focus(); });
    document.getElementById('propiedad-agregar-valor').addEventListener('click', agregarValorEscrito);
    nuevoValor.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); agregarValorEscrito(); }
    });
    campoNombre.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); nuevoValor.focus(); }
    });

    botonCrear.addEventListener('click', function () {
        if (nuevoValor.value.trim() !== '') agregarValorEscrito();   // lo escrito sin apretar Agregar

        var nombre = tipo.value === '__nueva' ? limpiarNombre(campoNombre.value) : tipo.value;
        var valores = valoresMarcados();
        if (!nombre) { showToast('Escribí el nombre de la propiedad (por ejemplo, Sabor).', 'warning'); campoNombre.focus(); return; }
        if (!valores.length) { showToast('Elegí o agregá al menos un valor.', 'warning'); nuevoValor.focus(); return; }

        var repetida = propiedades.some(function (p, i) { return i !== editando && normal(p.nombre) === normal(nombre); });
        if (repetida) { showToast('El producto ya tiene «' + nombre + '».', 'warning'); return; }

        if (editando === null) {
            propiedades.push({ nombre: nombre, valores: valores });
        } else {
            propiedades[editando].valores = valores;
        }
        offcanvas.hide();
        pintar();
    });

    // Sin propiedades se borran las variantes: se pide confirmación antes de guardar.
    var teniaVariantes = @json($teniaVariantes);
    var confirmado = false;
    var formulario = document.getElementById('card-variantes').closest('form');
    if (formulario && teniaVariantes) {
        formulario.addEventListener('submit', function (e) {
            if (confirmado || propiedades.length) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            window.confirmarAccion({
                texto:   '¿Dejar de usar variantes?',
                detalle: 'Se borran las variantes y su stock se suma al producto. Queda anotado en el historial.',
                boton:   'Sí, quitar variantes'
            }, function () {
                confirmado = true;
                if (formulario.requestSubmit) { formulario.requestSubmit(); } else { formulario.submit(); }
            });
        }, true);
    }

    pintar();
})();
</script>
@endpush
