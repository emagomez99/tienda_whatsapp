{{--
    Una opción del menú lateral (celular), con sus submenús a cualquier profundidad.
    El primer nivel usa .drawer-item; los de adentro, .drawer-subitem, con más
    sangría en cada nivel: la calcula tienda.css a partir de --nivel.
--}}
@php
    $clase = $nivel === 0 ? 'drawer-item' : 'drawer-subitem';
@endphp
@if($menu->tieneHijos())
    <div>
        <button class="{{ $clase }} w-100 d-flex justify-content-between align-items-center border-0 text-start"
                style="--nivel: {{ $nivel }};"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#drawer-sub-{{ $menu->clave() }}"
                aria-expanded="false">
            <span>{{ $menu->nombre() }}</span>
            <i class="bi bi-chevron-down drawer-chevron"></i>
        </button>
        <div class="collapse" id="drawer-sub-{{ $menu->clave() }}">
            @if(!$menu->esContenedor())
                <a class="drawer-subitem" href="{{ $menu->url() }}" style="--nivel: {{ $nivel + 1 }};">
                    <i class="bi bi-grid me-1 opacity-50"></i> Ver todos
                </a>
            @endif
            @foreach($menu->hijos() as $child)
                @include('components.menu-drawer-item', ['menu' => $child, 'nivel' => $nivel + 1])
            @endforeach
        </div>
    </div>
@else
    <a class="{{ $clase }}" href="{{ $menu->url() }}" style="--nivel: {{ $nivel }};">{{ $menu->nombre() }}</a>
@endif
