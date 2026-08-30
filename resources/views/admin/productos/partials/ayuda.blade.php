{{--
    Botón de ayuda para los formularios de producto.

        @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.precio')])
        @include('admin.productos.partials.ayuda', ['texto' => ..., 'lugar' => 'right', 'grande' => true])

    Es un popover y no un tooltip porque el admin se usa mucho desde el celular: un
    tooltip aparece sólo con el mouse encima y en una pantalla táctil no hay hover,
    así que la ayuda quedaba invisible justo donde más se necesita. El popover se
    abre al tocar (o al hacer clic) y se cierra tocando en cualquier otro lado.
    De paso muestra mejor los textos largos, que acá rondan los 150 caracteres.

    type="button" es obligatorio: sin eso, dentro de un <form>, un <button> envía
    el formulario al tocarlo.

    Se inicializa en layouts/admin.blade.php, no acá: este partial se incluye muchas
    veces por página y no tiene sentido repetir el arranque en cada una.
--}}
<button type="button"
        class="btn btn-link p-0 border-0 align-baseline text-muted ms-1 ayuda-campo"
        data-bs-toggle="popover"
        data-bs-trigger="focus"
        data-bs-placement="{{ isset($lugar) ? $lugar : 'top' }}"
        data-bs-content="{{ $texto }}"
        aria-label="Ayuda sobre este campo">
    <i class="bi bi-question-circle {{ isset($grande) && $grande ? 'fs-6' : '' }}"></i>
</button>
