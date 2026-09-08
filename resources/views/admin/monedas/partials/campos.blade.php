{{--
    Campos de la moneda. Compartidos por el alta y la edición.
    $moneda viene sólo en edición; $base es la moneda base actual (puede no haber).

    Van en un partial y no copiados en los dos blades porque son las mismas reglas de
    negocio explicadas al usuario: cuando el texto vive por duplicado, la explicación
    termina existiendo sólo en la edición y faltando justo en el alta.
--}}
@php
    $monedaEditada = isset($moneda) ? $moneda : null;
    // En qué moneda cobra la tienda se contesta una sola vez, en Ajustes, y no es un
    // campo de este formulario: acá sólo se sabe si ESTA es la elegida, para no
    // pedirle una cotización contra sí misma.
    $esBase        = $monedaEditada ? $monedaEditada->es_base : false;
    $codigoBase    = $base ? $base->codigo : 'la moneda de la tienda';
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="nombre" class="form-label">Nombre *</label>
        <input type="text" class="form-control @error('nombre') is-invalid @enderror"
               id="nombre" name="nombre" value="{{ old('nombre', $monedaEditada ? $monedaEditada->nombre : '') }}"
               placeholder="Ej: Dólar Estadounidense" required>
        @error('nombre')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-3 mb-3">
        <label for="codigo" class="form-label">Código *</label>
        <input type="text" class="form-control text-uppercase @error('codigo') is-invalid @enderror"
               id="codigo" name="codigo" maxlength="3"
               value="{{ old('codigo', $monedaEditada ? $monedaEditada->codigo : '') }}"
               placeholder="USD" required>
        @error('codigo')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">3 letras (ISO).</div>
    </div>
    <div class="col-md-3 mb-3">
        <label for="simbolo" class="form-label">Símbolo *</label>
        <input type="text" class="form-control @error('simbolo') is-invalid @enderror"
               id="simbolo" name="simbolo" maxlength="3"
               value="{{ old('simbolo', $monedaEditada ? $monedaEditada->simbolo : '') }}"
               placeholder="U$S" required>
        @error('simbolo')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Se antepone al precio.</div>
    </div>
</div>

