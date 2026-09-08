@extends('layouts.admin')

@section('title', 'Monedas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-currency-exchange"></i> Monedas</h3>
    <a href="{{ route('admin.monedas.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nueva
    </a>
</div>

<div class="alert alert-light border d-flex gap-2 py-2 px-3 mb-4">
    <i class="bi bi-info-circle text-primary mt-1"></i>
    <div class="small">
        @if($base)
            <span class="fw-semibold d-block mb-1">Las cotizaciones se escriben en {{ $base->nombre }}.</span>
            <span class="text-muted">
                Cuando decís "el dólar está a 1500", ese 1500 son {{ $base->nombre }}: por eso
                {{ $base->codigo }} es tu moneda de referencia y no se cotiza contra nada.
                Al guardar una cotización se recalculan solos los precios de los productos que
                trabajan con margen de ganancia.
            </span>
        @else
            <span class="fw-semibold d-block mb-1">Falta elegir la moneda de referencia.</span>
            <span class="text-muted">
                Es la moneda en la que vas a escribir todas las cotizaciones. Marcá una para poder
                convertir precios entre monedas.
            </span>
        @endif
    </div>
</div>

@php $filtrosActivos = request()->hasAny(['buscar']); @endphp
<div class="card mb-4">
    <div class="card-header d-flex d-md-none justify-content-between align-items-center py-2 px-3"
         style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#filtros-monedas"
         aria-expanded="{{ $filtrosActivos ? 'true' : 'false' }}">
        <span class="small fw-semibold text-muted"><i class="bi bi-funnel me-1"></i> Filtros{!! $filtrosActivos ? ' <span class="badge bg-primary ms-1" style="font-size:.6rem;">activo</span>' : '' !!}</span>
        <i class="bi bi-chevron-down filtros-chevron" style="transition:transform .2s;{{ $filtrosActivos ? 'transform:rotate(180deg);' : '' }}"></i>
    </div>
    <div class="collapse d-md-block{{ $filtrosActivos ? ' show' : '' }}" id="filtros-monedas">
    <div class="card-body">
        <form action="{{ route('admin.monedas.index') }}" method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre o código..." value="{{ request('buscar') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
        </form>
    </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($monedas->isEmpty())
            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle"></i> No se encontraron monedas.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Moneda</th>
                            <th class="text-end">Cotización</th>
                            <th>Estado</th>
                            <th class="text-center">Productos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monedas as $moneda)
                            @php $enUso = $moneda->productos_count > 0 || $moneda->productos_de_compra_count > 0; @endphp
                            <tr>
                                <td>
                                    <strong>{{ $moneda->nombre }}</strong>
                                    <span class="text-muted">({{ $moneda->codigo }})</span>
                                    <span class="badge bg-light text-muted fw-normal">{{ $moneda->simbolo }}</span>
                                    @if($moneda->es_base)
                                        <span class="badge bg-primary ms-1" title="Las cotizaciones de las demás monedas se escriben en ésta">Referencia</span>
                                    @endif
                                    @if($moneda->es_default)
                                        <span class="badge bg-info ms-1" title="Viene preseleccionada al cargar un producto">Por defecto</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    {{-- La base no se cotiza: el "1" que se guarda es
                                         la unidad de medida, no un valor cargado. --}}
                                    @if($moneda->es_base)
                                        <span class="text-muted">—</span>
                                        <div class="text-muted" style="font-size:.75rem;">Unidad de referencia</div>
                                    @else
                                        <span class="fw-semibold">{{ rtrim(rtrim(number_format($moneda->cotizacion, 6, ',', '.'), '0'), ',') }}</span>
                                        @if($base)
                                            <div class="text-muted" style="font-size:.75rem;">1 {{ $moneda->codigo }} = {{ rtrim(rtrim(number_format($moneda->cotizacion, 6, ',', '.'), '0'), ',') }} {{ $base->codigo }}</div>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    @if($moneda->activa)
                                        <span class="badge bg-success">Activa</span>
                                    @else
                                        <span class="badge bg-secondary">Inactiva</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary" title="Productos que se venden en esta moneda">{{ $moneda->productos_count }}</span>
                                    <span class="badge bg-light text-muted" title="Productos que se compran en esta moneda">{{ $moneda->productos_de_compra_count }}</span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('admin.monedas.edit', $moneda) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @if($moneda->es_base || $enUso)
                                            <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                                    title="{{ $moneda->es_base ? 'Es tu moneda de referencia' : 'Está en uso por productos' }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @else
                                            <form action="{{ route('admin.monedas.destroy', $moneda) }}" method="POST"
                                                  data-confirmar="¿Eliminar la moneda {{ $moneda->nombre }}?"
                                                  data-confirmar-boton="Sí, eliminar">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center">
                {{ $monedas->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Todas las equivalencias, derivadas de la cotización de cada moneda contra la
     base. Se muestran porque una cotización se lee de a pares ("1 dólar son 1500
     pesos"), aunque por debajo se guarde un solo número por moneda: así se pueden
     contrastar con la realidad sin sacar la cuenta a mano. --}}
@if($todas->count() > 1)
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-arrow-left-right"></i> Equivalencias</h6>
        <small class="text-muted">Cada fila dice cuánto vale 1 unidad de esa moneda</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0 text-end">
                <thead class="table-light">
                    <tr>
                        <th class="text-start">1 unidad de…</th>
                        @foreach($todas as $destino)
                            <th>{{ $destino->codigo }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($todas as $origen)
                        <tr>
                            <th class="text-start fw-normal">
                                {{ $origen->nombre }} <span class="text-muted">({{ $origen->codigo }})</span>
                                @if($origen->es_base)
                                    <span class="badge bg-primary ms-1">Referencia</span>
                                @endif
                            </th>
                            @foreach($todas as $destino)
                                @php
                                    // Se usa el factor y no convertirA(): convertir
                                    // redondea a centavos porque mueve dinero, y una
                                    // tasa como 0,00067 se mostraría en cero.
                                    $equivale = ($origen->id === $destino->id || (float) $destino->cotizacion <= 0)
                                        ? null
                                        : App\Support\PrecioVenta::factor($origen->cotizacion, $destino->cotizacion);
                                    // Una cotización puede ser 1500 o 0,00067: los
                                    // decimales se ajustan a la magnitud para que el
                                    // número chico no se muestre como cero.
                                    $decimales = $equivale === null ? 0 : ($equivale >= 100 ? 2 : ($equivale >= 1 ? 4 : 6));
                                @endphp
                                <td class="{{ $equivale === null ? 'table-light' : '' }}">
                                    @if($equivale === null)
                                        <span class="text-muted">—</span>
                                    @else
                                        {{ rtrim(rtrim(number_format($equivale, $decimales, ',', '.'), '0'), ',') }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
