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
    </div>
</div>

@once
@push('styles')
<style>
    .panel-ayuda { --bs-offcanvas-width: 380px; }
    .panel-ayuda .offcanvas-body { background: #fbfbfc; }
    .ayuda-titulo { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; margin: 1.25rem 0 .75rem; }
    .ayuda-titulo:first-child { margin-top: 0; }
    .ayuda-item { display: flex; gap: .75rem; align-items: flex-start; margin-bottom: .9rem; font-size: .875rem; color: #495057; line-height: 1.45; }
    .ayuda-item strong { color: #212529; font-weight: 600; }
    .ayuda-icono {
        flex-shrink: 0; width: 2rem; height: 2rem; border-radius: .5rem;
        display: inline-flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--admin-color, #0d6efd) 22%, white); color: #212529;
    }
    .ayuda-ejemplo { background: #fff; border: 1px solid #e9ecef; border-radius: .5rem; padding: .6rem .75rem; font-size: .85rem; margin-bottom: 1rem; }
    .ayuda-ejemplo .text-muted { font-size: .8rem; }
</style>
@endpush
@push('scripts')
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
@endpush
@endonce
