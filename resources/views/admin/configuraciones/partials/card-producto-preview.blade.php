{{--
    Card de producto de la vista previa en Ajustes → Tienda. Replica la card de
    tienda/partials/productos-grid.blade.php en chico, sin acciones reales.

    Parámetros:
      $producto — App\Models\Producto o null (usa un ejemplo)
      $sinStock — bool, si se muestra como agotado
      $ejemplo  — nombre del producto de ejemplo cuando $producto es null
--}}
@php
    $simboloEjemplo = optional(App\Models\Moneda::base())->simbolo ?: '$';
    $nombre = $producto ? $producto->descripcion : $ejemplo;
    $imagen = ($producto ? $producto->imagen_url : null) ?: '/img/no-image.svg';
    $precio = $producto ? $producto->precio_con_moneda : $simboloEjemplo . number_format($sinStock ? 8900 : 15000, 2);
@endphp
<div class="card h-100 preview-card">
    <div class="position-relative" style="height: 110px;">
        <img src="{{ $imagen }}" alt="" style="width: 100%; height: 100%; object-fit: contain; background: #fff;"
             onerror="this.onerror=null;this.src='/img/no-image.svg';">
        @if($sinStock)
            <div class="preview-card-agotado"><span>Sin stock</span></div>
        @endif
    </div>
    <div class="card-body d-flex flex-column p-2">
        <div class="small fw-semibold preview-card-nombre">{{ $nombre }}</div>
        <div class="mt-auto pt-1 fw-bold preview-card-precio" data-preview-precio>{{ $precio }}</div>
    </div>
    <div class="card-footer border-0 bg-transparent p-2 pt-0">
        @if($sinStock)
            <span class="btn btn-light btn-sm w-100 text-muted disabled" style="border: 1px solid #e9ecef;">
                <i class="bi bi-x-circle me-1"></i> Sin stock
            </span>
        @else
            <span class="btn btn-sm w-100 disabled preview-card-boton"><i class="bi bi-cart-plus"></i> Agregar</span>
        @endif
    </div>
</div>
