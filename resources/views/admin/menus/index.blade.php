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
        @if($menus->count() > 0)
            <small class="text-muted"><i class="bi bi-grip-vertical"></i> Arrastrá para ordenar · llevalo a la derecha para meterlo dentro de otro</small>
        @endif
    </div>
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
    <div class="card-footer bg-white small text-muted">
        <i class="bi bi-folder2"></i> <strong>Agrupa:</strong> no muestra productos, sólo despliega sus submenús.
        <span class="mx-1">·</span>
        <i class="bi bi-tag"></i> <strong>Etiqueta</strong> / <i class="bi bi-truck"></i> <strong>Proveedor:</strong> muestra esos productos.
        <span class="mx-1">·</span>
        <i class="bi bi-diagram-2"></i> Un submenú suma lo que filtra el de arriba: «Notebook › Asus» son las notebooks Asus.
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
    var antes = null;    // el árbol al empezar a arrastrar

    function aviso(texto, tipo) {
        var contenedor = document.getElementById('toast-container');
        var toast = document.createElement('div');
        toast.className = 'toast align-items-center border-0 text-bg-' + (tipo || 'success');
        toast.setAttribute('role', 'status');
        toast.innerHTML = '<div class="d-flex"><div class="toast-body"></div>'
            + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        toast.querySelector('.toast-body').textContent = texto;
        contenedor.appendChild(toast);
        new bootstrap.Toast(toast, { delay: 3000 }).show();
        toast.addEventListener('hidden.bs.toast', function () { toast.remove(); });
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

    function guardar(movido) {
        fetch(URL_ORDENAR, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ items: arbol() })
        })
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
                aviso('«' + movido + '» quedó en su nuevo lugar.');
                return refrescar();
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
                },
                // No dejar soltar donde quedaría más profundo de lo permitido.
                onMove: function (evt, original) {
                    var nivelDestino = parseInt(evt.to.dataset.nivel, 10);
                    if (nivelDestino + altura(evt.dragged) > NIVEL_MAXIMO) return false;

                    // Entrar en un menú que no tiene submenús sólo llevando el mouse a
                    // la derecha, hasta la altura de su nombre. Si no, al arrastrar en
                    // línea recta para reordenar, se metía sin querer en el de arriba.
                    var vacia = !Array.prototype.some.call(evt.to.children, function (hijo) {
                        return hijo !== evt.dragged && hijo.classList.contains('menu-nodo');
                    });
                    var destino = evt.to.closest('.menu-nodo');
                    if (vacia && destino) {
                        var x = original.clientX !== undefined ? original.clientX
                              : (original.touches && original.touches[0] ? original.touches[0].clientX : 0);
                        var nombre = destino.querySelector(':scope > .menu-fila .menu-nombre').getBoundingClientRect();
                        if (x < nombre.left + 24) return false;
                    }
                    return true;
                },
                // Se compara el árbol entero y no los índices del evento: al pasar a
                // una lista anidada, Sortable puede informar el mismo índice aunque
                // el menú haya cambiado de padre.
                onEnd: function (evt) {
                    document.body.classList.remove('arrastrando-menu');
                    if (JSON.stringify(arbol()) === antes) return;
                    guardar(evt.item.dataset.nombre);
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