<div class="mb-3">
    {{-- La moneda en la que cobra la tienda no se cotiza: es la unidad de medida.
         Decir "1 peso equivale a 0,00058 euros" es cierto, pero no es una cotización
         que nadie cargue, y un campo vacío invita a completarlo. Se muestra un bloque
         o el otro según el caso, resuelto en el servidor: ya no hay nada que alternar
         en vivo porque el switch dejó de existir. --}}
    @if(!$esBase)
        <label for="cotizacion" class="form-label">Cotización *</label>
        <div class="input-group">
            <span class="input-group-text">1 <span id="cotizacion-codigo" class="fw-semibold ms-1">{{ $monedaEditada ? $monedaEditada->codigo : '—' }}</span></span>
            <span class="input-group-text">equivale a</span>
            <input type="number" step="0.000001" min="0.000001"
                   class="form-control @error('cotizacion') is-invalid @enderror"
                   id="cotizacion" name="cotizacion"
                   value="{{ old('cotizacion', $monedaEditada ? rtrim(rtrim($monedaEditada->cotizacion, '0'), '.') : '1') }}"
                   required>
            <select class="form-select" id="cotizacion_referencia_id" name="cotizacion_referencia_id"
                    style="max-width:14rem;">
                @foreach($monedas as $m)
                    @if(!$monedaEditada || $m->id !== $monedaEditada->id)
                        <option value="{{ $m->id }}"
                                data-cotizacion="{{ $m->cotizacion }}"
                                {{ old('cotizacion_referencia_id', $base ? $base->id : null) == $m->id ? 'selected' : '' }}>
                            {{ $m->nombre }} ({{ $m->codigo }})
                        </option>
                    @endif
                @endforeach
            </select>
        </div>
        @error('cotizacion')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @error('cotizacion_referencia_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <div class="form-text" id="cotizacion-ayuda">
            Cambiá la moneda de la derecha y el número se reexpresa solo.
        </div>
        <div class="mt-2 small text-muted" id="equivalencias"></div>
    @else
        {{-- La validación exige la cotización; para la moneda de la tienda vale 1 por
             definición y el modelo la fuerza igual. Va oculta para no pedir un dato
             que no se elige. --}}
        <input type="hidden" name="cotizacion" value="1">

        <div class="alert alert-light border mb-0 py-2 px-3">
            <i class="bi bi-info-circle text-primary"></i>
            <span class="small">
                <strong>Es la moneda en la que cobrás: no se cotiza.</strong>
                Las demás se cotizan contra ella. Se cambia desde Configuración → Ajustes.
            </span>
        </div>
    @endif
</div>

<div class="mb-3">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="activa" name="activa" value="1"
               {{ old('activa', $monedaEditada ? $monedaEditada->activa : true) ? 'checked' : '' }}>
        <label class="form-check-label" for="activa">Activa</label>
    </div>
    <div class="form-text">Sólo las monedas activas se pueden elegir al cargar un producto.</div>
</div>

@if(!$esBase)
@push('scripts')
<script>
    (function () {
        // Cotizaciones en la escala interna (contra la base), para reexpresar el
        // número cuando cambia la moneda de referencia y para mostrar las
        // equivalencias derivadas. El cálculo que vale es el del servidor.
        var MONEDAS = @json($monedas->mapWithKeys(function ($m) {
            return [$m->id => ['codigo' => $m->codigo, 'cotizacion' => (float) $m->cotizacion]];
        }));

        var cotizacion  = document.getElementById('cotizacion');
        var referencia  = document.getElementById('cotizacion_referencia_id');
        var codigo      = document.getElementById('codigo');
        var etiqueta    = document.getElementById('cotizacion-codigo');
        var salida      = document.getElementById('equivalencias');
        var refAnterior = referencia.value;

        function formatear(n) {
            // Una cotización puede ser 1500 o 0,00067: los decimales se recortan
            // según la magnitud, o los números chicos se muestran como cero.
            var decimales = Math.abs(n) >= 100 ? 2 : (Math.abs(n) >= 1 ? 4 : 6);
            return n.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: decimales });
        }

        /** Cotización de esta moneda contra la base, según lo que hay cargado. */
        function enBase() {
            var valor = parseFloat(cotizacion.value);
            var ref   = MONEDAS[referencia.value];
            if (isNaN(valor) || !ref) { return null; }
            return valor * ref.cotizacion;
        }

        function pintarEquivalencias() {
            var propia = enBase();
            var sigla  = (codigo.value || '—').toUpperCase();

            if (propia === null || propia <= 0) { salida.textContent = ''; return; }

            var lineas = [];
            Object.keys(MONEDAS).forEach(function (id) {
                var m = MONEDAS[id];
                if (m.codigo === sigla || !m.cotizacion) { return; }
                lineas.push('1 ' + sigla + ' = ' + formatear(propia / m.cotizacion) + ' ' + m.codigo);
            });

            salida.textContent = lineas.length ? lineas.join('  ·  ') : '';
        }

        // Al cambiar la referencia el número se reexpresa para que la cotización real
        // no se mueva: pasar de "1500 pesos" a euros tiene que mostrar 0,86, no 1500.
        function reexpresar() {
            var anterior = MONEDAS[refAnterior];
            var nueva    = MONEDAS[referencia.value];
            var valor    = parseFloat(cotizacion.value);

            if (anterior && nueva && !isNaN(valor) && nueva.cotizacion) {
                cotizacion.value = parseFloat(((valor * anterior.cotizacion) / nueva.cotizacion).toFixed(6));
            }

            refAnterior = referencia.value;
            pintarEquivalencias();
        }

        referencia.addEventListener('change', reexpresar);
        cotizacion.addEventListener('input', pintarEquivalencias);
        codigo.addEventListener('input', function () {
            etiqueta.textContent = (codigo.value || '—').toUpperCase();
            pintarEquivalencias();
        });

        etiqueta.textContent = (codigo.value || '—').toUpperCase();
        pintarEquivalencias();
    })();
</script>
@endpush
@endif
