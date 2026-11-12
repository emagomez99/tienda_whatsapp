@extends('layouts.admin')

@section('title', 'Importar productos')

{{--
    Una planilla subida. Sin aplicar: qué va a pasar (cuántos se crean y actualizan,
    qué cambia en los que ya existen, con el antes y el después, errores por fila
    (se bajan en Excel para corregirlos), lo que se va a crear y los valores parecidos), con "Aplicar" y
    "No aplicar". Aplicando: la barra de avance, que pide la siguiente tanda hasta
    terminar (ver ImportacionController::aplicar). Terminada: el resultado.
--}}
@php
    $r = $importacion->resumen ?: [];
    $sinAplicar = $importacion->estado === App\Models\Importacion::REVISANDO;
    $aplicando = $importacion->estado === App\Models\Importacion::APLICANDO;
    $cuenta = function ($n, $uno, $varios) { return number_format($n, 0, ',', '.') . ' ' . ($n === 1 ? $uno : $varios); };
@endphp
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h3 class="mb-0"><i class="bi bi-file-earmark-spreadsheet"></i> {{ $importacion->nombre_archivo }}</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-importar" titulo="Cómo importar con Excel">
            @include('admin.ayudas.importar')
        </x-ayuda>
        <a href="{{ route('admin.importaciones.index') }}" class="btn btn-outline-secondary" title="Volver">
            <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
        </a>
    </div>
</div>
<p class="text-muted mb-4">
    Subida el {{ $importacion->created_at->format('d/m/Y H:i') }}{{ $importacion->usuario ? ' por ' . $importacion->usuario->name : '' }}
    · @include('admin.importaciones.partials.estado', ['estado' => $importacion->estado])
</p>

