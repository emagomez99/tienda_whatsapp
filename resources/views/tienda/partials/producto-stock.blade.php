{{-- Estado de stock de la ficha de producto (desktop y mobile). --}}
@if($producto->stock !== null || $producto->por_encargue)
    <div class="pdp-stock">
        @if($producto->stock > 0)
            <span class="stock-pill stock-ok">En stock · {{ $producto->stock }} {{ $producto->stock == 1 ? 'disponible' : 'disponibles' }}</span>
        @elseif($producto->stock !== null)
            <span class="stock-pill stock-no">Sin stock</span>
        @endif
        @if($producto->por_encargue)
            <span class="stock-pill stock-encargue">Disponible por encargue</span>
        @endif
    </div>
@endif
