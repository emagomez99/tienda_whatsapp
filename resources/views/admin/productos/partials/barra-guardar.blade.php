{{--
    Barra fija al pie del formulario de producto, con el mismo criterio que la de
    Ajustes: los botones quedan siempre a mano, sin tener que subir a buscarlos.

    Además avisa si hay cambios sin guardar y pide confirmación antes de perderlos al
    salir por un enlace (Cancelar, Volver, el menú) o al enviar otro formulario de la
    página (ej. el ajuste de stock, que recarga).

    Parámetros:
      $textoGuardar — texto del botón principal
      $cancelarUrl  — adónde vuelve "Cancelar"
--}}
<div class="position-sticky bottom-0 bg-white border-top py-2 mt-3" style="z-index: 5;">
    <div class="d-flex justify-content-end align-items-center gap-2">
        <span class="text-warning-emphasis small me-auto d-none" id="cambios-sin-guardar">
            <i class="bi bi-circle-fill" style="font-size:.5rem; vertical-align:middle;"></i> Cambios sin guardar
        </span>
        <a href="{{ $cancelarUrl }}" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check2"></i> {{ $textoGuardar }}
        </button>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var form   = document.getElementById('form-producto');
    var aviso  = document.getElementById('cambios-sin-guardar');
    if (!form || !aviso) return;

    var inicial = null, saliendo = false, espera = null;

    /**
     * Lo que se enviaría, más el texto de los editores enriquecidos (Quill escribe en
     * su campo oculto recién al enviar). Comparar contra la foto inicial, en vez de
     * marcar "sucio" con el primer evento, hace que deshacer un cambio lo limpie.
     */
    function foto() {
        var partes = [];
        new FormData(form).forEach(function (valor, clave) {
            if (clave === '_token') return;
            partes.push(clave + '=' + (valor instanceof File ? valor.name + ':' + valor.size : valor));
        });
        form.querySelectorAll('[contenteditable="true"]').forEach(function (editor) {
            partes.push('editor=' + editor.innerHTML);
        });
        return partes.join('&');
    }

    function hayCambios() {
        return inicial !== null && !saliendo && foto() !== inicial;
    }

    function revisar() {
        clearTimeout(espera);
        espera = setTimeout(function () {
            aviso.classList.toggle('d-none', !hayCambios());
        }, 150);
    }

    // La foto se toma cuando los componentes ya armaron sus campos (etiquetas,
    // editor, imágenes): antes, el propio armado contaría como cambio.
    window.addEventListener('load', function () {
        setTimeout(function () { inicial = foto(); }, 300);
    });

    ['input', 'change', 'click', 'keyup'].forEach(function (evento) {
        form.addEventListener(evento, revisar, true);
    });

    // Hay campos que se completan solos sin disparar eventos (la dirección web se
    // recalcula en el servidor a partir del nombre): una pasada por segundo los cubre.
    setInterval(revisar, 1000);

    form.addEventListener('submit', function () { saliendo = true; });

    function confirmarSalida(continuar) {
        window.confirmarAccion({
            texto:   'Hay cambios sin guardar.',
            detalle: 'Si salís ahora se pierden.',
            boton:   'Salir sin guardar'
        }, function () {
            saliendo = true;
            continuar();
        });
    }

    // Enlaces que sacan de la página: Cancelar, Volver, el menú lateral...
    document.addEventListener('click', function (e) {
        var enlace = e.target.closest('a[href]');
        if (!enlace || !hayCambios()) return;

        var href = enlace.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
        if (enlace.target === '_blank' || e.ctrlKey || e.metaKey || e.shiftKey) return;

        e.preventDefault();
        confirmarSalida(function () { window.location.href = enlace.href; });
    }, true);

    // Otros formularios de la página (ej. ajustar stock) recargan y perderían lo editado.
    document.addEventListener('submit', function (e) {
        var otro = e.target;
        // Los que ya piden confirmación propia (data-confirmar) no se encadenan con esta.
        if (otro === form || otro.hasAttribute('data-confirmar')) return;
        if (otro.dataset.salidaConfirmada === '1' || !hayCambios()) return;

        e.preventDefault();
        e.stopImmediatePropagation();
        confirmarSalida(function () {
            otro.dataset.salidaConfirmada = '1';
            if (typeof otro.requestSubmit === 'function') {
                otro.requestSubmit();
            } else {
                otro.submit();
            }
        });
    }, true);

    // Cerrar la pestaña o recargar: el navegador no deja mostrar un modal propio en
    // ese momento, sólo su aviso estándar.
    window.addEventListener('beforeunload', function (e) {
        if (!hayCambios()) return;
        e.preventDefault();
        e.returnValue = '';
    });
})();
</script>
@endpush
