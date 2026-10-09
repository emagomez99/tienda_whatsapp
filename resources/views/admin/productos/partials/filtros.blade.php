{{--
    Filtros del listado de productos: el buscador a la vista y, a la derecha,
    recargar, el embudo (con un globito que dice cuántos filtros hay puestos) y el
    embudo tachado para quitarlos todos. El embudo abre el panel "Filtrar productos"
    desde la derecha. Cada filtro puesto se ve como una fichita con ✕ para sacarlo.
    Los filtros los entiende App\Support\FiltroDeProductos (los usa también Excel y
    "Agregar categorías" a todos los del filtro).
--}}
@php
    $valores = $filtro->valores();
    $cantidad = $filtro->cantidadDelPanel();
    $sinEste = function (...$campos) use ($valores) {
        return route('admin.productos.index', array_diff_key($valores, array_flip($campos)));
    };

    // Las fichitas: [texto, dirección sin ese filtro].
    $fichas = [];
    if (isset($valores['categoria'])) {
        $nombre = $valores['categoria'] === App\Support\FiltroDeProductos::SIN_CATEGORIA
            ? 'Sin categoría'
            : optional($categorias->firstWhere('id', (int) $valores['categoria']))->nombre;
        $fichas[] = ['Categoría: ' . ($nombre ?: '?'), $sinEste('categoria')];
    }
    if (isset($valores['proveedor'])) {
        $fichas[] = ['Proveedor: ' . (optional($proveedores->firstWhere('id', (int) $valores['proveedor']))->nombre ?: '?'), $sinEste('proveedor')];
    }
    if (isset($valores['disponible'])) {
        $fichas[] = [$valores['disponible'] ? 'Se muestran en la tienda' : 'No se muestran en la tienda', $sinEste('disponible')];
    }
    if (isset($valores['stock'])) {
        $fichas[] = [['con' => 'Con stock', 'poco' => 'Poco stock', 'sin' => 'Sin stock'][$valores['stock']] ?? 'Stock', $sinEste('stock')];
    }
    if (isset($valores['precio_desde']) || isset($valores['precio_hasta'])) {
        $texto = isset($valores['precio_desde'], $valores['precio_hasta'])
            ? 'Precio: de ' . $valores['precio_desde'] . ' a ' . $valores['precio_hasta']
            : (isset($valores['precio_desde']) ? 'Precio: desde ' . $valores['precio_desde'] : 'Precio: hasta ' . $valores['precio_hasta']);
        $fichas[] = [$texto, $sinEste('precio_desde', 'precio_hasta')];
    }
    if (isset($valores['variantes'])) {
        $fichas[] = [$valores['variantes'] === 'con' ? 'Con variantes' : 'Sin variantes', $sinEste('variantes')];
    }
    if (isset($valores['foto'])) {
        $fichas[] = [$valores['foto'] === 'con' ? 'Con foto' : 'Sin foto', $sinEste('foto')];
    }
    if (isset($valores['encargue'])) {
        $fichas[] = [$valores['encargue'] ? 'Se pueden pedir sin stock' : 'No se pueden pedir sin stock', $sinEste('encargue')];
    }
    if (isset($valores['caracteristica'])) {
        $nombre = optional($caracteristicas->firstWhere('id', (int) $valores['caracteristica']))->nombre ?: '?';
        $fichas[] = [$nombre . (isset($valores['valor']) ? ': ' . $valores['valor'] : ' (cargada)'), $sinEste('caracteristica', 'valor')];
    }
