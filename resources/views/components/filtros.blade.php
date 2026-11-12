{{--
    Los filtros de un listado del panel, iguales en todas las pantallas:
    arriba el buscador y, a la derecha, recargar, el embudo (con un globito que dice
    cuántos filtros hay puestos) y el embudo tachado para quitarlos todos. El embudo
    abre el panel de filtros desde la derecha. Cada filtro puesto se ve como una
    fichita con ✕ para sacarlo de a uno.

        <x-filtros :url="route('admin.proveedores.index')" buscar="Buscar por nombre o email"
                   :campos="[
                       ['nombre' => 'activo', 'titulo' => 'Estado', 'opciones' => ['1' => 'Activos', '0' => 'Inactivos']],
                   ]" />

    Cada campo: nombre (el parámetro de la dirección), titulo y:
      - opciones [valor => texto]: botones (hasta 4) o una lista desplegable (más, o 'lista' => true).
      - 'tipo' => 'rango': dos números, {nombre}_desde y {nombre}_hasta (ej. precio).
    Para algo especial, el slot va adentro del panel, y :fichas-extra suma sus fichitas
    ([texto, dirección sin ese filtro]) y :cantidad-extra al globito.
    :conservar = parámetros que no son filtros del panel pero no se pierden (ej. la
    pestaña de estado de Pedidos).
--}}
@props([
    'url',
    'buscar'        => null,
    'campos'        => [],
    'conservar'     => [],
    'fichasExtra'   => [],
    'cantidadExtra' => 0,
    'titulo'        => 'Filtrar',
    'id'            => 'panel-filtros',
])

@php
    // Un parámetro que vino vacío (?desde=) llega como null: se deja en '' para que
    // siga viajando en el buscador y en los enlaces (en Pedidos, "todas las fechas").
    $query = array_map(function ($v) { return $v === null ? '' : $v; }, request()->query());
    unset($query['page']);
    $conValor = function ($v) { return $v !== null && $v !== ''; };

    // Los parámetros de cada campo (un rango usa dos).
    $parametrosDe = function ($campo) {
        return ($campo['tipo'] ?? null) === 'rango' ? [$campo['nombre'] . '_desde', $campo['nombre'] . '_hasta'] : [$campo['nombre']];
    };
    $sin = function (array $nombres) use ($url, $query) {
        $resto = array_diff_key($query, array_flip($nombres));
        return $url . ($resto ? '?' . http_build_query($resto) : '');
    };

    $fichas = [];
    foreach ($campos as $campo) {
        if (($campo['tipo'] ?? null) === 'rango') {
            $desde = $query[$campo['nombre'] . '_desde'] ?? null;
            $hasta = $query[$campo['nombre'] . '_hasta'] ?? null;
            if ($conValor($desde) || $conValor($hasta)) {
                $texto = $campo['titulo'] . ': ' . ($conValor($desde) && $conValor($hasta) ? "de {$desde} a {$hasta}" : ($conValor($desde) ? "desde {$desde}" : "hasta {$hasta}"));
                $fichas[] = [$texto, $sin($parametrosDe($campo))];
            }
            continue;
        }
        $valor = $query[$campo['nombre']] ?? null;
        if ($conValor($valor) && is_scalar($valor)) {
            $texto = $campo['opciones'][$valor] ?? $valor;
            $fichas[] = [($campo['ficha'] ?? $campo['titulo']) . ': ' . $texto, $sin($parametrosDe($campo))];
        }
    }
    $fichas = array_merge($fichas, $fichasExtra);
    $cantidad = count($fichas) - count($fichasExtra) + $cantidadExtra;

    $hayCampos = !empty($campos) || trim((string) $slot) !== '';
    $hayFiltros = !empty($fichas) || $conValor($query['buscar'] ?? null);
    $limpio = $url . (array_intersect_key($query, array_flip($conservar)) ? '?' . http_build_query(array_intersect_key($query, array_flip($conservar))) : '');
@endphp

<div class="card mb-3 barra-filtros">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-2">
            @if($buscar)
                <form action="{{ $url }}" method="GET" class="flex-grow-1" role="search" style="max-width: 520px;">
                    @foreach(array_diff_key($query, ['buscar' => true]) as $campo => $valor)
                        @if(is_scalar($valor))<input type="hidden" name="{{ $campo }}" value="{{ $valor }}">@endif
                    @endforeach
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute text-muted" style="left: 12px; top: 50%; transform: translateY(-50%);"></i>
                        <input type="search" name="buscar" class="form-control ps-5" placeholder="{{ $buscar }}" value="{{ request('buscar') }}" aria-label="{{ $buscar }}">
                    </div>
                </form>
            @endif

            <div class="ms-auto d-flex align-items-center gap-1">
                <a href="{{ request()->fullUrl() }}" class="btn btn-link text-secondary px-2" title="Recargar" aria-label="Recargar">
                    <i class="bi bi-arrow-clockwise fs-5"></i>
                </a>
                @if($hayCampos)
                    <button type="button" class="btn btn-link text-secondary px-2 position-relative" data-bs-toggle="offcanvas" data-bs-target="#{{ $id }}"
                            title="Filtrar" aria-label="Filtrar{{ $cantidad ? ' (' . $cantidad . ($cantidad === 1 ? ' puesto)' : ' puestos)') : '' }}">
                        <i class="bi bi-funnel-fill fs-5"></i>
                        @if($cantidad)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="margin-left: -6px; margin-top: 6px;">{{ $cantidad }}</span>
                        @endif
                    </button>
                @endif
                @if($hayFiltros)
                    <a href="{{ $limpio }}" class="btn btn-link text-secondary px-2" title="Quitar todos los filtros" aria-label="Quitar todos los filtros">
                        <span class="position-relative d-inline-block fs-5"><i class="bi bi-funnel"></i><i class="bi bi-slash-lg position-absolute top-50 start-50 translate-middle"></i></span>
                    </a>
                @endif
            </div>
        </div>

        @if($fichas)
            <div class="d-flex flex-wrap gap-2 mt-2">
                @foreach($fichas as [$texto, $sinEsta])
                    <span class="badge rounded-pill text-bg-light border fw-normal d-inline-flex align-items-center gap-1 py-2 px-3">
                        {{ $texto }}
                        <a href="{{ $sinEsta }}" class="text-secondary lh-1" title="Quitar este filtro" aria-label="Quitar {{ $texto }}"><i class="bi bi-x-lg"></i></a>
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</div>

