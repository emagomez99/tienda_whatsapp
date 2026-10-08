{{--
    Panel lateral para elegir categorías: búsqueda, casillas y "Crear categoría" ahí
    mismo. Lo usan el formulario de producto (las del producto) y el listado (asignar
    o quitar en masa), con lo que cada uno haga con lo elegido.

    Recibe:
      $panelId     id del offcanvas
      $categorias  Categoria::enOrdenDeArbol()
      $titulo      encabezado del panel
      $notaMadres  (opcional, true) avisar "Ya aparece acá por «X»" en las madres de las elegidas
      $pie         (opcional) HTML del pie; por defecto un botón "Listo"

    En JS:  var panel = new PanelDeCategorias(document.getElementById(id), elegidas, alCambiar);
            panel.elegidas(), panel.fijar(ids), panel.porId(id)
--}}
@php
    $puedeCrear = auth()->user()->puede('categorias.gestionar');
    $datosCategorias = $categorias->map(function ($c) {
        return ['id' => $c->id, 'nombre' => $c->nombre, 'camino' => $c->camino(), 'nivel' => $c->nivel(), 'padre' => $c->parent_id];
    })->values();
@endphp

<div class="offcanvas offcanvas-end panel-categorias" tabindex="-1" id="{{ $panelId }}" aria-labelledby="{{ $panelId }}-titulo"
     data-nota-madres="{{ ($notaMadres ?? true) ? '1' : '0' }}">
    <script type="application/json" data-rol="datos">@json($datosCategorias)</script>
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="{{ $panelId }}-titulo">{{ $titulo }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <div class="p-3 border-bottom">
            <input type="search" class="form-control" data-rol="buscar" placeholder="Buscar categoría" autocomplete="off" aria-label="Buscar categoría">
            @if($puedeCrear)
                <button type="button" class="btn btn-link px-0 mt-2" data-rol="crear-abrir">
                    <i class="bi bi-plus-circle"></i> Crear categoría
                </button>
                <div class="d-none mt-2" data-rol="crear">
                    <input type="text" class="form-control mb-2" data-rol="crear-nombre" placeholder="Nombre (ej: Gaseosas)" maxlength="255" aria-label="Nombre de la categoría nueva">
                    <select class="form-select mb-2" data-rol="crear-madre" aria-label="Dentro de"></select>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary btn-sm" data-rol="crear-guardar">Crear y elegir</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-rol="crear-cancelar">Cancelar</button>
                    </div>
                    <div class="text-danger small mt-1 d-none" data-rol="crear-error"></div>
                </div>
            @endif
        </div>
        <div class="flex-grow-1 overflow-auto" data-rol="lista"></div>
        <div class="p-3 border-top">
            @if(isset($pie))
                {!! $pie !!}
            @else
                <button type="button" class="btn btn-primary w-100" data-bs-dismiss="offcanvas">Listo</button>
            @endif
        </div>
    </div>
</div>

@once
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
/**
 * Lista con búsqueda y alta rápida de categorías dentro de un offcanvas.
 * alCambiar(elegidas) se llama cada vez que se marca o desmarca una.
 */
function PanelDeCategorias(panel, elegidas, alCambiar) {
    var URL_CREAR   = @json(route('admin.categorias.rapida'));
    var NIVEL_MADRE = {{ App\Models\Categoria::NIVEL_MAXIMO_PADRE }};

    var self       = this;
    var categorias = JSON.parse(panel.querySelector('[data-rol="datos"]').textContent);
    var lista      = panel.querySelector('[data-rol="lista"]');
    var buscar     = panel.querySelector('[data-rol="buscar"]');
    var notaMadres = panel.getAttribute('data-nota-madres') === '1';
    elegidas = elegidas.slice();

    function rol(nombre) { return panel.querySelector('[data-rol="' + nombre + '"]'); }

    function porId(id) {
        for (var i = 0; i < categorias.length; i++) if (categorias[i].id === id) return categorias[i];
        return null;
    }

    /** Ids de las madres de las elegidas: el producto ya aparece ahí sin marcarlas. */
    function incluidasPorHijas() {
        var mapa = {};
        if (!notaMadres) return mapa;
        elegidas.forEach(function (id) {
            var c = porId(id);
            while (c && c.padre) { mapa[c.padre] = c.nombre; c = porId(c.padre); }
        });
        return mapa;
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
        pintarLista();
        alCambiar(elegidas.slice());
    }

    buscar.addEventListener('input', pintarLista);

    // ── Crear sin salir de la pantalla ──────────────────────────────────────

    var abrir = rol('crear-abrir');
    if (abrir) {
        var caja    = rol('crear');
        var nombre  = rol('crear-nombre');
        var madre   = rol('crear-madre');
        var error   = rol('crear-error');
        var guardar = rol('crear-guardar');

        var opcionesDeMadre = function () {
            madre.innerHTML = '<option value="">Primer nivel</option>';
            categorias.forEach(function (c) {
                if (c.nivel > NIVEL_MADRE) return;
                var op = document.createElement('option');
                op.value = c.id;
                op.textContent = 'Dentro de ' + c.camino.replace(/ > /g, ' › ');
                madre.appendChild(op);
            });
        };

        var mostrarCrear = function (mostrar) {
            caja.classList.toggle('d-none', !mostrar);
            abrir.classList.toggle('d-none', mostrar);
            error.classList.add('d-none');
            if (mostrar) {
                opcionesDeMadre();
                nombre.value = buscar.value.trim();   // lo que se buscó y no estaba
                nombre.focus();
            }
        };

        abrir.addEventListener('click', function () { mostrarCrear(true); });
        rol('crear-cancelar').addEventListener('click', function () { mostrarCrear(false); });
        nombre.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); guardar.click(); }   // sin esto, Enter envía el formulario de la página
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

    self.porId    = porId;
    self.hay      = function () { return categorias.length > 0; };
    self.elegidas = function () { return elegidas.slice(); };
    self.fijar    = function (ids) { elegidas = ids.slice(); buscar.value = ''; pintarLista(); };

    pintarLista();
}
</script>
@endpush
@endonce
