<div class="container py-4 d-none d-md-block">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item">
                <a href="{{ route('tienda.index') }}" class="text-decoration-none">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
            </li>
            @foreach($migas as $estante)
                <li class="breadcrumb-item"><a href="{{ $estante->url() }}" class="text-decoration-none">{{ $estante->nombre }}</a></li>
            @endforeach
            <li class="breadcrumb-item active text-truncate" aria-current="page">
                {{ $producto->descripcion }}
            </li>
        </ol>
    </nav>

    <div class="row g-4 g-lg-5">
        <div class="col-md-5">
            @php $galeria = $producto->galeria(); @endphp
            @if($galeria->count() > 1)
                <div id="carousel-producto-desktop" class="carousel slide ficha-galeria" data-bs-ride="false">
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
                            data-bs-target="#carousel-producto-desktop" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button"
                            data-bs-target="#carousel-producto-desktop" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                </div>
                <div class="ficha-puntos">
                    @foreach($galeria as $i => $img)
                        <button type="button"
                                class="ficha-punto {{ $i === 0 ? 'activo' : '' }}"
                                data-bs-target="#carousel-producto-desktop"
                                data-bs-slide-to="{{ $i }}"
                                aria-label="Imagen {{ $i + 1 }}"></button>
                    @endforeach
                </div>
            @else
                <div class="ficha-galeria d-flex align-items-center justify-content-center">
                    <img src="{{ $producto->imagen_url ?? '/img/no-image.svg' }}"
                         alt="{{ $producto->descripcion }}"
                         class="img-contenida img-aparece"
                         onload="this.style.opacity=1"
                         onerror="this.onerror=null;this.src='/img/no-image.svg';this.style.opacity=1;">
                </div>
            @endif
        </div>
        <div class="col-md-7">
            <h2 class="ficha-titulo mb-2">{{ $producto->descripcion }}</h2>

            @if($producto->id_proveedor)
                <p class="text-muted small mb-1">Código: {{ $producto->id_proveedor }}</p>
            @endif

            @if($producto->proveedor && App\Models\Configuracion::mostrarProveedor())
                <p class="text-muted small mb-1">Proveedor: {{ $producto->proveedor->nombre }}</p>
            @endif

            @if($producto->detalle)
                <div class="my-3 ficha-detalle">{!! $producto->detalle !!}</div>
                <hr>
            @endif

            @if($mostrarPrecios)
                <div class="ficha-precio text-primary my-3">{{ $producto->precio_con_moneda }}</div>
            @endif

            @if($producto->etiquetasPublicas->isNotEmpty())
                <div class="mb-3">
                    <div class="ficha-rotulo">Características:</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($producto->etiquetasPublicas as $etiqueta)
                            <span class="tienda-chip tienda-chip-grande">{{ $etiqueta->nombre }}: {{ $etiqueta->pivot->valor }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($producto->especificaciones->count() > 0)
                <div class="mb-3">
                    <div class="ficha-rotulo">Especificaciones:</div>
                    <table class="table table-sm table-striped ficha-especificaciones mt-2">
                        @foreach($producto->especificaciones as $espec)
                            <tr>
                                <td class="fw-bold">{{ $espec->clave }}</td>
                                <td>{{ $espec->valor }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif

            <div class="mb-3 d-flex gap-2 flex-wrap">
                {{-- Por encargue sólo importa cuando no hay stock; "Sin stock" en rojo al
                     lado de "Disponible por encargue" se leía como una contradicción. --}}
                @if($producto->stock > 0)
                    <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle"></i> En stock ({{ $producto->stock }} disponibles)</span>
                @elseif($producto->por_encargue)
                    <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-clock"></i> Sin stock ahora: lo encargamos para vos</span>
                @elseif($producto->stock !== null)
                    <span class="badge bg-danger-subtle text-danger-emphasis"><i class="bi bi-x-circle"></i> Sin stock</span>
                @endif
            </div>

            @if($producto->estaDisponible())
                <form class="form-agregar mt-4">
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
            @endif

            <div class="ficha-acciones d-flex gap-2 mt-3">
                <button type="button" onclick="history.length > 1 ? history.back() : window.location='{{ route('tienda.index') }}'" class="btn btn-outline-secondary flex-grow-1">
                    <i class="bi bi-arrow-left me-1"></i> Volver a la tienda
                </button>
                <button type="button" class="btn btn-outline-secondary btn-compartir">
                    <i class="bi bi-share me-1"></i> Compartir
                </button>
            </div>
        </div>
    </div>
</div>
