@if($productos->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="bi bi-search" style="font-size:2.5rem;opacity:.25;"></i>
        <p class="mt-3 mb-0 fs-6">No se encontraron productos.</p>
    </div>
@else
    <div class="row row-cols-1 row-cols-md-2 {{ $menuEnSidebar ? 'row-cols-lg-2 row-cols-xl-3' : 'row-cols-lg-3 row-cols-xl-4' }} g-3 g-md-4">
        @foreach($productos as $producto)
            <div class="col">
                <div class="card h-100 producto-card">

                    {{-- Imagen --}}
                    <a href="{{ route('tienda.show', $producto) }}" class="producto-img-wrap position-relative d-block" style="height:220px;">
                        <img src="{{ $producto->url_imagen ? $producto->imagen_url : '/img/no-image.svg' }}"
                             class="card-img-top"
                             alt="{{ $producto->descripcion }}"
                             style="width:100%;height:100%;object-fit:contain;background:#fff;"
                             onerror="this.onerror=null;this.src='/img/no-image.svg';">

                        @if($producto->stock > 0)
                            {{-- En stock: sin badge --}}
                        @elseif($producto->por_encargue)
                            <span class="badge bg-warning text-dark position-absolute"
                                  style="top:9px;left:9px;font-size:.72rem;">
                                <i class="bi bi-clock"></i> Por encargue
                            </span>
                        @else
                            <div class="producto-sin-stock-overlay">
                                <span>Sin stock</span>
                            </div>
                        @endif
                    </a>

                    {{-- Cuerpo --}}
                    <div class="card-body d-flex flex-column px-3 pt-3 pb-2">
                        <h6 class="producto-nombre mb-2">
                            <a href="{{ route('tienda.show', $producto) }}" class="text-decoration-none text-dark">
                                {{ $producto->descripcion }}
                            </a>
                        </h6>

                        @if($producto->etiquetas->where('visible_usuarios', true)->count() > 0)
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                @foreach($producto->etiquetas->where('visible_usuarios', true) as $etiqueta)
                                    <span class="badge fw-normal"
                                          style="background:rgba(13,202,240,.12);color:#0a6a77;font-size:.7rem;">
                                        {{ $etiqueta->nombre }}: {{ $etiqueta->pivot->valor }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @if($producto->especificaciones->count() > 0)
                            <div class="text-muted mb-2" style="font-size:.78rem;line-height:1.6;">
                                @foreach($producto->especificaciones as $espec)
                                    {{ $espec->clave }}: <strong>{{ $espec->valor }}</strong><br>
                                @endforeach
                            </div>
                        @endif

                        @if($mostrarPrecios)
                            <div class="mt-auto pt-1">
                                <span class="fs-5 fw-bold text-primary">{{ $producto->precio_con_moneda }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="card-footer border-0 bg-transparent px-3 pb-3 pt-1">
                        @if($producto->estaDisponible())
                            @php $maxGrid = $producto->stockMaximo(); @endphp
                            <form class="form-agregar" data-url="{{ route('carrito.agregar', $producto) }}">
                                @csrf
                                <div class="d-flex gap-1 align-items-center">
                                    <button type="button" class="btn btn-outline-secondary btn-sm btn-dec"
                                            style="width:32px;height:32px;padding:0;flex-shrink:0;" disabled>
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number" name="cantidad" value="1" min="1"
                                           @if($maxGrid !== null) max="{{ $maxGrid }}" @endif
                                           class="form-control form-control-sm text-center qty-grid"
                                           style="width:44px;height:32px;-moz-appearance:textfield;flex-shrink:0;">
                                    <button type="button" class="btn btn-outline-secondary btn-sm btn-inc"
                                            style="width:32px;height:32px;padding:0;flex-shrink:0;" {{ $maxGrid === 1 ? 'disabled' : '' }}>
                                        <i class="bi bi-plus"></i>
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1" style="height:32px;">
                                        <i class="bi bi-cart-plus"></i> Agregar
                                    </button>
                                </div>
                            </form>
                        @else
                            <button class="btn btn-light btn-sm w-100 text-muted"
                                    style="border:1px solid #e9ecef;" disabled>
                                <i class="bi bi-x-circle me-1"></i> Sin stock
                            </button>
                        @endif
                    </div>

                </div>
            </div>
        @endforeach
    </div>

    @if($productos->hasPages())
        <div class="mt-4">
            {{ $productos->withQueryString()->links('vendor.pagination.tienda') }}
        </div>
    @endif
@endif
