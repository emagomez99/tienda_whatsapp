@php
    if (!$menu->relationLoaded('childrenActivos')) {
        $menu->load('childrenActivos');
    }
    $tieneHijos = $menu->childrenActivos->count() > 0;
    $collapseId = 'menu-collapse-' . $menu->id;
    $paddingLeft = ($nivel * 15) + 12;
@endphp

@if($tieneHijos)
    {{-- Menú con submenús --}}
    <li class="list-group-item p-0 border-0">
        <div class="d-flex align-items-center">
            @if(!$menu->esContenedor())
                <a href="{{ $menu->url }}" class="flex-grow-1 text-decoration-none text-dark py-2" style="padding-left: {{ $paddingLeft }}px;">
                    {{ $menu->nombre }}
                </a>
            @else
                <span class="flex-grow-1 py-2 text-dark" style="padding-left: {{ $paddingLeft }}px;">
                    {{ $menu->nombre }}
                </span>
            @endif
            <button class="btn btn-sm btn-link text-dark px-3 py-2 border-0" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
        <div class="collapse" id="{{ $collapseId }}">
            <ul class="list-group list-group-flush">
                @foreach($menu->childrenActivos as $child)
                    @include('components.menu-sidebar-item', ['menu' => $child, 'nivel' => $nivel + 1])
                @endforeach
            </ul>
        </div>
    </li>
@else
    {{-- Menú sin submenús --}}
    <li class="list-group-item border-0 py-2" style="padding-left: {{ $paddingLeft }}px;">
        <a href="{{ $menu->url }}" class="text-decoration-none text-dark">
            {{ $menu->nombre }}
        </a>
    </li>
@endif
