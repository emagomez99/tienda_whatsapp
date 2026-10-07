{{--
    Árbol ordenable con arrastrar y soltar, compartido por Menús y Categorías.

    Arrastrar desde ⋮⋮ reordena; llevarlo a la derecha, sobre la fila de otro, lo
    mete adentro (aunque no tenga hijos todavía). Respeta un nivel máximo, guarda
    solo al soltar (ver los servicios Arbol*) y refresca la página por partes.

    Parámetros:
      $arbolId        id del <ul> raíz (ej. "menu-tree")
      $prefijoNodo    prefijo del id de cada <li> (ej. "menu-")
      $urlOrdenar     POST con items [{id, parent_id, orden}]
      $nivelMaximo    nivel más profundo permitido (0 = primer nivel)
      $refrescarIds   ids de elementos a reemplazar después de guardar
      $avisarSinProductos  avisar con Deshacer si algo queda con 0 productos (menús)
      $movido         id del nodo recién subido/bajado, para volver a él

    Marcado esperado de cada nodo: li.arbol-nodo#{prefijo}{id}[data-id][data-nombre]
    > .arbol-fila (con .arbol-arrastrar, .arbol-plegar, .arbol-nombre, .arbol-detalle,
    .arbol-cuenta, .arbol-acciones) y, adentro, ul.arbol-lista[data-nivel].
--}}
@push('styles')
<style>
    .arbol-lista { list-style: none; margin: 0; padding: 0; }
    /* Sangría con una guía punteada que muestra de quién cuelga cada submenú. */
    .arbol-lista .arbol-lista { margin-left: 1.9rem; border-left: 1px dashed #dee2e6; }

    .arbol-fila {
        display: flex; align-items: center; gap: .5rem;
        padding: .55rem .75rem; background: #fff; border-bottom: 1px solid #f1f3f5;
    }
    .arbol-fila:hover { background: #f8f9fa; }

    .arbol-arrastrar { cursor: grab; color: #ced4da; padding: 0 .1rem; touch-action: none; }
    .arbol-fila:hover .arbol-arrastrar { color: #6c757d; }
    .arbol-plegar { border: 0; background: none; padding: 0; width: 1.1rem; color: #6c757d; line-height: 1; }
    .arbol-plegar i { display: inline-block; transition: transform .15s; }
    .arbol-plegar-vacio { width: 1.1rem; flex-shrink: 0; }

    .arbol-texto { flex: 1 1 auto; min-width: 0; }
    .arbol-nombre { font-weight: 600; color: #212529; text-decoration: none; }
    .arbol-nombre:hover { text-decoration: underline; }
    .arbol-detalle { font-size: .8rem; color: #6c757d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .arbol-cuenta {
        flex-shrink: 0; font-size: .75rem; color: #6c757d; white-space: nowrap;
        background: #f1f3f5; border-radius: 1rem; padding: .15rem .6rem;
    }
    .arbol-cuenta.vacio { background: #fff3cd; color: #997404; }

    /* Las acciones no compiten con el contenido: aparecen al pasar el mouse por la
       fila (o con el teclado). En pantallas táctiles, siempre. */
    .arbol-acciones { display: flex; gap: .25rem; flex-shrink: 0; opacity: 0; transition: opacity .12s; }
    .arbol-fila:hover .arbol-acciones,
    .arbol-fila:focus-within .arbol-acciones,
    .arbol-acciones:has(.show) { opacity: 1; }
    @media (hover: none) { .arbol-acciones { opacity: 1; } }

    .arbol-nodo.inactivo > .arbol-fila .arbol-texto { opacity: .55; }
    .arbol-nodo.plegado > .arbol-lista { display: none; }
    .arbol-nodo.plegado > .arbol-fila .arbol-plegar i { transform: rotate(-90deg); }

    /* Mientras se arrastra, las listas vacías se abren un poco para poder soltar
       adentro de un menú que todavía no tiene submenús. */
    body.arrastrando-arbol .arbol-lista .arbol-lista:empty { min-height: .75rem; border-left-style: solid; border-left-color: #adb5bd; }
    .arbol-fantasma > .arbol-fila { background: color-mix(in srgb, var(--admin-color) 30%, white); outline: 1px dashed #adb5bd; }
    /* El menú dentro del que va a quedar lo que se arrastra. */
    .arbol-nodo.destino-anidar > .arbol-fila { background: color-mix(in srgb, var(--admin-color) 18%, white); box-shadow: inset 3px 0 0 #6c757d; }
    .arbol-nodo.destino-anidar > .arbol-fila .arbol-nombre::after { content: " · soltá para meterlo adentro"; font-weight: 400; font-size: .75rem; color: #6c757d; }
    .arbol-fantasma > .arbol-lista { display: none; }

    /* El que se acaba de mover o al que se llegó desde la vista previa. */
    .arbol-nodo.recien-movido > .arbol-fila,
    .arbol-nodo:target > .arbol-fila { animation: recien-movido 1.6s ease-out; }
    @keyframes recien-movido { from { background-color: #fff3cd; } to { background-color: transparent; } }

    @media (max-width: 575.98px) {
        .arbol-lista .arbol-lista { margin-left: 1rem; }
        .arbol-fila { padding: .5rem; gap: .35rem; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var ARBOL        = @json($arbolId);
    var PREFIJO      = @json($prefijoNodo);
    var URL_ORDENAR  = @json($urlOrdenar);
    var NIVEL_MAXIMO = {{ (int) $nivelMaximo }};
    var REFRESCAR    = @json($refrescarIds ?? [$arbolId]);
    var AVISAR_VACIOS = @json($avisarSinProductos ?? false);
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
        document.querySelectorAll('#' + ARBOL + ' .arbol-nodo').forEach(function (nodo) {
            var cuenta = nodo.querySelector(':scope > .arbol-fila .arbol-cuenta');
            mapa[nodo.dataset.id] = cuenta ? parseInt(cuenta.textContent.replace(/\D/g, ''), 10) || 0 : null;
        });
        return mapa;
    }

    // ── Plegar ──────────────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var boton = e.target.closest('.arbol-plegar');
        if (!boton) return;
        var nodo = boton.closest('.arbol-nodo');
        var plegado = nodo.classList.toggle('plegado');
        boton.setAttribute('aria-expanded', plegado ? 'false' : 'true');
        plegados[nodo.dataset.id] = plegado;
    });

    function aplicarPlegados() {
        Object.keys(plegados).forEach(function (id) {
            var nodo = document.getElementById(PREFIJO + id);
            if (nodo && plegados[id] && nodo.querySelector(':scope > .arbol-fila .arbol-plegar')) {
                nodo.classList.add('plegado');
            }
        });
    }

    // ── Arrastrar ───────────────────────────────────────────────────────────

    /** Cuántos niveles tiene un menú hacia adentro (0 si no tiene submenús). */
    function altura(nodo) {
        var max = 0;
        nodo.querySelectorAll(':scope > .arbol-lista > .arbol-nodo').forEach(function (hijo) {
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
        var nombre = nodo.querySelector(':scope > .arbol-fila .arbol-nombre');
        return !!nombre && x >= nombre.getBoundingClientRect().left + 24;
    }

    /** Resalta el menú dentro del que va a quedar lo que se arrastra. */
    function marcarDestino(nodo) {
        document.querySelectorAll('.arbol-nodo.destino-anidar').forEach(function (n) {
            if (n !== nodo) n.classList.remove('destino-anidar');
        });
        if (nodo) nodo.classList.add('destino-anidar');
    }

    /** El árbol tal como quedó en pantalla: padre y posición de cada menú. */
    function arbol() {
        var items = [];
        document.querySelectorAll('#' + ARBOL + ' .arbol-nodo').forEach(function (nodo) {
            var padre = nodo.parentElement.closest('.arbol-nodo');
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
                REFRESCAR.forEach(function (id) {
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
        if (!AVISAR_VACIOS) return false;
        var movido = document.getElementById(PREFIJO + movidoId);
        if (!movido) return false;

        var nodos = [movido].concat(Array.prototype.slice.call(movido.querySelectorAll('.arbol-nodo')));
        var vacios = nodos.filter(function (nodo) {
            var despues = cuentas()[nodo.dataset.id];
            return antesDeMover[nodo.dataset.id] > 0 && despues === 0;
        }).map(function (nodo) { return '«' + nodo.dataset.nombre + '»'; });

        if (!vacios.length) return false;

        // Los filtros que hereda: los de los menús de arriba que no sólo agrupan.
        var filtros = [], padre = movido.parentElement.closest('.arbol-nodo'), nombrePadre = padre ? padre.dataset.nombre : '';
        while (padre) {
            if (padre.querySelector(':scope > .arbol-fila .arbol-cuenta')) {
                filtros.unshift(padre.querySelector(':scope > .arbol-fila .arbol-detalle').textContent.trim());
            }
            padre = padre.parentElement.closest('.arbol-nodo');
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
        document.querySelectorAll('#' + ARBOL + ', #' + ARBOL + ' .arbol-lista').forEach(function (lista) {
            if (lista._sortable) return;
            lista._sortable = Sortable.create(lista, {
                group: ARBOL,
                handle: '.arbol-arrastrar',
                animation: 150,
                // Arrastre propio de la librería y no el nativo del navegador: se ve
                // igual en todos y sigue al mouse con la fila entera.
                forceFallback: true,
                fallbackClass: 'arbol-arrastrado',
                fallbackOnBody: true,
                swapThreshold: .65,
                emptyInsertThreshold: 6,
                delay: 150,
                delayOnTouchOnly: true,     // en el celular, mantener apretado para arrastrar
                ghostClass: 'arbol-fantasma',
                onStart: function () {
                    document.body.classList.add('arrastrando-arbol');
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
                    if (fila && fila !== evt.dragged && fila.classList.contains('arbol-nodo') && aLaDerechaDe(fila, x)) {
                        var lista = fila.querySelector(':scope > .arbol-lista');
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
                        return hijo !== evt.dragged && hijo.classList.contains('arbol-nodo');
                    });
                    var destino = evt.to.closest('.arbol-nodo');
                    if (vacia && destino && !aLaDerechaDe(destino, x)) return false;

                    if (vacia && destino) marcarDestino(destino);
                    return true;
                },
                // Se compara el árbol entero y no los índices del evento: al pasar a
                // una lista anidada, Sortable puede informar el mismo índice aunque
                // el menú haya cambiado de padre.
                onEnd: function (evt) {
                    document.body.classList.remove('arrastrando-arbol');
                    marcarDestino(null);
                    if (JSON.stringify(arbol()) === antes) return;
                    guardar(evt.item, antes, cuentasAntes);
                }
            });
        });
    }

    if (document.getElementById(ARBOL) && window.Sortable) iniciar();

    @if(!empty($movido))
        // Después de subir o bajar (desde ⋯), volver a la fila movida.
        var fila = document.getElementById(PREFIJO + {{ (int) $movido }});
        if (fila) fila.scrollIntoView({ block: 'center' });
    @endif
})();
</script>
@endpush
