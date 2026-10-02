@php
    use App\Models\Menu;
    $menuItems = Menu::getArbolMenu();
@endphp

@if($menuItems->count() > 0)
    <nav class="sidebar-card" aria-label="Categorías">
        <p class="sidebar-title">Categorías</p>
        <ul class="sidebar-list">
            @foreach($menuItems as $menu)
                @include('components.menu-sidebar-item', ['menu' => $menu, 'nivel' => 0])
            @endforeach
        </ul>
    </nav>
@endif
