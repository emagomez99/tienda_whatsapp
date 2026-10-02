@php
    use App\Models\Menu;
    $drawerItems = Menu::getArbolMenu();
    $urlActual = url()->current();
@endphp

@foreach($drawerItems as $menu)
    @if($menu->childrenActivos->count() > 0)
        @php
            // Si la página actual es este menú o uno de sus hijos, el grupo arranca abierto.
            $abierto = $menu->url === $urlActual || $menu->childrenActivos->contains(function ($hijo) use ($urlActual) {
                return $hijo->url === $urlActual;
            });
        @endphp
        <div>
            <button class="drawer-item"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#drawer-sub-{{ $menu->id }}"
                    aria-controls="drawer-sub-{{ $menu->id }}"
                    aria-expanded="{{ $abierto ? 'true' : 'false' }}">
                <span>{{ $menu->nombre }}</span>
                <i class="bi bi-chevron-down drawer-chevron"></i>
            </button>
            <div class="collapse{{ $abierto ? ' show' : '' }}" id="drawer-sub-{{ $menu->id }}">
                <div class="drawer-sub">
                    @if(!$menu->esContenedor())
                        <a class="drawer-subitem{{ $menu->url === $urlActual ? ' active' : '' }}" href="{{ $menu->url }}">Ver todos</a>
                    @endif
                    @foreach($menu->childrenActivos as $child)
                        <a class="drawer-subitem{{ $child->url === $urlActual ? ' active' : '' }}" href="{{ $child->url }}">{{ $child->nombre }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <a class="drawer-item{{ $menu->url === $urlActual ? ' active' : '' }}" href="{{ $menu->url }}">{{ $menu->nombre }}</a>
    @endif
@endforeach
