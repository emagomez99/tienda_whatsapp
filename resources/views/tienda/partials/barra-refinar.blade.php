{{--
    Barra para acomodar lo que se ve en el catálogo: el botón "Filtrar" (con un globito
    que cuenta lo elegido), "Ordenar por" a la vista y una fichita con ✕ por cada
    filtro puesto. El botón abre el panel desde la derecha (en el celular, pantalla
    completa) con:
      - los filtros opcionales del menú (Año, Forma, Marca…). Los obligatorios no van
        acá: están a la vista (components/filtros-cascada), sin ellos no se ve nada;
      - el rango de precio y "Solo con stock" (App\Support\RefinadoDelCatalogo).
    Los filtros del menú cargan por AJAX (script de components/filtros-cascada, que
    busca .filtro-select, #contador-resultados, #total-productos y #btn-limpiar);
    precio, stock y orden viajan en la dirección y recargan la página.
--}}
@php
    $refinado = App\Support\RefinadoDelCatalogo::desde(request());
    $conCascada = isset($menuActual) && $menuActual && $menuActual->tieneFiltros();
    $cascadaEnPanel = $conCascada && !$menuActual->filtros_requeridos;
    $sinParametros = function (array $nombres) {
        $resto = array_diff_key(request()->query(), array_flip(array_merge($nombres, ['page'])));
        return url()->current() . ($resto ? '?' . http_build_query($resto) : '');
    };
    $formatoPrecio = function ($n) { return '$' . number_format($n, 0, ',', '.'); };

    $fichas = [];
    if ($refinado->precioDesde() !== null || $refinado->precioHasta() !== null) {
        $desde = $refinado->precioDesde();
        $hasta = $refinado->precioHasta();
        $fichas[] = [
            $desde !== null && $hasta !== null ? 'De ' . $formatoPrecio($desde) . ' a ' . $formatoPrecio($hasta)
                : ($desde !== null ? 'Desde ' . $formatoPrecio($desde) : 'Hasta ' . $formatoPrecio($hasta)),
            $sinParametros(['precio_desde', 'precio_hasta']),
        ];
    }
    if ($refinado->conStock()) {
        $fichas[] = ['Solo con stock', $sinParametros(['con_stock'])];
    }
@endphp

<div class="d-flex flex-wrap align-items-center gap-2 mb-2 barra-refinar">
    <button type="button" class="btn btn-outline-secondary position-relative" data-bs-toggle="offcanvas" data-bs-target="#panel-refinar" aria-controls="panel-refinar">
        <i class="bi bi-funnel"></i> Filtrar
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger {{ $refinado->cantidad() ? '' : 'd-none' }}"
              id="refinar-globito" data-servidor="{{ $refinado->cantidad() }}">{{ $refinado->cantidad() }}</span>
    </button>

    <div class="d-flex align-items-center gap-2 ms-auto">
        @if($cascadaEnPanel)
            <span class="small text-muted" id="contador-resultados" style="display: none;"><span id="total-productos">0</span> productos</span>
        @endif
        <label for="refinar-orden" class="small text-muted text-nowrap mb-0">Ordenar por</label>
        <select id="refinar-orden" class="form-select form-select-sm" style="width: auto;">
            @foreach(App\Support\RefinadoDelCatalogo::ORDENES as $valor => $texto)
                <option value="{{ $valor }}" {{ $refinado->ordenElegido() === $valor ? 'selected' : '' }}>{{ $texto }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3" id="refinar-fichas">
    @foreach($fichas as [$texto, $url])
        <span class="badge rounded-pill text-bg-light border fw-normal d-inline-flex align-items-center gap-1 py-2 px-3">
            {{ $texto }}
            <a href="{{ $url }}" class="text-secondary lh-1" title="Quitar este filtro" aria-label="Quitar {{ $texto }}"><i class="bi bi-x-lg"></i></a>
        </span>
    @endforeach
</div>

<div class="offcanvas offcanvas-end panel-refinar" tabindex="-1" id="panel-refinar" aria-labelledby="panel-refinar-titulo">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="panel-refinar-titulo"><i class="bi bi-funnel"></i> Filtrar</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column gap-3">
        @if($cascadaEnPanel)
            @php
                $etiquetasFiltro = $menuActual->getEtiquetasFiltro();
                $filtrosRequeridos = false;
                $filtrosConfig = $menuActual->filtros_config ?? [];
            @endphp
            @include('tienda.partials.selects-cascada', ['enPanel' => true])
        @endif

        <div>
            <div class="form-label small fw-bold mb-1">Precio</div>
            <div class="d-flex align-items-center gap-2">
                <input type="number" id="refinar-precio-desde" class="form-control" min="0" step="1" placeholder="Desde" value="{{ $refinado->precioDesde() }}" aria-label="Precio desde">
                <span class="text-muted">a</span>
                <input type="number" id="refinar-precio-hasta" class="form-control" min="0" step="1" placeholder="Hasta" value="{{ $refinado->precioHasta() }}" aria-label="Precio hasta">
            </div>
        </div>

        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="refinar-con-stock" {{ $refinado->conStock() ? 'checked' : '' }}>
            <label class="form-check-label" for="refinar-con-stock">Solo con stock (para llevar ya)</label>
        </div>
    </div>
    <div class="border-top p-3 d-flex gap-2">
        @if($cascadaEnPanel)
            {{-- El script de los filtros del menú necesita su #btn-limpiar; queda oculto
                 porque "Limpiar" de al lado ya vuelve todo a cero. --}}
            <span id="btn-limpiar" class="d-none"></span>
        @endif
        @php $filtrosDelMenu = array_values(array_filter(array_keys(request()->query()), function ($k) { return (bool) preg_match('/^f\d+$/', $k); })); @endphp
        <a href="{{ $sinParametros(array_merge(['precio_desde', 'precio_hasta', 'con_stock'], $filtrosDelMenu)) }}" class="btn btn-outline-secondary" id="refinar-limpiar">Limpiar</a>
        <button type="button" class="btn btn-primary flex-grow-1" id="refinar-aplicar"><i class="bi bi-check2"></i> Ver productos</button>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // Precio, stock y orden viajan en la dirección: se arma y se recarga la página.
    function irCon(cambios) {
        var url = new URL(window.location.href);
        Object.keys(cambios).forEach(function (clave) {
            if (cambios[clave] === '' || cambios[clave] === null) url.searchParams.delete(clave);
            else url.searchParams.set(clave, cambios[clave]);
        });
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    document.getElementById('refinar-orden').addEventListener('change', function () {
        irCon({ orden: this.value });
    });

    document.getElementById('refinar-aplicar').addEventListener('click', function () {
        irCon({
            precio_desde: document.getElementById('refinar-precio-desde').value,
            precio_hasta: document.getElementById('refinar-precio-hasta').value,
            con_stock: document.getElementById('refinar-con-stock').checked ? '1' : ''
        });
    });

    // Los filtros del menú que van en el panel: una fichita por cada uno elegido, y el
    // globito suma los elegidos a precio y stock.
    var fichas = document.getElementById('refinar-fichas');
    var globito = document.getElementById('refinar-globito');
    var selects = document.querySelectorAll('#panel-refinar .filtro-select');
    if (!selects.length) return;

    function pintar() {
        fichas.querySelectorAll('.ficha-menu').forEach(function (f) { f.remove(); });
        var elegidos = 0;
        selects.forEach(function (select) {
            if (!select.value) return;
            elegidos++;
            var ficha = document.createElement('span');
            ficha.className = 'badge rounded-pill text-bg-light border fw-normal d-inline-flex align-items-center gap-1 py-2 px-3 ficha-menu';
            ficha.appendChild(document.createTextNode(select.dataset.nombre + ': ' + select.options[select.selectedIndex].text + ' '));
            var quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.className = 'btn btn-link p-0 text-secondary lh-1';
            quitar.setAttribute('aria-label', 'Quitar ' + select.dataset.nombre);
            quitar.innerHTML = '<i class="bi bi-x-lg"></i>';
            quitar.addEventListener('click', function () {
                select.value = '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
            ficha.appendChild(quitar);
            fichas.insertBefore(ficha, fichas.firstChild);
        });
        var total = elegidos + parseInt(globito.dataset.servidor || '0', 10);
        globito.textContent = total;
        globito.classList.toggle('d-none', total === 0);
    }

    selects.forEach(function (select) { select.addEventListener('change', function () { setTimeout(pintar, 0); }); });
    // Los valores se cargan por AJAX al entrar: se repinta cuando llegan.
    var observador = new MutationObserver(function () { pintar(); });
    selects.forEach(function (select) { observador.observe(select, { childList: true }); });
    pintar();
})();
</script>
@endpush
