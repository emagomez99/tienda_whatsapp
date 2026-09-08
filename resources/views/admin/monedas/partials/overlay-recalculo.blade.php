{{--
    Aviso de "esto va a tardar" mientras el servidor reescribe los precios.

    Cambiar una cotización dispara un UPDATE sobre todos los productos que trabajan
    con margen en esa moneda: medido, 1,4 s para 30.900 productos. Sin aviso, el
    navegador se queda quieto ese rato y la reacción natural es volver a apretar
    Guardar, que dispara un segundo recálculo encima del primero. Por eso el overlay
    también bloquea el botón: no es sólo cosmético.

    No aparece si la cotización no cambió (renombrar la moneda no recalcula nada) ni
    si no hay productos que recalcular, y espera un instante antes de mostrarse para
    no tirar un flash en los casos que resuelven en milisegundos.

    $productosAfectados llega desde MonedaController::edit.
--}}
<div id="overlay-recalculo"
     class="position-fixed top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center"
     style="background:rgba(33,37,41,.6);z-index:1080;backdrop-filter:blur(2px);">
    <div class="card shadow-lg border-0" style="max-width:22rem;">
        <div class="card-body text-center p-4">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Actualizando…</span>
            </div>
            <h6 class="mb-2">Actualizando precios de los productos</h6>
            <p class="text-muted small mb-0">
                @if($productosAfectados === 1)
                    Se está recalculando 1 producto que trabaja con margen en {{ $moneda->codigo }}.
                @else
                    Se están recalculando {{ number_format($productosAfectados, 0, ',', '.') }} productos
                    que trabajan con margen en {{ $moneda->codigo }}.
                @endif
            </p>
            <p class="text-muted small mb-0 mt-2">No cierres esta ventana.</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        var AFECTADOS = {{ $productosAfectados }};

        if (!AFECTADOS) { return; }

        var form       = document.getElementById('form-moneda');
        var cotizacion = document.getElementById('cotizacion');
        var referencia = document.getElementById('cotizacion_referencia_id');
        var overlay    = document.getElementById('overlay-recalculo');

        if (!form || !cotizacion || !overlay) { return; }

        // La cotización se guarda contra la base, así que para saber si cambió hay
        // que compararla en esa misma escala: pasar de "1500 pesos" a "0,86 euros"
        // no cambia nada y no tiene por qué mostrar el aviso.
        function enBase() {
            var valor = parseFloat(cotizacion.value);
            var opcion = referencia && !referencia.disabled ? referencia.selectedOptions[0] : null;
            var factor = opcion ? parseFloat(opcion.dataset.cotizacion) : 1;

            if (isNaN(valor) || isNaN(factor)) { return null; }

            return valor * factor;
        }

        var original = enBase();
        var timer    = null;

        form.addEventListener('submit', function () {
            var ahora = enBase();

            if (ahora === null || original === null || Math.abs(ahora - original) < 0.000001) { return; }

            // Doble guardado = doble recálculo. Se bloquea apenas se envía, aunque el
            // overlay todavía no se vea.
            var botones = form.querySelectorAll('button[type="submit"]');
            for (var i = 0; i < botones.length; i++) { botones[i].disabled = true; }

            // Un recálculo chico resuelve en milisegundos: mostrar el overlay de
            // inmediato sería un parpadeo más molesto que informativo.
            timer = setTimeout(function () {
                overlay.classList.remove('d-none');
                overlay.classList.add('d-flex');
            }, 250);
        });

        // Si el usuario vuelve con el botón Atrás, el navegador restaura la página
        // desde su caché: sin esto quedaría el overlay tapando todo.
        window.addEventListener('pageshow', function () {
            if (timer) { clearTimeout(timer); }
            overlay.classList.add('d-none');
            overlay.classList.remove('d-flex');
        });
    })();
</script>
@endpush
