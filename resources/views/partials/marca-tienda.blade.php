{{--
    Logo + nombre de la tienda (header, drawer y footer). Va dentro de un <a class="site-brand">.
    Usa $logoTienda, $mostrarNombre y $nombreTienda, que define layouts/app.
--}}
@if($logoTienda)
    <img src="{{ url('storage/' . $logoTienda) }}" alt="{{ $nombreTienda }}">
@else
    <span class="site-brand-icon" aria-hidden="true"><i class="bi bi-shop"></i></span>
@endif
@if($mostrarNombre || !$logoTienda)
    <span class="site-brand-name">{{ $nombreTienda }}</span>
@endif
