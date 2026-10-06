@php
    use App\Models\Menu;
    $drawerItems = Menu::getArbolMenu();
@endphp

{{-- Recursivo (ver menu-drawer-item): antes llegaba sólo hasta el segundo nivel, y
     un submenú con submenús (ej. Notebook › Por rango de precio › Hasta $200.000)
     quedaba como un enlace a ningún lado. --}}
@foreach($drawerItems as $menu)
    @include('components.menu-drawer-item', ['menu' => $menu, 'nivel' => 0])
@endforeach