{{-- Los números grandes: qué va a pasar (o qué pasó) --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">{{ $sinAplicar ? 'Se van a crear' : 'Creados' }}</div>
            <div class="fs-3 fw-semibold" id="numero-creados">{{ number_format($sinAplicar ? ($r['crear'] ?? 0) : $importacion->creados, 0, ',', '.') }}</div>
            <div class="small text-muted">productos nuevos</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">{{ $sinAplicar ? 'Se van a actualizar' : 'Actualizados' }}</div>
            <div class="fs-3 fw-semibold" id="numero-actualizados">{{ number_format($sinAplicar ? ($r['actualizar'] ?? 0) : $importacion->actualizados, 0, ',', '.') }}</div>
            <div class="small text-muted">productos que ya tenías (por su código o su nombre)</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Quedan igual</div>
            <div class="fs-3 fw-semibold">{{ number_format($importacion->sinCambios(), 0, ',', '.') }}</div>
            <div class="small text-muted">la planilla no les cambia nada: no se tocan</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        @php $errores = $sinAplicar ? ($r['con_error'] ?? 0) : $importacion->con_error; @endphp
        <div class="card h-100 {{ $errores ? 'border-warning' : '' }}"><div class="card-body">
            <div class="text-muted small">Con algo para corregir</div>
            <div class="fs-3 fw-semibold {{ $errores ? 'text-warning-emphasis' : '' }}" id="numero-errores">{{ number_format($errores, 0, ',', '.') }}</div>
            <div class="small text-muted">{{ $sinAplicar ? 'no se cargan: mirá abajo por qué' : 'filas que no se cargaron' }}</div>
        </div></div>
    </div>
</div>

@if($sinAplicar)
    {{-- Lo que conviene mirar antes de aplicar --}}
    @foreach($r['avisos'] ?? [] as $aviso)
        <div class="alert alert-info py-2 small"><i class="bi bi-info-circle"></i> {{ $aviso }}</div>
    @endforeach

    {{-- Qué cambia en los productos que ya tenía: cuántos por dato, y los primeros con su antes y después --}}
    @if(!empty($r['cambios']))
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-arrow-left-right"></i> Qué va a cambiar en los productos que ya tenés</div>
            <div class="card-body pb-2">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($r['cambios'] as $campo => $cuantos)
                        <span class="badge rounded-pill text-bg-light border fw-normal fs-6">{{ $campo }}: <strong>{{ $cuenta($cuantos, 'producto', 'productos') }}</strong></span>
                    @endforeach
                </div>
                <div class="small text-muted mt-2">Mirá que sea lo que esperabas. Si ves un dato que no querías cambiar, tocá «No aplicar» y revisá la planilla.</div>
            </div>
            <div class="table-responsive" style="max-height: 360px;">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th style="width: 70px;">Fila</th><th>Producto</th><th>Dato</th><th>Ahora</th><th>Va a quedar</th></tr></thead>
                    <tbody>
                        @foreach($r['ejemplos'] ?? [] as $cambio)
                            <tr>
                                <td>{{ $cambio['fila'] }}</td>
                                <td>{{ $cambio['producto'] }}</td>
                                <td>{{ $cambio['campo'] }}</td>
                                <td class="text-muted">{{ $cambio['antes'] !== '' ? $cambio['antes'] : '—' }}</td>
                                <td><strong>{{ $cambio['despues'] !== '' ? $cambio['despues'] : '—' }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(array_sum($r['cambios']) > count($r['ejemplos'] ?? []))
                <div class="card-body small text-muted py-2">Se muestran los primeros {{ count($r['ejemplos'] ?? []) }} cambios de {{ number_format(array_sum($r['cambios']), 0, ',', '.') }}.</div>
            @endif
        </div>
    @endif

    @if(!empty($r['parecidos']))
        <div class="card mb-3 border-warning">
            <div class="card-header"><i class="bi bi-spellcheck text-warning-emphasis"></i> ¿Error de tipeo? Valores nuevos que se parecen a uno que ya usás</div>
            <div class="card-body small text-muted pb-0">Si los cargás así, vas a tener los dos y los filtros de la tienda los muestran separados. Corregilos en la planilla y volvé a subirla, o seguí si son distintos de verdad.</div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="table-light"><tr><th>Fila</th><th>Característica</th><th>Escribiste</th><th>Ya existe</th></tr></thead>
                    <tbody>
                        @foreach($r['parecidos'] as $p)
                            <tr><td>{{ $p['fila'] }}</td><td>{{ $p['caracteristica'] }}</td><td><strong>{{ $p['escrito'] }}</strong></td><td>{{ $p['existente'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if(!empty($r['proveedores_nuevos']) || !empty($r['categorias_nuevas']))
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-plus-circle"></i> Se van a crear</div>
            <div class="card-body small">
                @if(!empty($r['proveedores_nuevos']))
                    <div class="mb-2"><strong>Proveedores:</strong> {{ implode(', ', $r['proveedores_nuevos']) }}</div>
                @endif
                @if(!empty($r['categorias_nuevas']))
                    <div><strong>Categorías:</strong> {{ implode(', ', $r['categorias_nuevas']) }}</div>
                @endif
                <div class="text-muted mt-2">Si alguno está mal escrito, corregilo en la planilla para no duplicarlo.</div>
            </div>
        </div>
    @endif
@endif

{{-- Errores por fila: del análisis y, después, los que aparecieron al aplicar --}}
@php $listaErrores = array_merge($r['errores'] ?? [], $r['errores_al_aplicar'] ?? []); @endphp
@if($listaErrores)
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="bi bi-exclamation-triangle text-warning-emphasis"></i> Filas para corregir</span>
            <a href="{{ route('admin.importaciones.errores', $importacion) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-download"></i> Bajar estas filas en Excel
            </a>
        </div>
        <div class="card-body small text-muted pb-0">Estas filas no se cargan. Bajalas en Excel (la primera columna dice qué corregir en cada una), arreglalas ahí y subí esa planilla: el resto no hace falta subirlo de nuevo.</div>
        <div class="table-responsive" style="max-height: 420px;">
            <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th style="width: 70px;">Fila</th><th>Qué pasa</th></tr></thead>
                <tbody>
                    @foreach($listaErrores as $error)
                        <tr><td>{{ $error['fila'] }}</td><td>{{ $error['mensaje'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(($r['con_error'] ?? 0) > count($r['errores'] ?? []))
            <div class="card-body small text-muted pt-2">Se muestran los primeros {{ count($r['errores'] ?? []) }}.</div>
        @endif
    </div>
@endif

{{-- Avance (al aplicar) --}}
<div class="card mb-3 {{ ($sinAplicar || $importacion->terminada()) ? 'd-none' : '' }}" id="avance">
    <div class="card-body">
        <div class="d-flex justify-content-between small mb-1">
            <span id="avance-texto">Aplicando…</span>
            <span id="avance-numeros">{{ $importacion->procesadas }} de {{ $importacion->total }}</span>
        </div>
        <div class="progress" role="progressbar" aria-label="Avance de la importación">
            <div class="progress-bar" id="avance-barra" style="width: {{ $importacion->porcentaje() }}%"></div>
        </div>
        <div class="small text-muted mt-2">No cierres esta página hasta que termine. Si se corta, volvé a entrar y tocá «Seguir».</div>
    </div>
</div>

@if($importacion->estado === App\Models\Importacion::TERMINADA)
    <div class="alert alert-success d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-check-circle"></i> Listo: {{ $cuenta($importacion->creados, 'producto creado', 'productos creados') }} y {{ $cuenta($importacion->actualizados, 'actualizado', 'actualizados') }}.
        <a href="{{ route('admin.productos.index') }}" class="alert-link ms-auto">Ver los productos</a>
    </div>
@endif

{{-- Acciones --}}
<div class="d-flex flex-wrap gap-2">
    @if($sinAplicar)
        @php $aCargar = ($r['crear'] ?? 0) + ($r['actualizar'] ?? 0); @endphp
        @if($aCargar > 0)
            <button type="button" class="btn btn-primary" id="boton-aplicar"
                    data-texto="{{ 'Se van a crear ' . $cuenta($r['crear'] ?? 0, 'producto', 'productos') . ' y actualizar ' . $cuenta($r['actualizar'] ?? 0, 'producto', 'productos') . '.' }}"
                    data-detalle="{{ ($r['con_error'] ?? 0) ? $cuenta($r['con_error'], 'fila con error no se carga', 'filas con error no se cargan') . '.' : '' }}">
                <i class="bi bi-check2"></i> Aplicar la planilla
            </button>
        @elseif(($r['con_error'] ?? 0) === 0)
            <div class="alert alert-success w-100 mb-0"><i class="bi bi-check-circle"></i> Tus productos ya están como dice la planilla: no hay nada para cambiar.</div>
        @endif
        <form action="{{ route('admin.importaciones.cancelar', $importacion) }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">{{ $aCargar > 0 ? 'No aplicar' : 'Volver' }}</button>
        </form>
    @elseif($aplicando)
        <button type="button" class="btn btn-primary" id="boton-seguir"><i class="bi bi-play"></i> Seguir</button>
    @endif
</div>

@if($sinAplicar || $aplicando)
@push('scripts')
<script>
(function () {
    var URL_APLICAR = @json(route('admin.importaciones.aplicar', $importacion));
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var avance = document.getElementById('avance');
    var formato = function (n) { return n.toLocaleString('es-AR'); };

    /** Pide la siguiente tanda hasta terminar, mostrando el avance. */
    function seguir() {
        avance.classList.remove('d-none');
        document.querySelectorAll('#boton-aplicar, #boton-seguir').forEach(function (b) { b.disabled = true; });

        fetch(URL_APLICAR, { method: 'POST', credentials: 'same-origin',
                             headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token } })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, datos: d }; }); })
            .then(function (respuesta) {
                if (!respuesta.ok) { throw new Error(respuesta.datos.error || 'error'); }
                var d = respuesta.datos;
                document.getElementById('avance-barra').style.width = d.porcentaje + '%';
                document.getElementById('avance-numeros').textContent = formato(d.procesadas) + ' de ' + formato(d.total);
                document.getElementById('numero-creados').textContent = formato(d.creados);
                document.getElementById('numero-actualizados').textContent = formato(d.actualizados);
                document.getElementById('numero-errores').textContent = formato(d.con_error);
                if (d.estado === 'terminada') {
                    document.getElementById('avance-texto').textContent = 'Terminado';
                    location.reload();   // el resultado, con los errores que hubo al aplicar
                } else {
                    seguir();
                }
            })
            .catch(function () {
                document.getElementById('avance-texto').textContent = 'Se cortó. Revisá la conexión y tocá «Seguir»: retoma desde donde quedó.';
                document.querySelectorAll('#boton-aplicar, #boton-seguir').forEach(function (b) {
                    b.disabled = false;
                    b.innerHTML = '<i class="bi bi-play"></i> Seguir';
                });
            });
    }

    // "Aplicar" pide confirmación una sola vez; si se corta y se vuelve a tocar, sigue.
    var confirmado = false;
    var aplicar = document.getElementById('boton-aplicar');
    if (aplicar) {
        aplicar.addEventListener('click', function () {
            if (confirmado) { seguir(); return; }
            window.confirmarAccion({ texto: aplicar.dataset.texto, detalle: aplicar.dataset.detalle || null, boton: 'Sí, aplicar' }, function () {
                confirmado = true;
                seguir();
            });
        });
    }
    var seguirBoton = document.getElementById('boton-seguir');
    if (seguirBoton) seguirBoton.addEventListener('click', seguir);
})();
</script>
@endpush
@endif
@endsection
