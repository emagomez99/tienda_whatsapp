@php
    use App\Models\Menu;
    $menuItems = Menu::getArbolMenu();
@endphp

@if($menuItems->count() > 0)
    <ul class="navbar-nav me-auto">
        @foreach($menuItems as $menu)
            @if($menu->childrenActivos->count() > 0)
                {{-- Menú con submenús --}}
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
                            @if($child->childrenActivos->count() > 0)
                                {{-- Submenú con más niveles --}}
                                <li class="dropend">
                                    <a class="dropdown-item dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                        {{ $child->nombre }}
                                    </a>
                                    <ul class="dropdown-menu">
                                        @if(!$child->esContenedor())
                                            <li>
                                                <a class="dropdown-item" href="{{ $child->url }}">
                                                    <strong>Ver todos</strong>
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                        @endif
                                        @foreach($child->childrenActivos as $grandchild)
                                            <li>
                                                <a class="dropdown-item" href="{{ $grandchild->url }}">
                                                    {{ $grandchild->nombre }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @else
                                <li>
                                    <a class="dropdown-item" href="{{ $child->url }}">
                                        {{ $child->nombre }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </li>
            @else
                {{-- Menú simple sin submenús --}}
                <li class="nav-item">
                    <a class="nav-link" href="{{ $menu->url }}">
                        {{ $menu->nombre }}
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
@endif