@if($hayCampos)
<div class="offcanvas offcanvas-end" tabindex="-1" id="{{ $id }}" aria-labelledby="{{ $id }}-titulo" style="width: 380px; max-width: 100%;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="{{ $id }}-titulo"><i class="bi bi-funnel"></i> {{ $titulo }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <form action="{{ $url }}" method="GET" class="d-flex flex-column flex-grow-1 overflow-hidden form-filtros">
        @foreach(array_merge(['buscar'], $conservar) as $nombre)
            @if($conValor($query[$nombre] ?? null) && is_scalar($query[$nombre]))
                <input type="hidden" name="{{ $nombre }}" value="{{ $query[$nombre] }}">
            @endif
        @endforeach
        <div class="offcanvas-body d-flex flex-column gap-3">
            @foreach($campos as $campo)
                @php $nombre = $campo['nombre']; $actual = $query[$nombre] ?? null; @endphp
                @if(($campo['tipo'] ?? null) === 'rango')
                    <div>
                        <div class="form-label small fw-semibold mb-1">{{ $campo['titulo'] }}</div>
                        <div class="d-flex align-items-center gap-2">
                            <input type="number" name="{{ $nombre }}_desde" class="form-control" min="0" step="0.01" placeholder="Desde" value="{{ $query[$nombre . '_desde'] ?? '' }}" aria-label="{{ $campo['titulo'] }} desde">
                            <span class="text-muted">a</span>
                            <input type="number" name="{{ $nombre }}_hasta" class="form-control" min="0" step="0.01" placeholder="Hasta" value="{{ $query[$nombre . '_hasta'] ?? '' }}" aria-label="{{ $campo['titulo'] }} hasta">
                        </div>
                    </div>
                @elseif(!empty($campo['lista']) || count($campo['opciones']) > 4)
                    <div>
                        <label for="{{ $id }}-{{ $nombre }}" class="form-label small fw-semibold mb-1">{{ $campo['titulo'] }}</label>
                        <select name="{{ $nombre }}" id="{{ $id }}-{{ $nombre }}" class="form-select">
                            <option value="">{{ $campo['todos'] ?? 'Todos' }}</option>
                            @foreach($campo['opciones'] as $valor => $texto)
                                <option value="{{ $valor }}" {{ (string) $actual === (string) $valor ? 'selected' : '' }}>{!! $campo['sangria'][$valor] ?? '' !!}{{ $texto }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <div class="form-label small fw-semibold mb-1">{{ $campo['titulo'] }}</div>
                        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="{{ $campo['titulo'] }}">
                            <input type="radio" class="btn-check" name="{{ $nombre }}" id="{{ $id }}-{{ $nombre }}-todos" value="" {{ !$conValor($actual) ? 'checked' : '' }}>
                            <label class="btn btn-outline-primary" for="{{ $id }}-{{ $nombre }}-todos">{{ $campo['todos'] ?? 'Todos' }}</label>
                            @foreach($campo['opciones'] as $valor => $texto)
                                <input type="radio" class="btn-check" name="{{ $nombre }}" id="{{ $id }}-{{ $nombre }}-{{ $valor }}" value="{{ $valor }}" {{ (string) $actual === (string) $valor ? 'checked' : '' }}>
                                <label class="btn btn-outline-primary" for="{{ $id }}-{{ $nombre }}-{{ $valor }}">{{ $texto }}</label>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            {{ $slot }}
        </div>
        <div class="border-top p-3 d-flex gap-2">
            <a href="{{ $limpio }}" class="btn btn-outline-secondary">Limpiar</a>
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check2"></i> Ver resultados</button>
        </div>
    </form>
</div>
@endif

@once
<script>
    // Los campos vacíos no viajan en la dirección ("?estado=&perfil=" ensuciaría las
    // fichitas). Los que llevan data-viaja-vacio sí (ej. "todas las fechas").
    document.addEventListener('submit', function (e) {
        if (!e.target.classList || !e.target.classList.contains('form-filtros')) return;
        e.target.querySelectorAll('input, select').forEach(function (campo) {
            if (campo.hasAttribute('data-viaja-vacio')) return;
            if ((campo.type === 'radio' && !campo.checked) || campo.value === '') campo.disabled = true;
        });
    });
</script>
@endonce
