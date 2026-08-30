{{--
    Bloquea la sección de Etiquetas mientras no haya proveedor elegido.

    Hace falta porque las etiquetas dependen del proveedor de tres maneras:
      - la lista de etiquetas disponibles se filtra según el proveedor,
      - las obligatorias del proveedor se agregan solas al elegirlo,
      - y al cambiar de proveedor se limpian todas las filas cargadas.

    Sin bloqueo se puede cargar etiquetas antes de elegir proveedor y perderlas
    en silencio: limpiarTodasEtiquetas() las borra al seleccionarlo.

    Se incluye dentro del card-body de Etiquetas, antes del contenedor de filas.
--}}
<div id="etiquetas-bloqueo" class="text-center text-muted py-4 d-none">
    <i class="bi bi-arrow-up-circle fs-4 d-block mb-2 opacity-50"></i>
    <div class="small">
        Elegí primero un <strong>proveedor</strong>.<br>
        Las etiquetas disponibles dependen de él, y algunas pueden ser obligatorias.
    </div>
</div>

@push('scripts')
<script>
    (function () {
        var proveedor = document.getElementById('proveedor_id');
        var aviso     = document.getElementById('etiquetas-bloqueo');
        var filas     = document.getElementById('etiquetas-container');
        var agregar   = document.getElementById('agregar-etiqueta');
        var pista     = document.getElementById('etiquetas-hint');
        if (!proveedor || !aviso || !filas) return;

        function aplicar() {
            var hay = proveedor.value !== '';

            aviso.classList.toggle('d-none', hay);
            filas.classList.toggle('d-none', !hay);

            // La pista explica cómo cargar etiquetas: sin proveedor no aplica todavía.
            if (pista) {
                pista.classList.toggle('d-none', !hay);
            }

            if (agregar) {
                agregar.disabled = !hay;
                agregar.classList.toggle('disabled', !hay);
            }

            // Los campos ocultos se deshabilitan para que no viajen en el envío:
            // sin proveedor no hay etiqueta válida que mandar.
            filas.querySelectorAll('select, input').forEach(function (campo) {
                campo.disabled = !hay;
            });
        }

        proveedor.addEventListener('change', aplicar);
        aplicar();
    })();
</script>
@endpush
