@extends('layouts.admin')

@section('title', 'Pedidos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-bag-check"></i> Pedidos</h3>
    @if(auth()->user()->puede('pedidos.gestionar'))
        <a href="{{ route('admin.pedidos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo
        </a>
    @endif
</div>

{{-- Filtros: búsqueda y rango de fechas. El estado va aparte, en pestañas con contadores. --}}
@php
    $filtrosActivos = request()->filled('buscar') || $rango->estaActivo();
    // En celular los filtros arrancan cerrados: siempre hay un rango (por defecto los
    // últimos 30 días) y abiertos ocupan media pantalla. Se abren solos si hay búsqueda.
    $abrirFiltros = request()->filled('buscar');
    // Parámetros que se conservan al cambiar de pestaña de estado.
    $conBusqueda = array_filter(['buscar' => request('buscar')]);
@endphp
<div class="card mb-3">
    <div class="card-header d-flex d-md-none justify-content-between align-items-center py-2 px-3"
         style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#filtros-pedidos"
         aria-expanded="{{ $abrirFiltros ? 'true' : 'false' }}">
        <span class="small text-truncate">
            <i class="bi bi-funnel me-1 text-muted"></i><span class="fw-semibold text-muted">Filtros</span>
            <span class="text-muted ms-1" id="filtros-resumen"></span>
        </span>
        <i class="bi bi-chevron-down filtros-chevron flex-shrink-0 ms-2" style="transition:transform .2s;{{ $abrirFiltros ? 'transform:rotate(180deg);' : '' }}"></i>
    </div>
    <div class="collapse d-md-block{{ $abrirFiltros ? ' show' : '' }}" id="filtros-pedidos">
    <div class="card-body py-3">
        <form action="{{ route('admin.pedidos.index') }}" method="GET">
            @if($estado)
                <input type="hidden" name="estado" value="{{ $estado }}">
            @endif
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="buscar" class="form-label small mb-1">Buscar</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="buscar" id="buscar" class="form-control"
                               placeholder="Nombre, email, celular o #123" value="{{ request('buscar') }}">
                    </div>
                </div>
                {{-- Un solo campo para el rango: muestra "Todas las fechas" o "1 oct – 15 oct 2026"
                     y al tocarlo abre un calendario (flatpickr). Lo que viaja son desde/hasta. --}}
                <div class="col-md-4">
                    <label for="fechas" class="form-label small mb-1">Fechas</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                        <input type="text" id="fechas" class="form-control bg-white" placeholder="Todas las fechas" readonly>
                        <button type="button" id="fechas-limpiar" class="btn btn-outline-secondary {{ $rango->estaActivo() ? '' : 'd-none' }}" title="Todas las fechas">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <input type="hidden" name="desde" id="desde" value="{{ $rango->desdeTexto() }}">
                    <input type="hidden" name="hasta" id="hasta" value="{{ $rango->hastaTexto() }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </div>
        </form>
    </div>
    </div>
</div>

{{-- Estado en pestañas: cada una dice cuántos pedidos hay dentro de la búsqueda y las fechas. --}}
@php
    $pestanas = [
        null         => ['Todos',       $porEstado->sum()],
        'pendiente'  => ['Pendientes',  $porEstado['pendiente'] ?? 0],
        'confirmado' => ['Confirmados', $porEstado['confirmado'] ?? 0],
        'cancelado'  => ['Cancelados',  $porEstado['cancelado'] ?? 0],
    ];
@endphp
<ul class="nav nav-tabs mb-0 nav-estados">
    @foreach($pestanas as $valor => $pestana)
        <li class="nav-item">
            <a class="nav-link {{ $estado === ($valor ?: null) ? 'active' : '' }}"
               href="{{ route('admin.pedidos.index', array_merge($conBusqueda, $rango->parametros(), array_filter(['estado' => $valor]))) }}">
                {{ $pestana[0] }} <span class="badge rounded-pill bg-secondary bg-opacity-25 text-body ms-1">{{ $pestana[1] }}</span>
            </a>
        </li>
    @endforeach
</ul>

<div class="card border-top-0 rounded-top-0">
    <div class="card-body p-0">
        @if($pedidos->isEmpty())
            <div class="p-4 text-muted text-center">{{ $filtrosActivos || $estado ? 'No hay pedidos con estos filtros.' : 'No hay pedidos.' }}</div>
        @else
            {{-- Celular: una tarjeta por pedido, sin scroll lateral. --}}
            <div class="list-group list-group-flush d-md-none">
                @foreach($pedidos as $pedido)
                    <a href="{{ route('admin.pedidos.show', $pedido) }}" class="list-group-item list-group-item-action py-3">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div style="min-width: 0;">
                                <div class="fw-semibold text-truncate">{{ $pedido->nombre }} {{ $pedido->apellido }}</div>
                                <div class="small text-muted">
                                    <code>#{{ $pedido->id }}</code> · {{ $pedido->created_at->format('d/m/Y H:i') }}
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                @include('admin.pedidos.partials.estado-badge')
                                @foreach($pedido->totales as $pt)
                                    <div class="small fw-semibold mt-1">{{ $pt->moneda ? $pt->moneda->simbolo : '$' }}{{ number_format($pt->total, 2, ',', '.') }}</div>
                                @endforeach
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    @php $mostrarLocalidad = App\Models\Configuracion::pedirDireccionEnvio(); @endphp
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            @if($mostrarLocalidad)<th>Localidad</th>@endif
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($pedidos as $pedido)
                        <tr>
                            <td><code>#{{ $pedido->id }}</code></td>
                            <td>
                                {{ $pedido->nombre }} {{ $pedido->apellido }}<br>
                                <small class="text-muted">{{ $pedido->celular }}</small>
                            </td>
                            @if($mostrarLocalidad)
                            <td>{{ $pedido->localidad ? $pedido->localidad . ($pedido->provincia ? ', ' . $pedido->provincia : '') : ($pedido->provincia ?: '—') }}</td>
                            @endif
                            <td>
                                @foreach($pedido->totales as $pt)
                                    <div class="lh-sm">{{ $pt->moneda ? $pt->moneda->simbolo : '$' }}{{ number_format($pt->total, 2, ',', '.') }}</div>
                                @endforeach
                            </td>
                            <td>@include('admin.pedidos.partials.estado-badge')</td>
                            <td>{{ $pedido->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.pedidos.show', $pedido) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($pedidos->hasPages())
                <div class="p-3">
                    {{ $pedidos->links('vendor.pagination.tienda') }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>
    #fechas { cursor: pointer; }
    .nav-estados { flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; scrollbar-width: none; }
    .nav-estados::-webkit-scrollbar { display: none; }
    .nav-estados .nav-link { white-space: nowrap; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>
<script>
// Rango de fechas en un solo campo. Al cerrar el calendario con fechas nuevas se
// filtra; con un solo día marcado se toma ese día completo.
(function () {
    var desde   = document.getElementById('desde');
    var hasta   = document.getElementById('hasta');
    var limpiar = document.getElementById('fechas-limpiar');
    var form    = desde.form;
    var inicial = desde.value + '|' + hasta.value;

    function aTexto(fecha) {
        return flatpickr.formatDate(fecha, 'Y-m-d');
    }

    var calendario = flatpickr('#fechas', {
        mode: 'range',
        // "1 oct 2026 – 15 oct 2026": meses abreviados en minúscula, como se escriben en castellano.
        locale: Object.assign({}, flatpickr.l10ns.es, {
            rangeSeparator: ' – ',
            months: {
                shorthand: flatpickr.l10ns.es.months.shorthand.map(function (m) { return m.toLowerCase(); }),
                longhand: flatpickr.l10ns.es.months.longhand
            }
        }),
        dateFormat: 'j M Y',
        defaultDate: [desde.value, hasta.value].filter(Boolean).map(function (v) { return flatpickr.parseDate(v, 'Y-m-d'); }),
        onClose: function (fechas) {
            if (!fechas.length) return;
            desde.value = aTexto(fechas[0]);
            hasta.value = aTexto(fechas[fechas.length - 1]);
            if (desde.value + '|' + hasta.value !== inicial) form.submit();
        }
    });

    document.getElementById('filtros-resumen').textContent = '· ' + (document.getElementById('fechas').value || 'Todas las fechas');

    limpiar.addEventListener('click', function () {
        calendario.clear();
        desde.value = '';
        hasta.value = '';
        form.submit();
    });
})();
</script>
@endpush
