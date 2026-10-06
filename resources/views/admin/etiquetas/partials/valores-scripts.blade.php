{{-- Muestra y oculta el formulario de renombre de cada fila (ver valor-fila). --}}
<style>
    .valor-fila:hover { background: #f8f9fa; }
    .min-w-0 { min-width: 0; }
</style>
<script>
document.addEventListener('click', function (e) {
    var editar   = e.target.closest('[data-valor-editar]');
    var cancelar = e.target.closest('[data-valor-cancelar]');
    if (!editar && !cancelar) return;

    var fila = (editar || cancelar).closest('.valor-fila');
    var form = fila.querySelector('.valor-renombrar');

    fila.querySelector('.valor-vista').classList.toggle('d-none', !!editar);
    fila.querySelector('.valor-acciones').classList.toggle('d-none', !!editar);
    form.classList.toggle('d-none', !editar);

    if (editar) {
        var input = form.querySelector('input[name="valor"]');
        input.focus();
        input.select();
    } else {
        form.reset();
    }
});
</script>
