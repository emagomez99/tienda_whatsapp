{{--
    Un listado sin nada que mostrar. Dice cuál de las dos cosas pasa, porque se
    resuelven distinto:
      - hay filtros o búsqueda puestos y nada coincide → cómo quitarlos;
      - todavía no se cargó nada → el botón para cargar lo primero.

    Recibe: $icono (bi-…), $filtrado y $nada (los dos textos), $url (el listado sin
    filtros) y, opcionales, $crear (dirección) con $crearTexto.
--}}
@php
    $hayFiltros = collect(request()->except('page'))->filter(function ($v) { return $v !== null && $v !== ''; })->isNotEmpty();
@endphp
<div class="text-center py-5">
    <i class="bi {{ $hayFiltros ? 'bi-funnel' : $icono }} text-muted display-6"></i>
    @if($hayFiltros)
        <p class="fw-semibold mt-2 mb-1">{{ $filtrado }}</p>
        <a href="{{ $url }}">Quitar los filtros y ver todo</a>
    @else
        <p class="fw-semibold mt-2 mb-{{ empty($crear) ? '0' : '3' }}">{{ $nada }}</p>
        @if(!empty($crear))
            <a href="{{ $crear }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> {{ $crearTexto }}</a>
        @endif
    @endif
</div>
