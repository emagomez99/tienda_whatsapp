@extends('layouts.admin')

@section('title', 'Gestión de Menú')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-list-nested"></i> Menú de la Tienda</h3>
    <a href="{{ route('admin.menus.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo
    </a>
</div>

@if($menus->count() > 0)
    @include('admin.menus.partials.vista-previa')
@endif

<div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Estructura del menú</span>
        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="boton-ayuda-menus"
                data-bs-toggle="collapse" data-bs-target="#ayuda-menus" aria-expanded="false" aria-controls="ayuda-menus">
            <i class="bi bi-question-circle"></i> Cómo funciona
        </button>
    </div>
    @include('admin.menus.partials.ayuda')
    <div class="card-body p-0">
        @if($menus->count() > 0)
            <ul class="menu-arbol" id="menu-tree" data-nivel="0">
                @foreach($menus as $menu)
                    @include('admin.menus.partials.menu-item', [
                        'menu'      => $menu,
                        'nivel'     => 0,
                        'esPrimero' => $loop->first,
                        'esUltimo'  => $loop->last,
                    ])
                @endforeach
            </ul>
        @else
            <div class="text-center py-5 text-muted">
                <i class="bi bi-list-nested display-4"></i>
                <p class="mt-3">No hay elementos en el menú</p>
                <a href="{{ route('admin.menus.create') }}" class="btn btn-outline-primary">
                    <i class="bi bi-plus"></i> Crear primer ítem
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
    #menu-tree, .menu-arbol { list-style: none; margin: 0; padding: 0; }
    /* Sangría con una guía punteada que muestra de quién cuelga cada submenú. */
    .menu-arbol .menu-arbol { margin-left: 1.9rem; border-left: 1px dashed #dee2e6; }

    .menu-fila {
        display: flex; align-items: center; gap: .5rem;
        padding: .55rem .75rem; background: #fff; border-bottom: 1px solid #f1f3f5;
    }
    .menu-fila:hover { background: #f8f9fa; }

    .menu-arrastrar { cursor: grab; color: #ced4da; padding: 0 .1rem; touch-action: none; }
    .menu-fila:hover .menu-arrastrar { color: #6c757d; }
    .menu-plegar { border: 0; background: none; padding: 0; width: 1.1rem; color: #6c757d; line-height: 1; }
    .menu-plegar i { display: inline-block; transition: transform .15s; }
    .menu-plegar-vacio { width: 1.1rem; flex-shrink: 0; }

    .menu-texto { flex: 1 1 auto; min-width: 0; }
    .menu-nombre { font-weight: 600; color: #212529; text-decoration: none; }
    .menu-nombre:hover { text-decoration: underline; }
    .menu-filtro { font-size: .8rem; color: #6c757d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .menu-cuenta {
        flex-shrink: 0; font-size: .75rem; color: #6c757d; white-space: nowrap;
        background: #f1f3f5; border-radius: 1rem; padding: .15rem .6rem;
    }
    .menu-cuenta.vacio { background: #fff3cd; color: #997404; }

    /* Las acciones no compiten con el contenido: aparecen al pasar el mouse por la
       fila (o con el teclado). En pantallas táctiles, siempre. */
    .menu-acciones { display: flex; gap: .25rem; flex-shrink: 0; opacity: 0; transition: opacity .12s; }
    .menu-fila:hover .menu-acciones,
    .menu-fila:focus-within .menu-acciones,
    .menu-acciones:has(.show) { opacity: 1; }
    @media (hover: none) { .menu-acciones { opacity: 1; } }

    .menu-nodo.inactivo > .menu-fila .menu-texto { opacity: .55; }
    .menu-nodo.plegado > .menu-arbol { display: none; }
    .menu-nodo.plegado > .menu-fila .menu-plegar i { transform: rotate(-90deg); }

    /* Mientras se arrastra, las listas vacías se abren un poco para poder soltar
       adentro de un menú que todavía no tiene submenús. */
    body.arrastrando-menu .menu-arbol .menu-arbol:empty { min-height: .75rem; border-left-style: solid; border-left-color: #adb5bd; }
    .menu-fantasma > .menu-fila { background: color-mix(in srgb, var(--admin-color) 30%, white); outline: 1px dashed #adb5bd; }
    /* El menú dentro del que va a quedar lo que se arrastra. */
    .menu-nodo.destino-anidar > .menu-fila { background: color-mix(in srgb, var(--admin-color) 18%, white); box-shadow: inset 3px 0 0 #6c757d; }
    .menu-nodo.destino-anidar > .menu-fila .menu-nombre::after { content: " · soltá para meterlo adentro"; font-weight: 400; font-size: .75rem; color: #6c757d; }
    .menu-fantasma > .menu-arbol { display: none; }

    /* El que se acaba de mover o al que se llegó desde la vista previa. */
    .menu-nodo.recien-movido > .menu-fila,
    .menu-nodo:target > .menu-fila { animation: recien-movido 1.6s ease-out; }
    @keyframes recien-movido { from { background-color: #fff3cd; } to { background-color: transparent; } }

    @media (max-width: 575.98px) {
        .menu-arbol .menu-arbol { margin-left: 1rem; }
        .menu-fila { padding: .5rem; gap: .35rem; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var URL_ORDENAR  = @json(route('admin.menus.reordenar'));
    var NIVEL_MAXIMO = {{ App\Services\ArbolDeMenus::NIVEL_MAXIMO }};
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var plegados = {};   // id → true, para mantenerlos plegados al refrescar
    var antes = null;         // el árbol al empezar a arrastrar
    var cuentasAntes = {};    // y cuántos productos mostraba cada menú

    /**
     * Aviso abajo a la derecha. Con accion ({texto, alHacer}) suma un botón (ej.
     * Deshacer) y no se cierra solo: hay que leerlo y decidir.
     */
    function aviso(texto, tipo, accion) {
        tipo = tipo || 'success';
        var contenedor = document.getElementById('toast-container');
        var toast = document.createElement('div');
        toast.className = 'toast align-items-center border-0 text-bg-' + tipo;
        toast.setAttribute('role', 'status');
        toast.innerHTML = '<div class="d-flex align-items-start"><div class="toast-body"></div>'
            + '<button type="button" class="btn-close me-2 mt-2 ' + (tipo === 'warning' ? '' : 'btn-close-white') + '" data-bs-dismiss="toast"></button></div>';
        var cuerpo = toast.querySelector('.toast-body');
        cuerpo.textContent = texto;
        if (accion) {
            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'btn btn-sm btn-dark d-block mt-2';
            boton.textContent = accion.texto;
            boton.addEventListener('click', function () { bootstrap.Toast.getInstance(toast).hide(); accion.alHacer(); });
            cuerpo.appendChild(boton);
        }
        contenedor.appendChild(toast);
        new bootstrap.Toast(toast, accion ? { autohide: false } : { delay: 3000 }).show();
        toast.addEventListener('hidden.bs.toast', function () { toast.remove(); });
    }

    /** Cuántos productos muestra cada menú según la lista (null si sólo agrupa). */
    function cuentas() {
        var mapa = {};
        document.querySelectorAll('#menu-tree .menu-nodo').forEach(function (nodo) {
            var cuenta = nodo.querySelector(':scope > .menu-fila .menu-cuenta');
            mapa[nodo.dataset.id] = cuenta ? parseInt(cuenta.textContent.replace(/\D/g, ''), 10) || 0 : null;
        });
        return mapa;
    }

    // ── Plegar ──────────────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var boton = e.target.closest('.menu-plegar');
        if (!boton) return;
        var nodo = boton.closest('.menu-nodo');
        var plegado = nodo.classList.toggle('plegado');
        boton.setAttribute('aria-expanded', plegado ? 'false' : 'true');
        plegados[nodo.dataset.id] = plegado;
    });

    function aplicarPlegados() {
        Object.keys(plegados).forEach(function (id) {
            var nodo = document.getElementById('menu-' + id);
            if (nodo && plegados[id] && nodo.querySelector(':scope > .menu-fila .menu-plegar')) {
                nodo.classList.add('plegado');
            }
        });
    }

    // ── Arrastrar ───────────────────────────────────────────────────────────

    /** Cuántos niveles tiene un menú hacia adentro (0 si no tiene submenús). */
    function altura(nodo) {
        var max = 0;
        nodo.querySelectorAll(':scope > .menu-arbol > .menu-nodo').forEach(function (hijo) {
            max = Math.max(max, 1 + altura(hijo));
        });
        return max;
    }

    function posicionX(evento) {
        if (evento.clientX !== undefined) return evento.clientX;
        return evento.touches && evento.touches[0] ? evento.touches[0].clientX : 0;
    }

    /** Si el puntero está a la derecha del comienzo del nombre de ese menú. */
    function aLaDerechaDe(nodo, x) {
        var nombre = nodo.querySelector(':scope > .menu-fila .menu-nombre');
        return !!nombre && x >= nombre.getBoundingClientRect().left + 24;
    }

    /** Resalta el menú dentro del que va a quedar lo que se arrastra. */
    function marcarDestino(nodo) {
        document.querySelectorAll('.menu-nodo.destino-anidar').forEach(function (n) {
            if (n !== nodo) n.classList.remove('destino-anidar');
        });
        if (nodo) nodo.classList.add('destino-anidar');
    }

    /** El árbol tal como quedó en pantalla: padre y posición de cada menú. */
    function arbol() {
        var items = [];
        document.querySelectorAll('#menu-tree .menu-nodo').forEach(function (nodo) {
            var padre = nodo.parentElement.closest('.menu-nodo');
            items.push({
                id: parseInt(nodo.dataset.id, 10),
                parent_id: padre ? parseInt(padre.dataset.id, 10) : null,
                orden: Array.prototype.indexOf.call(nodo.parentElement.children, nodo)
            });
        });
        return items;
    }

    /**
     * Después de guardar se vuelve a pedir la página y se reemplazan el árbol y la
     * vista previa: al cambiar de padre cambian lo heredado, las cantidades de
     * productos y quién puede subir o bajar.
     */
    function refrescar() {
        return fetch(window.location.pathname, { credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var nueva = new DOMParser().parseFromString(html, 'text/html');
                ['menu-tree', 'vista-previa-menu'].forEach(function (id) {
                    var actual = document.getElementById(id), reemplazo = nueva.getElementById(id);
                    if (actual && reemplazo) actual.replaceWith(reemplazo);
                });
                aplicarPlegados();
                iniciar();
            });
    }

    function enviar(items) {
        return fetch(URL_ORDENAR, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ items: items })
        });
    }

    /**
     * Si al moverlo, el menú (o uno de sus submenús) pasó a mostrar 0 productos, se
     * explica por qué: dentro de otro menú hereda sus filtros. Sin este aviso parece
     * que el menú se rompió.
     */
    function avisarSiQuedoVacio(movidoId, arbolAnterior, antesDeMover) {
        var movido = document.getElementById('menu-' + movidoId);
        if (!movido) return false;

        var nodos = [movido].concat(Array.prototype.slice.call(movido.querySelectorAll('.menu-nodo')));
        var vacios = nodos.filter(function (nodo) {
            var despues = cuentas()[nodo.dataset.id];
            return antesDeMover[nodo.dataset.id] > 0 && despues === 0;
        }).map(function (nodo) { return '«' + nodo.dataset.nombre + '»'; });

        if (!vacios.length) return false;

        // Los filtros que hereda: los de los menús de arriba que no sólo agrupan.
        var filtros = [], padre = movido.parentElement.closest('.menu-nodo'), nombrePadre = padre ? padre.dataset.nombre : '';
        while (padre) {
            if (padre.querySelector(':scope > .menu-fila .menu-cuenta')) {
                filtros.unshift(padre.querySelector(':scope > .menu-fila .menu-filtro').textContent.trim());
            }
            padre = padre.parentElement.closest('.menu-nodo');
        }

        aviso(
            vacios.join(', ') + (vacios.length === 1 ? ' quedó' : ' quedaron') + ' sin productos: dentro de «' + nombrePadre
                + '» también se filtra por ' + filtros.join(' y ') + '.',
            'warning',
            {
                texto: 'Deshacer',
                alHacer: function () {
                    enviar(JSON.parse(arbolAnterior)).then(refrescar).then(function () { aviso('Se deshizo el cambio.'); });
                }
            }
        );
        return true;
    }

    function guardar(movido, arbolAnterior, antesDeMover) {
        enviar(arbol())
            .then(function (r) {
                return r.json().then(function (datos) { return { ok: r.ok, datos: datos }; });
            })
            .then(function (respuesta) {
                if (!respuesta.ok) {
                    var errores = respuesta.datos.errors || {};
                    var mensaje = (errores.items && errores.items[0]) || 'No se pudo guardar el orden.';
                    aviso(mensaje, 'danger');
                    return refrescar();
                }
                return refrescar().then(function () {
                    if (!avisarSiQuedoVacio(movido.dataset.id, arbolAnterior, antesDeMover)) {
                        aviso('«' + movido.dataset.nombre + '» quedó en su nuevo lugar.');
                    }
                });
            })
            .catch(function () {
                aviso('No se pudo guardar el orden. Revisá la conexión.', 'danger');
                refrescar();
            });
    }

    function iniciar() {
        document.querySelectorAll('#menu-tree, #menu-tree .menu-arbol').forEach(function (lista) {
            if (lista._sortable) return;
            lista._sortable = Sortable.create(lista, {
                group: 'menus',
                handle: '.menu-arrastrar',
                animation: 150,
                // Arrastre propio de la librería y no el nativo del navegador: se ve
                // igual en todos y sigue al mouse con la fila entera.
                forceFallback: true,
                fallbackClass: 'menu-arrastrado',
                fallbackOnBody: true,
                swapThreshold: .65,
                emptyInsertThreshold: 6,
                delay: 150,
                delayOnTouchOnly: true,     // en el celular, mantener apretado para arrastrar
                ghostClass: 'menu-fantasma',
                onStart: function () {
                    document.body.classList.add('arrastrando-menu');
                    antes = JSON.stringify(arbol());
                    cuentasAntes = cuentas();
                },
                // No dejar soltar donde quedaría más profundo de lo permitido.
                onMove: function (evt, original) {
                    var x = posicionX(original);
                    marcarDestino(null);

                    // Sobre la fila de un menú y con el mouse a su derecha: meterlo
                    // adentro, aunque todavía no tenga submenús. Sortable sólo sabe
                    // soltar en listas, y la de un menú sin submenús es una franja de
                    // pocos píxeles imposible de acertar: se resuelve acá moviéndolo a
                    // esa lista a mano.
                    var fila = evt.related;
                    if (fila && fila !== evt.dragged && fila.classList.contains('menu-nodo') && aLaDerechaDe(fila, x)) {
                        var lista = fila.querySelector(':scope > .menu-arbol');
                        if (lista && parseInt(lista.dataset.nivel, 10) + altura(evt.dragged) <= NIVEL_MAXIMO) {
                            marcarDestino(fila);
                            if (evt.dragged.parentElement !== lista) lista.insertBefore(evt.dragged, lista.firstChild);
                            return false;
                        }
                    }

                    var nivelDestino = parseInt(evt.to.dataset.nivel, 10);
                    if (nivelDestino + altura(evt.dragged) > NIVEL_MAXIMO) return false;

                    // Al revés: entrar en la lista vacía de un menú arrastrando en línea
                    // recta (para reordenar) no vale; si no, se metía sin querer en el
                    // de arriba.
                    var vacia = !Array.prototype.some.call(evt.to.children, function (hijo) {
                        return hijo !== evt.dragged && hijo.classList.contains('menu-nodo');
                    });
                    var destino = evt.to.closest('.menu-nodo');
                    if (vacia && destino && !aLaDerechaDe(destino, x)) return false;

                    if (vacia && destino) marcarDestino(destino);
                    return true;
                },
                // Se compara el árbol entero y no los índices del evento: al pasar a
                // una lista anidada, Sortable puede informar el mismo índice aunque
                // el menú haya cambiado de padre.
                onEnd: function (evt) {
                    document.body.classList.remove('arrastrando-menu');
                    marcarDestino(null);
                    if (JSON.stringify(arbol()) === antes) return;
                    guardar(evt.item, antes, cuentasAntes);
                }
            });
        });
    }

    if (document.getElementById('menu-tree') && window.Sortable) iniciar();

    @if(session('menu_movido'))
        // Después de subir o bajar (desde ⋯), volver a la fila movida.
        var fila = document.getElementById('menu-{{ (int) session('menu_movido') }}');
        if (fila) fila.scrollIntoView({ block: 'center' });
    @endif
})();
</script>
@endpush
