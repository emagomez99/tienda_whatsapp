@if($productos->isEmpty())
    <div class="tienda-aviso">
        <div class="tienda-aviso-icon"><i class="bi bi-search"></i></div>
        <div class="tienda-aviso-title">No encontramos productos</div>
        <p class="mb-0">Probá con otra búsqueda o cambiá los filtros.</p>
    </div>
@else
    {{-- En la card se muestra un resumen: el detalle completo está en la ficha. --}}
    @php
        $maxEtiquetas = 3;
        $maxEspecificaciones = 4;
    @endphp
    <div class="productos-grid">
        @foreach($productos as $producto)
            @php
                $etiquetasVisibles = $producto->etiquetas->where('visible_usuarios', true);
                $agotado = !($producto->stock > 0) && !$producto->por_encargue;
            @endphp
            <article class="producto-card{{ $agotado ? ' is-agotado' : '' }}">

                {{-- Imagen --}}
                <div class="producto-media">
                    <img src="{{ $producto->imagen_url ?? '/img/no-image.svg' }}"
                         alt="{{ $producto->descripcion }}"
                         class="img-fade"
                         loading="lazy"
                         onload="this.classList.add('is-loaded')"
                         onerror="this.onerror=null;this.src='/img/no-image.svg';this.classList.add('is-loaded');">

                    @if($producto->stock > 0)
                        {{-- En stock: sin marca --}}
                    @elseif($producto->por_encargue)
                        <span class="producto-flag flag-encargue"><i class="bi bi-clock"></i> Por encargue</span>
                    @else
                        <span class="producto-flag flag-agotado">Sin stock</span>
                    @endif
                </div>

                {{-- Cuerpo --}}
                <div class="producto-body">
                    @if($etiquetasVisibles->isNotEmpty())
                        <div class="producto-tags">
                            @foreach($etiquetasVisibles->take($maxEtiquetas) as $etiqueta)
                                <span class="chip" title="{{ $etiqueta->nombre }}: {{ $etiqueta->pivot->valor }}">
                                    <span class="chip-k">{{ $etiqueta->nombre }}</span> {{ $etiqueta->pivot->valor }}
                                </span>
                            @endforeach
                            @if($etiquetasVisibles->count() > $maxEtiquetas)
                                <span class="chip chip-more">+{{ $etiquetasVisibles->count() - $maxEtiquetas }}</span>
                            @endif
                        </div>
                    @endif

                    {{-- stretched-link: toda la card lleva a la ficha --}}
                    <h2 class="producto-nombre">
                        <a href="{{ $producto->url() }}" class="stretched-link">{{ $producto->descripcion }}</a>
                    </h2>

                    @if($producto->especificaciones->isNotEmpty())
                        <dl class="producto-specs">
                            @foreach($producto->especificaciones->take($maxEspecificaciones) as $espec)
                                <div><dt>{{ $espec->clave }}</dt><dd title="{{ $espec->valor }}">{{ $espec->valor }}</dd></div>
                            @endforeach
                        </dl>
                        @if($producto->especificaciones->count() > $maxEspecificaciones)
                            <span class="producto-specs-more">
                                +{{ $producto->especificaciones->count() - $maxEspecificaciones }} especificaciones
                            </span>
                        @endif
                    @endif

                    {{-- Precio y compra --}}
                    <div class="producto-footer">
                        @if($mostrarPrecios)
                            <div class="producto-precio">{{ $producto->precio_con_moneda }}</div>
                        @endif

                        @if($producto->estaDisponible())
                            @php $maxGrid = $producto->stockMaximo(); @endphp
                            <form class="form-agregar producto-comprar" data-url="{{ route('carrito.agregar', $producto) }}">
                                @csrf
                                <div class="qty-stepper">
                                    <button type="button" class="btn-dec" aria-label="Restar uno" disabled>
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number" name="cantidad" value="1" min="1"
                                           @if($maxGrid !== null) max="{{ $maxGrid }}" @endif
                                           class="qty-grid" aria-label="Cantidad">
                                    <button type="button" class="btn-inc" aria-label="Sumar uno" {{ $maxGrid === 1 ? 'disabled' : '' }}>
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                                <button type="submit" class="btn btn-primary btn-agregar">
                                    <i class="bi bi-cart-plus"></i> Agregar
                                </button>
                            </form>
                        @else
                            <button type="button" class="btn btn-ghost btn-agregar w-100" disabled>Sin stock</button>
                        @endif
                    </div>
                </div>

            </article>
        @endforeach
    </div>

    @if($productos->hasPages())
        <div class="mt-4">
            {{ $productos->withQueryString()->links('vendor.pagination.tienda') }}
        </div>
    @endif
@endif
