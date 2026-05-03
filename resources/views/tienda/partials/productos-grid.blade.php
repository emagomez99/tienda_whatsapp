@if($productos->isEmpty())
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No se encontraron productos con los filtros seleccionados.
    </div>
@else
    <div class="row row-cols-1 row-cols-md-2 {{ $menuEnSidebar ? 'row-cols-lg-2 row-cols-xl-3' : 'row-cols-lg-3 row-cols-xl-4' }} g-4">
        @foreach($productos as $producto)
            <div class="col">
                <div class="card h-100 producto-card shadow-sm">
                    <a href="{{ route('tienda.show', $producto) }}" class="text-decoration-none">
                        @if($producto->url_imagen)
                            <img src="{{ $producto->imagen_url }}" class="card-img-top" alt="{{ $producto->descripcion }}" style="height: 250px; width: 100%; object-fit: contain; background-color: #f8f9fa;">
                        @else
                            <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center text-white" style="height: 250px;">
                                <i class="bi bi-image" style="font-size: 3rem;"></i>
                            </div>
                        @endif
                    </a>
                    <div class="card-body">
                        <h6 class="card-title">
                            <a href="{{ route('tienda.show', $producto) }}" class="text-decoration-none text-dark stretched-link-title">{{ $producto->descripcion }}</a>
                        </h6>
                        @if($producto->id_proveedor)
                            <small class="text-muted">Cod: {{ $producto->id_proveedor }}</small>
                            <br>
                        @endif
                        @if($producto->stock > 0)
                            <small class="text-success"><i class="bi bi-check-circle"></i> En stock ({{ $producto->stock }})</small>
                        @elseif($producto->por_encargue)
                            <small class="text-warning"><i class="bi bi-clock"></i> Por encargue</small>
                        @else
                            <small class="text-danger"><i class="bi bi-x-circle"></i> Sin stock</small>
                        @endif

                        @if($mostrarPrecios)
                            <p class="card-text h5 text-primary mt-2">
                                {{ $producto->precio_con_moneda }}
                            </p>
                        @endif

                        @if($producto->etiquetas->where('visible_usuarios', true)->count() > 0)
                            <div class="mb-2 d-flex flex-wrap gap-1">
                                @foreach($producto->etiquetas->where('visible_usuarios', true) as $etiqueta)
                                    <span class="badge bg-info text-wrap text-start" style="word-break: break-word; white-space: normal;">{{ $etiqueta->nombre }}: {{ $etiqueta->pivot->valor }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if($producto->especificaciones->count() > 0)
                            <div class="especificaciones-list text-muted">
                                @foreach($producto->especificaciones as $espec)
                                    <small style="word-break: break-word;">{{ $espec->clave }}: {{ $espec->valor }}</small><br>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent">
                        @if($producto->estaDisponible())
                            @php $maxGrid = $producto->stockMaximo(); @endphp
                            <form class="form-agregar" data-url="{{ route('carrito.agregar', $producto) }}">
                                @csrf
                                <div class="d-flex gap-1 align-items-center">
                                    <button type="button" class="btn btn-outline-secondary btn-sm btn-dec" disabled>
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number" name="cantidad" value="1" min="1"
                                           @if($maxGrid !== null) max="{{ $maxGrid }}" @endif
                                           class="form-control form-control-sm text-center qty-grid"
                                           style="width: 50px; -moz-appearance: textfield;">
                                    <button type="button" class="btn btn-outline-secondary btn-sm btn-inc" {{ $maxGrid === 1 ? 'disabled' : '' }}>
                                        <i class="bi bi-plus"></i>
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                        <i class="bi bi-cart-plus"></i> Agregar
                                    </button>
                                </div>
                            </form>
                        @else
                            <button class="btn btn-secondary btn-sm w-100" disabled>
                                <i class="bi bi-x-circle"></i> Sin stock
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
