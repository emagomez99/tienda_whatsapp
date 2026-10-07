{{--
    Agregar o quitar categorías a varios productos del listado.

    Se marcan productos de la página; si se marcan todos y el filtro trae más, se
    ofrece "Elegir los N del filtro", que manda el filtro en vez de los ids (lo
    resuelve el servidor en una sola consulta, ver AsignacionDeCategorias).

    Usa $productos (paginador), $filtro (FiltroDeProductos) y $categorias.
--}}
@php $total = $productos->total(); @endphp

<form action="{{ route('admin.productos.categorias-en-masa') }}" method="POST" id="masa-form" class="d-none">
    @csrf
    <input type="hidden" name="accion" value="asignar">
    <input type="hidden" name="alcance" value="seleccion">
    @if($productos->currentPage() > 1)
        <input type="hidden" name="page" value="{{ $productos->currentPage() }}">
    @endif
    @foreach($filtro->valores() as $campo => $valor)
        <input type="hidden" name="filtro[{{ $campo }}]" value="{{ $valor }}">
    @endforeach
    <div data-rol="ids"></div>
</form>

<div class="masa-barra shadow d-none" id="masa-barra" role="region" aria-label="Acciones sobre los productos marcados">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold" id="masa-cuantos"></span>
        @if($total > $productos->count())
            <button type="button" class="btn btn-link btn-sm p-0 d-none" id="masa-todo-el-filtro">
                Elegir los {{ number_format($total, 0, ',', '.') }} {{ $filtro->hayActivos() ? 'del filtro' : 'productos' }}
            </button>
        @endif
        <div class="ms-auto d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-masa-accion="asignar">
                <i class="bi bi-plus-lg"></i> Agregar categorías
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-masa-accion="quitar">
                <i class="bi bi-dash-lg"></i> Quitar categorías
            </button>
            <button type="button" class="btn btn-link btn-sm text-secondary" id="masa-cancelar">Cancelar</button>
        </div>
    </div>
</div>

@include('admin.categorias.partials.panel-elegir', [
    'panelId'    => 'panel-categorias-masa',
    'titulo'     => 'Agregar categorías',
    'notaMadres' => false,
    'pie'        => '<div class="small text-muted mb-2" data-rol="pie-nota"></div>'
                  . '<button type="button" class="btn btn-primary w-100" data-rol="aplicar" disabled>Elegí una o más categorías</button>',
])

