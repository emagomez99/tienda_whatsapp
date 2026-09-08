{{--
    Tarjeta de precio. Compartida por el alta y la edición.
    $producto viene sólo en edición; $monedas son las monedas activas.

    El precio salió de la tarjeta "Información del Producto" cuando dejó de ser un
    número suelto: ahora son dos decisiones encadenadas (a cuánto se compra, cómo se
    arma la venta) y mezcladas con el nombre y el proveedor no se entendía cuál
    depende de cuál.

    La vista previa es SÓLO una ayuda visual. El precio final lo recalcula siempre el
    servidor (Producto::ajustarPrecioSegunModo): lo que mande el navegador en modo
    margen se descarta.
--}}
@php
    $productoEditado = isset($producto) ? $producto : null;
    $modo            = old('modo_precio_venta', $productoEditado ? $productoEditado->modo_precio_venta : App\Models\Producto::MODO_PRECIO_MANUAL);
    $monedaVentaId   = old('moneda_id', $productoEditado ? $productoEditado->moneda_id : (isset($monedaDefaultId) ? $monedaDefaultId : null));
    $monedaCompraId  = old('moneda_compra_id', $productoEditado ? $productoEditado->moneda_compra_id : null);
    $esMargen        = $modo === App\Models\Producto::MODO_PRECIO_MARGEN;
