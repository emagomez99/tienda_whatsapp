@php
    // Asegurar que los hijos estén cargados
    if (!$menu->relationLoaded('childrenActivos')) {
        $menu->load('childrenActivos');
    }
@endphp
@if($menu->childrenActivos->count() > 0)
    {{-- Menú con submenús --}}
    @if($nivel === 0)
        {{-- Primer nivel: dropdown normal --}}
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ $menu->nombre }}
            </a>
            <ul class="dropdown-menu">
                @if(!$menu->esContenedor())
                    <li>
                        <a class="dropdown-item" href="{{ $menu->url }}">
                            <strong>Ver todos</strong>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                @endif
                @foreach($menu->childrenActivos as $child)
                    @include('components.menu-item', ['menu' => $child, 'nivel' => 1])
                @endforeach
            </ul>
        </li>
    @else
        {{-- Niveles interiores: dropend --}}
        <li class="dropend">
            <a class="dropdown-item dropdown-toggle" href="#" data-bs-toggle="dropdown">
                {{ $menu->nombre }}
            </a>
            <ul class="dropdown-menu">
                @if(!$menu->esContenedor())
                    <li>
                        <a class="dropdown-item" href="{{ $menu->url }}">
                            <strong>Ver todos</strong>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                @endif
                @foreach($menu->childrenActivos as $child)
                    @include('components.menu-item', ['menu' => $child, 'nivel' => $nivel + 1])
                @endforeach
            </ul>
        </li>
    @endif
@else
    {{-- Menú sin submenús --}}
    @if($nivel === 0)
        <li class="nav-item">
            <a class="nav-link" href="{{ $menu->url }}">
                {{ $menu->nombre }}
            </a>
        </li>
    @else
        <li>
            <a class="dropdown-item" href="{{ $menu->url }}">
                {{ $menu->nombre }}
            </a>
        </li>
    @endif
@endif
