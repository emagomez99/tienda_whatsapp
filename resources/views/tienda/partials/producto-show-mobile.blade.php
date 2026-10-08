<div class="d-block d-md-none">

    {{-- Imagen con botones flotantes --}}
    @php $galeria = $producto->galeria(); @endphp
    <div class="position-relative ficha-movil-imagen">
        @if($galeria->count() > 1)
            <div id="carousel-producto-mobile" class="carousel slide h-100" data-bs-ride="false">
                <div class="carousel-inner h-100">
                    @foreach($galeria as $i => $img)
                        <div class="carousel-item h-100 {{ $i === 0 ? 'active' : '' }}">
                            <img src="{{ $img->imagen_url }}"
                                 alt="{{ $producto->descripcion }}"
                                 class="img-contenida img-aparece"
                                 onload="this.style.opacity=1"
                                 onerror="this.onerror=null;this.src='/img/no-image.svg';this.style.opacity=1;">
                        </div>
                    @endforeach
                </div>
                <button class="carousel-control-prev" type="button"
                        data-bs-target="#carousel-producto-mobile" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button"
                        data-bs-target="#carousel-producto-mobile" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                </button>
            </div>
        @else
            <img src="{{ $producto->imagen_url ?? '/img/no-image.svg' }}"
                 alt="{{ $producto->descripcion }}"
                 class="img-contenida img-aparece"
                 onload="this.style.opacity=1"
                 onerror="this.onerror=null;this.src='/img/no-image.svg';this.style.opacity=1;">
        @endif

        {{-- Botón volver --}}
        <button type="button" onclick="history.length > 1 ? history.back() : window.location='{{ route('tienda.index') }}'"
                class="ficha-boton-redondo ficha-volver">
            <i class="bi bi-arrow-left"></i>
        </button>

        {{-- Botón compartir --}}
        <button type="button"
                class="btn-compartir ficha-boton-redondo ficha-compartir-movil">
            <i class="bi bi-share"></i>
        </button>
    </div>

    <div class="px-3 pt-3 pb-5">

        {{-- En qué estante está, para ir a ver los parecidos. --}}
        @if($migas)
            <nav aria-label="Ruta" class="migas mb-1 text-truncate">
                @foreach($migas as $estante)
                    <a href="{{ $estante->url() }}">{{ $estante->nombre }}</a>@if(!$loop->last)<span class="migas-separador">›</span>@endif
                @endforeach
            </nav>
        @endif

        {{-- Nombre + código --}}
        <h2 class="ficha-titulo-movil mb-1">{{ $producto->descripcion }}</h2>
        @if($producto->id_proveedor)
            <p class="text-muted small mb-2">Código: {{ $producto->id_proveedor }}</p>
        @endif
        @if($producto->proveedor && App\Models\Configuracion::mostrarProveedor())
            <p class="text-muted small mb-2">Proveedor: {{ $producto->proveedor->nombre }}</p>
        @endif

        {{-- Precio --}}
        @if($mostrarPrecios)
            <div class="ficha-precio-movil text-primary mb-3">{{ $producto->precio_con_moneda }}</div>
        @endif

        {{-- Stock --}}
        <div class="d-flex gap-2 flex-wrap mb-3">
            {{-- Igual que en la ficha de escritorio: por encargue sólo cuando no hay stock. --}}
            @if($producto->stock > 0)
                <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle"></i> En stock ({{ $producto->stock }})</span>
            @elseif($producto->por_encargue)
                <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-clock"></i> Sin stock ahora: lo encargamos para vos</span>
            @elseif($producto->stock !== null)
                <span class="badge bg-danger-subtle text-danger-emphasis"><i class="bi bi-x-circle"></i> Sin stock</span>
            @endif
        </div>

        {{-- Detalle colapsable --}}
        @if($producto->detalle)
            <div class="accordion ficha-acordeon mb-3" id="acc-detalle-mobile">
                <div class="accordion-item border-0 border-bottom">
                    <h2 class="accordion-header">
                        <button class="accordion-button px-0 bg-transparent shadow-none fw-semibold"
                                type="button" data-bs-toggle="collapse" data-bs-target="#detalle-mobile">
                            Descripción
                        </button>
                    </h2>
                    <div id="detalle-mobile" class="accordion-collapse collapse show">
                        <div class="accordion-body px-0 ficha-detalle">{!! $producto->detalle !!}</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Especificaciones colapsables --}}
        @if($producto->especificaciones->count() > 0)
            <div class="accordion ficha-acordeon mb-3" id="acc-specs-mobile">
                <div class="accordion-item border-0 border-bottom">
                    <h2 class="accordion-header">
                        <button class="accordion-button px-0 bg-transparent shadow-none fw-semibold"
                                type="button" data-bs-toggle="collapse" data-bs-target="#specs-mobile">
                            Especificaciones
                        </button>
                    </h2>
                    <div id="specs-mobile" class="accordion-collapse collapse show">
                        <div class="accordion-body px-0">
                            <table class="table table-sm table-striped ficha-especificaciones">
                                @foreach($producto->especificaciones as $espec)
                                    <tr>
                                        <td class="fw-bold">{{ $espec->clave }}</td>
                                        <td>{{ $espec->valor }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Etiquetas --}}
        @if($producto->etiquetasPublicas->isNotEmpty())
            <div class="mb-3 d-flex flex-wrap gap-2">
                @foreach($producto->etiquetasPublicas as $etiqueta)
                    <span class="tienda-chip tienda-chip-grande">{{ $etiqueta->nombre }}: {{ $etiqueta->pivot->valor }}</span>
                @endforeach
            </div>
        @endif

    </div>

    {{-- Barra sticky de agregar al carrito --}}
    @if($producto->estaDisponible())
        <div class="position-fixed bottom-0 start-0 end-0 ficha-barra-compra">
            <form class="form-agregar">
                @csrf
                <div class="selector-cantidad selector-cantidad-grande d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-decrement" disabled>
                        <i class="bi bi-dash"></i>
                    </button>
                    <input type="number" name="cantidad" value="1" min="1"
                           @if($maxStock !== null) max="{{ $maxStock }}" @endif
                           class="form-control text-center input-cantidad">
                    <button type="button" class="btn btn-outline-secondary btn-increment" {{ $maxStock === 1 ? 'disabled' : '' }}>
                        <i class="bi bi-plus"></i>
                    </button>
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-cart-plus"></i> Agregar al carrito
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
