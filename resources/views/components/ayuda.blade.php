{{--
    Ayuda de una pantalla: un botón "Cómo funciona" que abre un panel desde la
    izquierda. No oscurece ni bloquea la página: se puede ir haciendo lo que dice.

        <x-ayuda id="ayuda-categorias" titulo="Cómo funcionan las categorías">
            @include('admin.ayudas.categorias')
        </x-ayuda>

    Adentro se usan los bloques de abajo (.ayuda-item con su ícono), así todas las
    ayudas se ven iguales:

        <div class="ayuda-item">
            <span class="ayuda-icono"><i class="bi bi-plus-lg"></i></span>
            <div><strong>Título corto</strong><br>Una o dos frases, con un ejemplo.</div>
        </div>

    El botón va donde se pone el componente (en general, en el encabezado de la
    página). type="button": dentro de un <form> no tiene que enviarlo.
--}}
@props(['id', 'titulo' => 'Cómo funciona'])

<button type="button" class="btn btn-outline-secondary boton-ayuda" data-bs-toggle="offcanvas" data-bs-target="#{{ $id }}" aria-controls="{{ $id }}">
    <i class="bi bi-question-circle"></i><span class="d-none d-sm-inline"> Cómo funciona</span>
</button>

{{-- Sin fondo oscuro y con la página usable: se puede ir haciendo lo que dice mientras se lee. --}}
<div class="offcanvas offcanvas-start panel-ayuda shadow" tabindex="-1" id="{{ $id }}" aria-labelledby="{{ $id }}-titulo"
     data-bs-backdrop="false" data-bs-scroll="true">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="{{ $id }}-titulo"><i class="bi bi-lightbulb text-warning"></i> {{ $titulo }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar la ayuda"></button>
    </div>
    <div class="offcanvas-body">
        {{ $slot }}
        <a href="{{ route('admin.ayuda.index') }}" class="d-inline-block small mt-2"><i class="bi bi-question-circle"></i> Ver toda la ayuda</a>
    </div>
</div>

@once
{{-- Los estilos de la ayuda (.ayuda-*) están en public/css/admin.css: los usa también la página de Ayuda. --}}
{{-- En el lugar y no con @push: en Laravel 8 el stack agrupa lo empujado por
     profundidad de vista, y un push más desde acá reordenaba los scripts de la
     página (el de categorías en masa quedaba antes de PanelDeCategorias). Sólo usa
     bootstrap al apretar Escape, cuando ya está cargado. --}}
<script>
    // Escape cierra la ayuda abierta. Bootstrap sólo lo escucha con el foco adentro
    // del panel, y como este no bloquea la página (sin fondo, con scroll), el foco
    // suele estar afuera, en lo que se está haciendo mientras se lee.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || e.defaultPrevented) return;
        // Un modal abierto (ej. una confirmación) se cierra primero, solo.
        if (document.querySelector('.modal.show')) return;
        document.querySelectorAll('.panel-ayuda.show').forEach(function (panel) {
            bootstrap.Offcanvas.getOrCreateInstance(panel).hide();
        });
    });
</script>
@endonce
