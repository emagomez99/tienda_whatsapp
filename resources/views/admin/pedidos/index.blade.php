@extends('layouts.admin')

@section('title', 'Pedidos')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-bag-check"></i> Pedidos</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-pedidos" titulo="Cómo funcionan los pedidos">
            @include('admin.ayudas.pedidos')
        </x-ayuda>
        @if(auth()->user()->puede('pedidos.gestionar'))
            <a href="{{ route('admin.pedidos.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Crear pedido
            </a>
        @endif
    </div>
</div>

{{-- Filtros (el mismo componente de todos los listados): búsqueda arriba, fechas en
     el panel. El estado va aparte, en pestañas con contadores. Sin fechas elegidas
     se ven los últimos 30 días; "Todas" viaja como desde/hasta vacíos. --}}
@php
    $filtrosActivos = request()->filled('buscar') || $rango->estaActivo();
    // Parámetros que se conservan al cambiar de pestaña de estado.
    $conBusqueda = array_filter(['buscar' => request('buscar')]);

    $porDefecto = !request()->query->has('desde') && !request()->query->has('hasta');
    $fichasFechas = [];
    if ($rango->estaActivo()) {
        $formato = function ($texto) { return $texto ? \Carbon\Carbon::parse($texto)->format('d/m/Y') : '…'; };
        $texto = $porDefecto
            ? 'Últimos 30 días'
            : ($rango->desdeTexto() === $rango->hastaTexto() ? 'El ' . $formato($rango->desdeTexto()) : 'Del ' . $formato($rango->desdeTexto()) . ' al ' . $formato($rango->hastaTexto()));
        $todas = array_merge(request()->except(['page', 'desde', 'hasta']), ['desde' => '', 'hasta' => '']);
        $fichasFechas[] = ['Fechas: ' . $texto, route('admin.pedidos.index') . '?' . http_build_query($todas)];
    }
@endphp
<x-filtros :url="route('admin.pedidos.index')" buscar="Buscar por nombre, email, celular o #123" titulo="Filtrar pedidos"
           :conservar="['estado']" :fichas-extra="$fichasFechas" :cantidad-extra="count($fichasFechas)">
    <div>
        <label for="fechas" class="form-label small fw-semibold mb-1 d-block">Fechas</label>
        <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Atajos de fechas">
            <button type="button" class="btn btn-outline-primary {{ $porDefecto ? 'active' : '' }}" id="fechas-30">Últimos 30 días</button>
            <button type="button" class="btn btn-outline-primary {{ !$rango->estaActivo() ? 'active' : '' }}" id="fechas-todas">Todas</button>
        </div>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
            <input type="text" id="fechas" class="form-control bg-white" placeholder="Elegí desde y hasta" readonly>
        </div>
        <small class="text-muted">Tocá el primer día y el último.</small>
        <input type="hidden" name="desde" id="desde" value="{{ $rango->desdeTexto() }}" {{ $porDefecto ? 'disabled' : '' }} data-viaja-vacio>
        <input type="hidden" name="hasta" id="hasta" value="{{ $rango->hastaTexto() }}" {{ $porDefecto ? 'disabled' : '' }} data-viaja-vacio>
    </div>
</x-filtros>

{{-- Estado en pestañas: cada una dice cuántos pedidos hay dentro de la búsqueda y las fechas. --}}
@php
    $pestanas = [
        null         => ['Todos',       $porEstado->sum()],
        'pendiente'  => ['Pendientes',  $porEstado['pendiente'] ?? 0],
        'confirmado' => ['Confirmados', $porEstado['confirmado'] ?? 0],
        'cancelado'  => ['Cancelados',  $porEstado['cancelado'] ?? 0],
    ];
@endphp
<ul class="nav nav-tabs mb-0 nav-desplazable">
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
            {{-- Vacío: que se entienda por qué y cómo salir. Lo más común es que no
                 haya pedidos en los últimos 30 días (lo que se ve al entrar) pero sí antes. --}}
            @php $verTodos = route('admin.pedidos.index', ['desde' => '', 'hasta' => '']); @endphp
            <div class="text-center py-5">
                <i class="bi bi-bag text-muted display-6"></i>
                @if(!$filtrosActivos && !$estado)
                    <p class="fw-semibold mt-2 mb-1">Todavía no recibiste ningún pedido</p>
                    <p class="text-muted small mb-0">Cuando un cliente compre en tu tienda, su pedido aparece acá.</p>
                @elseif($porDefecto && !request()->filled('buscar') && !$estado)
                    <p class="fw-semibold mt-2 mb-1">No hay pedidos en los últimos 30 días</p>
                    <a href="{{ $verTodos }}" class="btn btn-outline-primary btn-sm mt-2">Ver los pedidos de todas las fechas</a>
                @else
                    <p class="fw-semibold mt-2 mb-1">Ningún pedido coincide con lo que buscás</p>
                    <a href="{{ $verTodos }}" class="btn btn-outline-primary btn-sm mt-2">Quitar los filtros y ver todos</a>
                @endif
            </div>
        @else
            {{-- Celular: una tarjeta por pedido, sin scroll lateral. --}}
            <div class="list-group list-group-flush d-md-none">
                @foreach($pedidos as $pedido)
                    <a href="{{ route('admin.pedidos.show', $pedido) }}" class="list-group-item list-group-item-action py-3">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div style="min-width: 0;">
                                <div class="fw-semibold text-truncate">{{ $pedido->nombre }} {{ $pedido->apellido }}</div>
                                <div class="small text-muted">
                                    <span class="cifras">#{{ $pedido->id }}</span> · {{ $pedido->created_at->format('d/m/Y H:i') }}
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                @include('admin.pedidos.partials.estado-badge')
                                @foreach($pedido->totalesVisibles() as $pt)
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
                            <td class="text-muted cifras">#{{ $pedido->id }}</td>
                            <td>
                                {{ $pedido->nombre }} {{ $pedido->apellido }}<br>
                                <small class="text-muted">{{ $pedido->celular }}</small>
                            </td>
                            @if($mostrarLocalidad)
                            <td>{{ $pedido->localidad ? $pedido->localidad . ($pedido->provincia ? ', ' . $pedido->provincia : '') : ($pedido->provincia ?: '—') }}</td>
                            @endif
                            <td>
                                @foreach($pedido->totalesVisibles() as $pt)
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
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>
<script>
// Rango de fechas en el panel de filtros. Se aplica con "Ver resultados".
// "Últimos 30 días" = sin desde/hasta (lo de siempre); "Todas" = desde/hasta vacíos.
(function () {
    var desde = document.getElementById('desde');
    var hasta = document.getElementById('hasta');
    var form  = desde.form;

    function aTexto(fecha) {
        return flatpickr.formatDate(fecha, 'Y-m-d');
    }
    function usar(habilitados) {
        desde.disabled = hasta.disabled = !habilitados;
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
            usar(true);
        }
    });

    document.getElementById('fechas-30').addEventListener('click', function () {
        calendario.clear();
        usar(false);
        form.requestSubmit ? form.requestSubmit() : form.submit();
    });
    document.getElementById('fechas-todas').addEventListener('click', function () {
        calendario.clear();
        desde.value = '';
        hasta.value = '';
        usar(true);
        form.requestSubmit ? form.requestSubmit() : form.submit();
    });
})();
</script>
@endpush
