@php
    $tieneHijos = $menu->tieneHijos();
    $collapseId = 'menu-collapse-' . $menu->clave();
    // La sangría por nivel la calcula tienda.css (.menu-lateral-opcion) con --nivel.
@endphp

@if($tieneHijos)
    {{-- Menú con submenús --}}
    <li class="list-group-item p-0 border-0">
        <div class="d-flex align-items-center menu-lateral-fila">
            @if(!$menu->esContenedor())
                <a href="{{ $menu->url() }}" class="menu-lateral-opcion flex-grow-1 text-decoration-none py-2" style="--nivel: {{ $nivel }};">
                    {{ $menu->nombre() }}
                </a>
            @else
                <span class="menu-lateral-opcion flex-grow-1 py-2" style="--nivel: {{ $nivel }};">
                    {{ $menu->nombre() }}
                </span>
            @endif
            <button class="btn btn-sm btn-link text-dark px-3 py-2 border-0" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
        <div class="collapse" id="{{ $collapseId }}">
            <ul class="list-group list-group-flush">
                @foreach($menu->hijos() as $child)
                    @include('components.menu-sidebar-item', ['menu' => $child, 'nivel' => $nivel + 1])
                @endforeach
            </ul>
        </div>
    </li>
@else
    {{-- Menú sin submenús --}}
    <li class="list-group-item border-0 py-2 menu-lateral-opcion menu-lateral-hoja" style="--nivel: {{ $nivel }};">
        <a href="{{ $menu->url() }}" class="text-decoration-none">
            {{ $menu->nombre() }}
        </a>
    </li>
@endif