@endphp

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-cash-coin"></i> Precio
            @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.precio'), 'grande' => true])
        </h5>
    </div>
    <div class="card-body">

        {{-- ─── Compra ─────────────────────────────────────────────────────── --}}
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="fw-semibold text-uppercase text-muted" style="font-size:.75rem;letter-spacing:.04em;">
                <i class="bi bi-box-arrow-in-down"></i> Compra
            </span>
            <span class="badge bg-light text-muted fw-normal">opcional</span>
            @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.compra'), 'grande' => true])
        </div>
        <p class="text-muted small mb-3">{{ __('productos.precio.compra_opcional') }}</p>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="moneda_compra_id" class="form-label">Moneda de compra</label>
                <select class="form-select @error('moneda_compra_id') is-invalid @enderror"
                        id="moneda_compra_id" name="moneda_compra_id">
                    <option value="">Sin declarar</option>
                    @foreach($monedas as $moneda)
                        <option value="{{ $moneda->id }}" {{ $monedaCompraId == $moneda->id ? 'selected' : '' }}>
                            {{ $moneda->nombre }} ({{ $moneda->codigo }})
                        </option>
                    @endforeach
                </select>
                @error('moneda_compra_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label for="precio_compra" class="form-label">Precio de compra</label>
                <div class="input-group">
                    <span class="input-group-text" id="simbolo-compra">$</span>
                    <input type="number" step="0.01" min="0"
                           class="form-control @error('precio_compra') is-invalid @enderror"
                           id="precio_compra" name="precio_compra"
                           value="{{ old('precio_compra', $productoEditado ? $productoEditado->precio_compra : '') }}">
                </div>
                @error('precio_compra')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <hr class="my-4">

        {{-- ─── Venta ──────────────────────────────────────────────────────── --}}
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="fw-semibold text-uppercase text-muted" style="font-size:.75rem;letter-spacing:.04em;">
                <i class="bi bi-tag"></i> Venta
            </span>
            @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.venta'), 'grande' => true])
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="moneda_id" class="form-label">
                    Moneda de venta
                    @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.moneda')])
                </label>
                <select class="form-select @error('moneda_id') is-invalid @enderror" id="moneda_id" name="moneda_id">
                    <option value="">Sin moneda</option>
                    @foreach($monedas as $moneda)
                        <option value="{{ $moneda->id }}" {{ $monedaVentaId == $moneda->id ? 'selected' : '' }}>
                            {{ $moneda->nombre }} ({{ $moneda->codigo }})
                        </option>
                    @endforeach
                </select>
                @error('moneda_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label d-block">Cómo se arma el precio</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="modo_precio_venta" id="modo-manual"
                           value="{{ App\Models\Producto::MODO_PRECIO_MANUAL }}" {{ $esMargen ? '' : 'checked' }}>
                    <label class="btn btn-outline-primary" for="modo-manual">
                        <i class="bi bi-pin-angle"></i> Precio fijo
                    </label>

                    <input type="radio" class="btn-check" name="modo_precio_venta" id="modo-margen"
                           value="{{ App\Models\Producto::MODO_PRECIO_MARGEN }}" {{ $esMargen ? 'checked' : '' }}>
                    <label class="btn btn-outline-primary" for="modo-margen">
                        <i class="bi bi-percent"></i> Por margen
                    </label>
                </div>
                @error('modo_precio_venta')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row" id="bloque-manual" style="{{ $esMargen ? 'display:none;' : '' }}">
            <div class="col-md-6 mb-3">
                <label for="precio" class="form-label">Precio de venta *</label>
                <div class="input-group">
                    <span class="input-group-text simbolo-venta">$</span>
                    <input type="number" step="0.01" min="0"
                           class="form-control @error('precio') is-invalid @enderror"
                           id="precio" name="precio"
                           value="{{ old('precio', $productoEditado ? $productoEditado->precio : 0) }}">
                </div>
                @error('precio')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row" id="bloque-margen" style="{{ $esMargen ? '' : 'display:none;' }}">
            <div class="col-md-6 mb-3">
                <label for="margen_ganancia" class="form-label">
                    Margen de ganancia *
                    @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.margen_ganancia'), 'grande' => true])
                </label>
                <div class="input-group">
                    <input type="number" step="0.01" min="0"
                           class="form-control @error('margen_ganancia') is-invalid @enderror"
                           id="margen_ganancia" name="margen_ganancia"
                           value="{{ old('margen_ganancia', $productoEditado ? $productoEditado->margen_ganancia : '') }}">
                    <span class="input-group-text">%</span>
                </div>
                @error('margen_ganancia')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Precio de venta calculado</label>
                <div class="form-control bg-light fw-semibold" id="precio-calculado">—</div>
                <small class="text-muted" id="detalle-calculo">{{ __('productos.precio.calculado_en_servidor') }}</small>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Vista previa del precio por margen. Replica la cuenta del servidor
    // (App\Support\PrecioVenta) sólo para que el usuario vea el resultado mientras
    // escribe: el número que se guarda lo calcula siempre el servidor.
    (function () {
        var MONEDAS = @json($monedas->mapWithKeys(function ($m) {
            return [$m->id => ['codigo' => $m->codigo, 'simbolo' => $m->simbolo, 'cotizacion' => (float) $m->cotizacion]];
        }));

        var modoManual   = document.getElementById('modo-manual');
        var modoMargen   = document.getElementById('modo-margen');
        var bloqueManual = document.getElementById('bloque-manual');
        var bloqueMargen = document.getElementById('bloque-margen');
        var monedaCompra = document.getElementById('moneda_compra_id');
        var monedaVenta  = document.getElementById('moneda_id');
        var precioCompra = document.getElementById('precio_compra');
        var margen       = document.getElementById('margen_ganancia');
        var resultado    = document.getElementById('precio-calculado');
        var detalle      = document.getElementById('detalle-calculo');
        var simboloCompra = document.getElementById('simbolo-compra');

        function moneda(select) {
            return select.value ? MONEDAS[select.value] : null;
        }

        function formatear(numero, simbolo) {
            return simbolo + numero.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function calcular() {
            var compra = moneda(monedaCompra);
            var venta  = moneda(monedaVenta);

            simboloCompra.textContent = compra ? compra.simbolo : '$';
            document.querySelectorAll('.simbolo-venta').forEach(function (el) {
                el.textContent = venta ? venta.simbolo : '$';
            });

            if (!modoMargen.checked) return;

            var costo  = parseFloat(precioCompra.value);
            var puntos = parseFloat(margen.value);

            if (!compra || !venta || isNaN(costo) || isNaN(puntos)) {
                resultado.textContent = '—';
                detalle.textContent = @json(__('productos.precio.faltan_datos'));
                return;
            }

            var factor     = compra.cotizacion === venta.cotizacion ? 1 : round(compra.cotizacion / venta.cotizacion, 6);
            var convertido = round(costo * factor, 2);
            var precio     = round(convertido * (1 + puntos / 100), 2);

            resultado.textContent = formatear(precio, venta.simbolo);
            detalle.textContent = compra.codigo === venta.codigo
                ? 'Costo ' + formatear(costo, compra.simbolo) + ' + ' + puntos + '%'
                : '1 ' + compra.codigo + ' = ' + factor + ' ' + venta.codigo + ' → costo ' +
                  formatear(convertido, venta.simbolo) + ' + ' + puntos + '%';
        }

        function round(valor, decimales) {
            var f = Math.pow(10, decimales);
            // toPrecision(15) antes de redondear: sin eso, 1.005 binario cae apenas
            // por debajo del medio y JS redondearía para el otro lado que PHP.
            return Math.round(parseFloat((valor * f).toPrecision(15))) / f;
        }

        function alternarModo() {
            bloqueManual.style.display = modoMargen.checked ? 'none' : '';
            bloqueMargen.style.display = modoMargen.checked ? '' : 'none';
            calcular();
        }

        [modoManual, modoMargen].forEach(function (el) { el.addEventListener('change', alternarModo); });
        [monedaCompra, monedaVenta, precioCompra, margen].forEach(function (el) {
            el.addEventListener('input', calcular);
            el.addEventListener('change', calcular);
        });

        calcular();
    })();
</script>
@endpush
