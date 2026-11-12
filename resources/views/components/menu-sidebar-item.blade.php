@php
    $tieneHijos = $menu->tieneHijos();
    $collapseId = 'menu-collapse-' . $menu->clave();
    $actual = $esActual($menu);
    // La rama donde está el cliente arranca abierta.
    $abierta = $tieneHijos && $ramaActual($menu);
    // La sangría por nivel la calcula tienda.css (.menu-lateral-opcion) con --nivel.
@endphp

@if($tieneHijos)
    {{-- Menú con submenús: el nombre lleva a su página y la flecha abre lo de adentro. --}}
    <li>
        <div class="menu-lateral-fila {{ $actual ? 'es-actual' : '' }}">
            @if(!$menu->esContenedor())
                <a href="{{ $menu->url() }}" class="menu-lateral-opcion flex-grow-1" style="--nivel: {{ $nivel }};" @if($actual) aria-current="page" @endif>
                    {{ $menu->nombre() }}
                </a>
            @else
                <button type="button" class="menu-lateral-opcion flex-grow-1 text-start border-0 bg-transparent" style="--nivel: {{ $nivel }};"
                        data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}">
                    {{ $menu->nombre() }}
                </button>
            @endif
            <button class="menu-lateral-flecha" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                    aria-expanded="{{ $abierta ? 'true' : 'false' }}" aria-controls="{{ $collapseId }}" aria-label="Ver lo que hay en {{ $menu->nombre() }}">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
        <div class="collapse {{ $abierta ? 'show' : '' }}" id="{{ $collapseId }}">
            <ul class="list-unstyled mb-0">
                @foreach($menu->hijos() as $child)
                    @include('components.menu-sidebar-item', ['menu' => $child, 'nivel' => $nivel + 1])
                @endforeach
            </ul>
        </div>
    </li>
@else
    {{-- Menú sin submenús: toda la fila es el enlace. --}}
    <li>
        <a href="{{ $menu->url() }}" class="menu-lateral-opcion {{ $actual ? 'es-actual' : '' }}" style="--nivel: {{ $nivel }};" @if($actual) aria-current="page" @endif>
            {{ $menu->nombre() }}
        </a>
    </li>
@endif
