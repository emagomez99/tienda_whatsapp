@php
    if (!$menu->relationLoaded('childrenActivos')) {
        $menu->load('childrenActivos');
    }
    $tieneHijos = $menu->childrenActivos->count() > 0;
    $collapseId = 'menu-collapse-' . $menu->id;
    $activo = url()->current() === $menu->url;
@endphp

<li>
    <div class="sidebar-row">
        @if($menu->esContenedor())
            <span class="sidebar-link">{{ $menu->nombre }}</span>
        @else
            <a href="{{ $menu->url }}" class="sidebar-link{{ $activo ? ' active' : '' }}" @if($activo) aria-current="page" @endif>
                {{ $menu->nombre }}
            </a>
        @endif
        @if($tieneHijos)
            <button class="sidebar-toggle" type="button"
                    data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                    aria-controls="{{ $collapseId }}" aria-expanded="false"
                    aria-label="Ver subcategorías de {{ $menu->nombre }}">
                <i class="bi bi-chevron-down"></i>
            </button>
        @endif
    </div>
    @if($tieneHijos)
        {{-- Los niveles se indentan con el borde de .sidebar-sub, no con padding calculado. --}}
        <div class="collapse" id="{{ $collapseId }}">
            <ul class="sidebar-sub">
                @foreach($menu->childrenActivos as $child)
                    @include('components.menu-sidebar-item', ['menu' => $child, 'nivel' => $nivel + 1])
                @endforeach
            </ul>
        </div>
    @endif
</li>
