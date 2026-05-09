@php
    use App\Models\Menu;
    $drawerItems = Menu::getArbolMenu();
@endphp

@foreach($drawerItems as $menu)
    @if($menu->childrenActivos->count() > 0)
        <div>
            <button class="drawer-item w-100 d-flex justify-content-between align-items-center"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#drawer-sub-{{ $menu->id }}"
                    aria-expanded="false">
                <span>{{ $menu->nombre }}</span>
                <i class="bi bi-chevron-down drawer-chevron"></i>
            </button>
            <div class="collapse" id="drawer-sub-{{ $menu->id }}">
                @if(!$menu->esContenedor())
                    <a class="drawer-subitem" href="{{ $menu->url }}">
                        <i class="bi bi-grid me-1 opacity-50"></i> Ver todos
                    </a>
                @endif
                @foreach($menu->childrenActivos as $child)
                    <a class="drawer-subitem" href="{{ $child->url }}">{{ $child->nombre }}</a>
                @endforeach
            </div>
        </div>
    @else
        <a class="drawer-item" href="{{ $menu->url }}">{{ $menu->nombre }}</a>
    @endif
@endforeach
