@if($productos->isEmpty())
    <div class="text-center py-5 text-muted tienda-sin-resultados">
        <i class="bi bi-search"></i>
        <p class="mt-3 mb-0 fs-6">No se encontraron productos.</p>
    </div>
@else
    <div class="row row-cols-1 row-cols-md-2 {{ $menuEnSidebar ? 'row-cols-lg-2 row-cols-xl-3' : 'row-cols-lg-3 row-cols-xl-4' }} g-3 g-md-4">
        @foreach($productos as $producto)
            <div class="col">
                <div class="card h-100 producto-card">

                    {{-- Imagen --}}
                    <a href="{{ $producto->url() }}" class="producto-img-wrap position-relative d-block">
                        <img src="{{ $producto->imagen_url ?? '/img/no-image.svg' }}"
                             class="card-img-top img-aparece"
                             alt="{{ $producto->descripcion }}"
                             onload="this.style.opacity=1"
                             onerror="this.onerror=null;this.src='/img/no-image.svg';this.style.opacity=1;">

                        @if($producto->stock > 0)
                            {{-- En stock: sin badge --}}
                        @elseif($producto->por_encargue)
                            <span class="badge bg-warning text-dark producto-badge">
                                <i class="bi bi-clock"></i> Por encargue
                            </span>
                        @else
                            <div class="producto-sin-stock-overlay">
                                <span>Sin stock</span>
                            </div>
                        @endif
                    </a>

                    {{-- Cuerpo --}}
                    <div class="card-body d-flex flex-column">
                        <h6 class="producto-nombre mb-2">
                            <a href="{{ $producto->url() }}" class="text-decoration-none">
                                {{ $producto->descripcion }}
                            </a>
                        </h6>

                        @if($producto->etiquetasPublicas->isNotEmpty())
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                @foreach(App\Models\Producto::agruparCaracteristicas($producto->etiquetasPublicas) as $caracteristica)
                                    <span class="tienda-chip">
                                        {{ $caracteristica['etiqueta']->nombre }}: {{ $caracteristica['texto'] }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @if($producto->especificaciones->count() > 0)
                            <div class="text-muted mb-2 producto-especificaciones">
                                @foreach($producto->especificaciones as $espec)
                                    {{ $espec->clave }}: <strong>{{ $espec->valor }}</strong><br>
                                @endforeach
                            </div>
                        @endif

                        @if($mostrarPrecios)
                            <div class="mt-auto pt-1">
                                <span class="producto-precio text-primary">{{ $producto->precio_con_moneda }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="card-footer border-0 bg-transparent">
                        @if($producto->estaDisponible())
                            @php $maxGrid = $producto->stockMaximo(); @endphp
                            <form class="form-agregar" data-url="{{ route('carrito.agregar', $producto) }}">
                                @csrf
                                <div class="selector-cantidad selector-cantidad-chico d-flex gap-1 align-items-center">
                                    <button type="button" class="btn btn-outline-secondary btn-sm btn-dec" disabled>
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number" name="cantidad" value="1" min="1"
                                           @if($maxGrid !== null) max="{{ $maxGrid }}" @endif
                                           class="form-control form-control-sm text-center qty-grid">
                                    <button type="button" class="btn btn-outline-secondary btn-sm btn-inc" {{ $maxGrid === 1 ? 'disabled' : '' }}>
                                        <i class="bi bi-plus"></i>
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                        <i class="bi bi-cart-plus"></i> Agregar
                                    </button>
                                </div>
                            </form>
                        @else
                            <button class="btn btn-light btn-sm w-100 text-muted producto-sin-stock-boton" disabled>
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