@endphp

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-2">
            <form action="{{ route('admin.productos.index') }}" method="GET" class="flex-grow-1" role="search" style="max-width: 520px;">
                @foreach(array_diff_key($valores, ['buscar' => true]) as $campo => $valor)
                    <input type="hidden" name="{{ $campo }}" value="{{ $valor }}">
                @endforeach
                <div class="position-relative">
                    <i class="bi bi-search position-absolute text-muted" style="left: 12px; top: 50%; transform: translateY(-50%);"></i>
                    <input type="search" name="buscar" class="form-control ps-5" placeholder="Buscar por nombre o código" value="{{ $filtro->valor('buscar') }}" aria-label="Buscar productos">
                </div>
            </form>

            <div class="ms-auto d-flex align-items-center gap-1">
                <a href="{{ request()->fullUrl() }}" class="btn btn-link text-secondary px-2" title="Recargar" aria-label="Recargar">
                    <i class="bi bi-arrow-clockwise fs-5"></i>
                </a>
                <button type="button" class="btn btn-link text-secondary px-2 position-relative" data-bs-toggle="offcanvas" data-bs-target="#panel-filtros"
                        title="Filtrar" aria-label="Filtrar{{ $cantidad ? ' (' . $cantidad . ' puestos)' : '' }}">
                    <i class="bi bi-funnel-fill fs-5"></i>
                    @if($cantidad)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="margin-left: -6px; margin-top: 6px;">{{ $cantidad }}</span>
                    @endif
                </button>
                @if($filtro->hayActivos())
                    <a href="{{ route('admin.productos.index') }}" class="btn btn-link text-secondary px-2" title="Quitar todos los filtros" aria-label="Quitar todos los filtros">
                        <span class="position-relative d-inline-block fs-5"><i class="bi bi-funnel"></i><i class="bi bi-slash-lg position-absolute top-50 start-50 translate-middle"></i></span>
                    </a>
                @endif
            </div>
        </div>

        @if($fichas)
            <div class="d-flex flex-wrap gap-2 mt-2">
                @foreach($fichas as [$texto, $url])
                    <span class="badge rounded-pill text-bg-light border fw-normal d-inline-flex align-items-center gap-1 py-2 px-3">
                        {{ $texto }}
                        <a href="{{ $url }}" class="text-secondary lh-1" title="Quitar este filtro" aria-label="Quitar {{ $texto }}"><i class="bi bi-x-lg"></i></a>
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- Panel "Filtrar productos", desde la derecha. --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="panel-filtros" aria-labelledby="panel-filtros-titulo" style="width: 380px; max-width: 100%;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="panel-filtros-titulo"><i class="bi bi-funnel"></i> Filtrar productos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <form action="{{ route('admin.productos.index') }}" method="GET" class="d-flex flex-column flex-grow-1 overflow-hidden" id="form-filtros-productos">
        @if($filtro->valor('buscar'))
            <input type="hidden" name="buscar" value="{{ $filtro->valor('buscar') }}">
        @endif
        <div class="offcanvas-body d-flex flex-column gap-3">
            <div>
                <label for="filtro-categoria" class="form-label small fw-semibold mb-1">Categoría</label>
                <select name="categoria" id="filtro-categoria" class="form-select">
                    <option value="">Todas</option>
                    <option value="{{ App\Support\FiltroDeProductos::SIN_CATEGORIA }}" {{ $filtro->valor('categoria') === App\Support\FiltroDeProductos::SIN_CATEGORIA ? 'selected' : '' }}>Sin categoría</option>
                    @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id }}" {{ (string) $filtro->valor('categoria') === (string) $categoria->id ? 'selected' : '' }}>
                            {!! str_repeat('&nbsp;&nbsp;&nbsp;', $categoria->nivel()) !!}{{ $categoria->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filtro-proveedor" class="form-label small fw-semibold mb-1">Proveedor</label>
                <select name="proveedor" id="filtro-proveedor" class="form-select">
                    <option value="">Todos</option>
                    @foreach($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}" {{ (string) $filtro->valor('proveedor') === (string) $proveedor->id ? 'selected' : '' }}>{{ $proveedor->nombre }}</option>
                    @endforeach
                </select>
            </div>

            @php
                $opciones = [
                    ['disponible', 'En la tienda', ['1' => 'Se muestran', '0' => 'No se muestran']],
                    ['stock', 'Stock', ['con' => 'Con stock', 'poco' => 'Poco (1 a ' . App\Support\FiltroDeProductos::POCO_STOCK . ')', 'sin' => 'Sin stock']],
                    ['variantes', 'Talles, colores…', ['con' => 'Con variantes', 'sin' => 'Sin variantes']],
                    ['foto', 'Foto', ['con' => 'Con foto', 'sin' => 'Sin foto']],
                    ['encargue', 'Sin stock, ¿se puede pedir?', ['1' => 'Sí', '0' => 'No']],
                ];
            @endphp
            @foreach($opciones as [$campo, $titulo, $lista])
                <div>
                    <div class="form-label small fw-semibold mb-1">{{ $titulo }}</div>
                    <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="{{ $titulo }}">
                        <input type="radio" class="btn-check" name="{{ $campo }}" id="filtro-{{ $campo }}-todos" value="" {{ $filtro->valor($campo) === null ? 'checked' : '' }}>
                        <label class="btn btn-outline-primary" for="filtro-{{ $campo }}-todos">Todos</label>
                        @foreach($lista as $valor => $texto)
                            <input type="radio" class="btn-check" name="{{ $campo }}" id="filtro-{{ $campo }}-{{ $valor }}" value="{{ $valor }}" {{ (string) $filtro->valor($campo) === (string) $valor ? 'checked' : '' }}>
                            <label class="btn btn-outline-primary" for="filtro-{{ $campo }}-{{ $valor }}">{{ $texto }}</label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <div class="form-label small fw-semibold mb-1">Precio</div>
                <div class="d-flex align-items-center gap-2">
                    <input type="number" name="precio_desde" class="form-control" min="0" step="0.01" placeholder="Desde" value="{{ $filtro->valor('precio_desde') }}" aria-label="Precio desde">
                    <span class="text-muted">a</span>
                    <input type="number" name="precio_hasta" class="form-control" min="0" step="0.01" placeholder="Hasta" value="{{ $filtro->valor('precio_hasta') }}" aria-label="Precio hasta">
                </div>
            </div>

            <div>
                <label for="filtro-caracteristica" class="form-label small fw-semibold mb-1">Característica</label>
                <select name="caracteristica" id="filtro-caracteristica" class="form-select mb-2">
                    <option value="">Cualquiera</option>
                    @foreach($caracteristicas as $c)
                        <option value="{{ $c->id }}" data-url="{{ route('admin.etiquetas.valores', $c) }}" {{ (string) $filtro->valor('caracteristica') === (string) $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                    @endforeach
                </select>
                <input type="text" name="valor" id="filtro-valor" class="form-control" placeholder="Valor, ej.: Coca-Cola (vacío: cualquiera)"
                       value="{{ $filtro->valor('valor') }}" autocomplete="off" aria-label="Valor de la característica"
                       data-combo="{{ $caracteristicas->isNotEmpty() ? route('admin.etiquetas.valores', (int) ($filtro->valor('caracteristica') ?: $caracteristicas->first()->id)) : '' }}" data-combo-sin-nuevo>
            </div>
        </div>
        <div class="border-top p-3 d-flex gap-2">
            <a href="{{ $filtro->valor('buscar') ? route('admin.productos.index', ['buscar' => $filtro->valor('buscar')]) : route('admin.productos.index') }}" class="btn btn-outline-secondary">Limpiar</a>
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check2"></i> Ver productos</button>
        </div>
    </form>
</div>

@include('admin.productos.partials.combo-sugerencias')
<script>
(function () {
    // El valor sugiere los de la característica elegida; sin característica no se usa.
    var caracteristica = document.getElementById('filtro-caracteristica');
    var valor = document.getElementById('filtro-valor');
    function actualizar() {
        var opcion = caracteristica.options[caracteristica.selectedIndex];
        valor.disabled = !caracteristica.value;
        if (opcion && opcion.dataset.url) {
            valor.setAttribute('data-combo', opcion.dataset.url);
            valor.setAttribute('data-combo-sin-nuevo', '');
        }
    }
    caracteristica.addEventListener('change', function () { valor.value = ''; actualizar(); });
    actualizar();

    // Los vacíos no viajan en la dirección: "?stock=&foto=" ensuciaría las fichitas.
    document.getElementById('form-filtros-productos').addEventListener('submit', function (e) {
        e.target.querySelectorAll('input, select').forEach(function (campo) {
            if ((campo.type === 'radio' && !campo.checked) || campo.value === '') campo.disabled = true;
        });
    });
})();
</script>
