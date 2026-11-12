{{--
    El menú de la tienda al costado (Ajustes › Menú de la tienda › Menú lateral).
    Una lista liviana, sin caja ni franja de color: la cabecera ya tiene el color de
    la marca. Marca dónde está el cliente y deja abierta esa rama. Sólo en pantallas
    grandes: en el celular y la tablet el menú es el de las tres rayitas.
--}}
@php
    $menuItems = App\Services\MenuDeLaTienda::opciones();
    $aca = rtrim(url()->current(), '/');
    $esActual = function ($menu) use ($aca) {
        return !$menu->esContenedor() && rtrim($menu->url(), '/') === $aca;
    };
    // ¿El cliente está en esta opción o en alguna de adentro?
    $ramaActual = function ($menu) use (&$ramaActual, $esActual) {
        if ($esActual($menu)) {
            return true;
        }
        foreach ($menu->tieneHijos() ? $menu->hijos() : [] as $hijo) {
            if ($ramaActual($hijo)) {
                return true;
            }
        }
        return false;
    };
    $enInicio = $aca === rtrim(route('tienda.index'), '/');
@endphp

@if($menuItems->count() > 0)
    <nav class="menu-lateral" aria-label="Categorías">
        <div class="menu-lateral-titulo">Categorías</div>
        <ul class="list-unstyled mb-0">
            <li>
                <a href="{{ route('tienda.index') }}" class="menu-lateral-opcion {{ $enInicio ? 'es-actual' : '' }}" style="--nivel: 0;" @if($enInicio) aria-current="page" @endif>
                    Todos los productos
                </a>
            </li>
            @foreach($menuItems as $menu)
                @include('components.menu-sidebar-item', ['menu' => $menu, 'nivel' => 0])
            @endforeach
        </ul>
    </nav>
@endif
