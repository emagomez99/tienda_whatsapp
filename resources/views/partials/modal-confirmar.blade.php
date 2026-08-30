{{--
    Modal de confirmación para acciones destructivas. Reemplaza al confirm() nativo
    del navegador, que no se puede estilar y desentona con el resto de la interfaz.

    Uso: poner data-confirmar en el formulario, con el texto de la pregunta.

        <form method="POST" action="..." data-confirmar="¿Eliminar este producto?">

    Opcionales:
        data-confirmar-detalle="Esta acción no se puede deshacer."
        data-confirmar-boton="Sí, eliminar"

    Sigue el mismo patrón de los modales ya existentes (ver admin/pedidos/show.blade.php).
--}}
<div class="modal fade" id="modalConfirmarAccion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger"></i> Confirmar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p id="modalConfirmarTexto" class="mb-0"></p>
                <p id="modalConfirmarDetalle" class="text-muted small mb-0 mt-2 d-none"></p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="modalConfirmarAceptar">Sí, continuar</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var modalEl = document.getElementById('modalConfirmarAccion');
        if (!modalEl || typeof bootstrap === 'undefined') return;

        var modal        = new bootstrap.Modal(modalEl);
        var texto        = document.getElementById('modalConfirmarTexto');
        var detalle      = document.getElementById('modalConfirmarDetalle');
        var btnAceptar   = document.getElementById('modalConfirmarAceptar');
        var formPendiente = null;

        // Se intercepta en captura y sobre todo el documento para que también
        // alcance a los formularios que se agregan al DOM después de cargar.
        document.addEventListener('submit', function (e) {
            var form = e.target;

            if (!form.hasAttribute || !form.hasAttribute('data-confirmar')) return;
            if (form.dataset.confirmado === '1') return; // ya confirmado: dejarlo pasar

            e.preventDefault();

            formPendiente        = form;
            texto.textContent    = form.getAttribute('data-confirmar');
            btnAceptar.textContent = form.getAttribute('data-confirmar-boton') || 'Sí, continuar';

            var textoDetalle = form.getAttribute('data-confirmar-detalle');
            detalle.textContent = textoDetalle || '';
            detalle.classList.toggle('d-none', !textoDetalle);

            modal.show();
        }, true);

        btnAceptar.addEventListener('click', function () {
            if (!formPendiente) return;

            var form = formPendiente;
            formPendiente = null;
            form.dataset.confirmado = '1';
            modal.hide();

            // requestSubmit respeta la validación del formulario; submit() no existe
            // en navegadores viejos con ese nombre, de ahí el fallback.
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });

        // Si se cancela, el formulario vuelve a pedir confirmación la próxima vez.
        modalEl.addEventListener('hidden.bs.modal', function () {
            formPendiente = null;
        });
    })();
</script>
