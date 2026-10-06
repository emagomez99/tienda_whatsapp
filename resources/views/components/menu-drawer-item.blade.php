{{--
    Una opción del menú lateral (celular), con sus submenús a cualquier profundidad.
    El primer nivel usa .drawer-item; los de adentro, .drawer-subitem, con más
    sangría en cada nivel.
--}}
@php
    $clase   = $nivel === 0 ? 'drawer-item' : 'drawer-subitem';
    $sangria = $nivel > 1 ? 'padding-left: ' . (2.25 + ($nivel - 1) * .85) . 'rem;' : '';
@endphp
@if($menu->childrenActivos->count() > 0)
    <div>
        <button class="{{ $clase }} w-100 d-flex justify-content-between align-items-center border-0 text-start"
                style="{{ $sangria }}"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#drawer-sub-{{ $menu->id }}"
                aria-expanded="false">
            <span>{{ $menu->nombre }}</span>
            <i class="bi bi-chevron-down drawer-chevron"></i>
        </button>
        <div class="collapse" id="drawer-sub-{{ $menu->id }}">
            @if(!$menu->esContenedor())
                <a class="drawer-subitem" href="{{ $menu->url }}" style="{{ $nivel > 0 ? 'padding-left: ' . (2.25 + $nivel * .85) . 'rem;' : '' }}">
                    <i class="bi bi-grid me-1 opacity-50"></i> Ver todos
                </a>
            @endif
            @foreach($menu->childrenActivos as $child)
                @include('components.menu-drawer-item', ['menu' => $child, 'nivel' => $nivel + 1])
            @endforeach
        </div>
    </div>
@else
    <a class="{{ $clase }}" href="{{ $menu->url }}" style="{{ $sangria }}">{{ $menu->nombre }}</a>
@endif
