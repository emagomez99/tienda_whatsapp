{{-- Código y proveedor de la ficha de producto (desktop y mobile). $mostrarProveedor lo define tienda/show. --}}
@if($producto->id_proveedor || $mostrarProveedor)
    <div class="pdp-meta">
        @if($producto->id_proveedor)
            <span class="meta-pill">Código <strong>{{ $producto->id_proveedor }}</strong></span>
        @endif
        @if($mostrarProveedor)
            <span class="meta-pill">Proveedor <strong>{{ $producto->proveedor->nombre }}</strong></span>
        @endif
    </div>
@endif
