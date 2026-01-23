@php
    use App\Models\Menu;
    $menuItems = Menu::getArbolMenu();
@endphp

@if($menuItems->count() > 0)
    <ul class="navbar-nav me-auto">
        @foreach($menuItems as $menu)
            @include('components.menu-item', ['menu' => $menu, 'nivel' => 0])
        @endforeach
    </ul>
@endif