@push('styles')
<style>
    .masa-barra { position: sticky; bottom: 1rem; z-index: 1020; margin-top: 1rem; padding: .75rem 1rem;
                  background: #fff; border: 1px solid #dee2e6; border-radius: .75rem; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var TOTAL      = {{ $total }};
    var form       = document.getElementById('masa-form');
    var barra      = document.getElementById('masa-barra');
    var cuantos    = document.getElementById('masa-cuantos');
    var todos      = document.getElementById('masa-todos');
    var todoFiltro = document.getElementById('masa-todo-el-filtro');
    var casillas   = Array.prototype.slice.call(document.querySelectorAll('.masa-producto'));
    var panelEl    = document.getElementById('panel-categorias-masa');
    var aplicar    = panelEl.querySelector('[data-rol="aplicar"]');
    var pieNota    = panelEl.querySelector('[data-rol="pie-nota"]');
    var crearAbrir = panelEl.querySelector('[data-rol="crear-abrir"]');
    var offcanvas  = bootstrap.Offcanvas.getOrCreateInstance(panelEl);

    var accion = 'asignar';
    var elegidas = [];
    var alcanceFiltro = false;   // true: "todos los del filtro", no sólo los marcados

    var panel = new PanelDeCategorias(panelEl, [], function (nuevas) {
        elegidas = nuevas;
        pintarPie();
    });

    function marcados() {
        return casillas.filter(function (c) { return c.checked; });
    }

    function cantidad() {
        return alcanceFiltro ? TOTAL : marcados().length;
    }

    function enTexto(n) {
        return n.toLocaleString('es-AR') + (n === 1 ? ' producto' : ' productos');
    }

    function pintarBarra() {
        var n = marcados().length;
        if (n < casillas.length) alcanceFiltro = false;

        barra.classList.toggle('d-none', n === 0);
        todos.checked = n > 0 && n === casillas.length;
        todos.indeterminate = n > 0 && n < casillas.length;

        cuantos.textContent = alcanceFiltro
            ? 'Elegidos los ' + TOTAL.toLocaleString('es-AR') + ' productos'
            : (n === 1 ? '1 marcado' : n + ' marcados');
        if (todoFiltro) todoFiltro.classList.toggle('d-none', alcanceFiltro || n !== casillas.length);
    }

    function pintarPie() {
        var n = cantidad();
        aplicar.disabled = elegidas.length === 0;
        if (!elegidas.length) {
            aplicar.textContent = 'Elegí una o más categorías';
        } else {
            aplicar.textContent = (accion === 'asignar' ? 'Agregar a ' : 'Quitar de ') + enTexto(n);
        }
        pieNota.textContent = accion === 'asignar'
            ? 'Se suman a las que ya tienen; no se quita ninguna.'
            : 'Se quita sólo la categoría marcada: si un producto está en una subcategoría suya, sigue ahí.';
    }

    function abrirPanel(nuevaAccion) {
        accion = nuevaAccion;
        panelEl.querySelector('.offcanvas-title').textContent = accion === 'asignar' ? 'Agregar categorías' : 'Quitar categorías';
        if (crearAbrir) crearAbrir.parentNode.querySelector('[data-rol="crear"]').classList.add('d-none');
        if (crearAbrir) crearAbrir.classList.toggle('d-none', accion !== 'asignar');
        elegidas = [];
        panel.fijar([]);
        pintarPie();
        offcanvas.show();
    }

    function enviar() {
        form.querySelector('[name="accion"]').value = accion;
        form.querySelector('[name="alcance"]').value = alcanceFiltro ? 'filtro' : 'seleccion';

        var ids = form.querySelector('[data-rol="ids"]');
        ids.innerHTML = '';
        function oculto(nombre, valor) {
            var campo = document.createElement('input');
            campo.type = 'hidden';
            campo.name = nombre;
            campo.value = valor;
            ids.appendChild(campo);
        }
        elegidas.forEach(function (id) { oculto('categorias[]', id); });
        if (!alcanceFiltro) marcados().forEach(function (c) { oculto('productos[]', c.value); });

        aplicar.disabled = true;
        form.submit();
    }

    casillas.forEach(function (c) { c.addEventListener('change', pintarBarra); });
    todos.addEventListener('change', function () {
        casillas.forEach(function (c) { c.checked = todos.checked; });
        pintarBarra();
    });
    if (todoFiltro) todoFiltro.addEventListener('click', function () { alcanceFiltro = true; pintarBarra(); });
    document.getElementById('masa-cancelar').addEventListener('click', function () {
        casillas.forEach(function (c) { c.checked = false; });
        pintarBarra();
    });
    barra.querySelectorAll('[data-masa-accion]').forEach(function (boton) {
        boton.addEventListener('click', function () { abrirPanel(boton.getAttribute('data-masa-accion')); });
    });

    aplicar.addEventListener('click', function () {
        // Lo que toca más de una página se confirma: no se ve todo lo que cambia.
        if (!alcanceFiltro && accion === 'asignar') { enviar(); return; }

        var nombres = elegidas.map(function (id) { return '«' + panel.porId(id).camino.replace(/ > /g, ' › ') + '»'; }).join(', ');
        offcanvas.hide();
        window.confirmarAccion({
            texto:   (accion === 'asignar' ? '¿Agregar ' + nombres + ' a ' : '¿Quitar ' + nombres + ' de ') + enTexto(cantidad()) + '?',
            detalle: alcanceFiltro ? 'Son todos los que da el filtro actual, no sólo los de esta página.' : null,
            boton:   accion === 'asignar' ? 'Sí, agregar' : 'Sí, quitar'
        }, enviar);
    });

    pintarBarra();
})();
</script>
@endpush
