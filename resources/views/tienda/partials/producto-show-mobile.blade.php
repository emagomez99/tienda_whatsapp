<div class="d-block d-md-none">

    {{-- Imagen con botones flotantes --}}
    <div class="pdp-m-stage">
        @if($galeria->count() > 1)
            <div id="carousel-producto-mobile" class="carousel slide" data-bs-ride="false">
                <div class="carousel-indicators">
                    @foreach($galeria as $i => $img)
                        <button type="button"
                                data-bs-target="#carousel-producto-mobile"
                                data-bs-slide-to="{{ $i }}"
                                @if($i === 0) class="active" aria-current="true" @endif
                                aria-label="Imagen {{ $i + 1 }}"></button>
                    @endforeach
                </div>
                <div class="carousel-inner">
                    @foreach($galeria as $i => $img)
                        <div class="carousel-item{{ $i === 0 ? ' active' : '' }}">
                            <img src="{{ $img->imagen_url }}"
                                 alt="{{ $producto->descripcion }}"
                                 class="img-fade"
                                 onload="this.classList.add('is-loaded')"
                                 onerror="this.onerror=null;this.src='/img/no-image.svg';this.classList.add('is-loaded');">
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <img src="{{ $producto->imagen_url ?? '/img/no-image.svg' }}"
                 alt="{{ $producto->descripcion }}"
                 class="img-fade"
                 onload="this.classList.add('is-loaded')"
                 onerror="this.onerror=null;this.src='/img/no-image.svg';this.classList.add('is-loaded');">
        @endif

        <button type="button" class="pdp-float-btn pdp-back btn-volver"
                data-volver="{{ route('tienda.index') }}" aria-label="Volver">
            <i class="bi bi-arrow-left"></i>
        </button>
        <button type="button" class="pdp-float-btn pdp-share btn-compartir" aria-label="Compartir">
            <i class="bi bi-share"></i>
        </button>
    </div>

    {{-- Hoja de información: se superpone al borde inferior de la imagen --}}
    <div class="pdp-m-sheet">
        <h1 class="pdp-title">{{ $producto->descripcion }}</h1>
        @include('tienda.partials.producto-meta')

        @if($mostrarPrecios)
            <div class="pdp-price">{{ $producto->precio_con_moneda }}</div>
        @endif

        @include('tienda.partials.producto-stock')
        @include('tienda.partials.producto-etiquetas')

        @if($producto->detalle)
            <div class="pdp-acc">
                <button class="pdp-acc-btn" type="button"
                        data-bs-toggle="collapse" data-bs-target="#detalle-mobile"
                        aria-controls="detalle-mobile" aria-expanded="true">
                    Descripción <i class="bi bi-chevron-down"></i>
                </button>
                <div id="detalle-mobile" class="collapse show">
                    <div class="pdp-acc-body pdp-detalle">{!! $producto->detalle !!}</div>
                </div>
            </div>
        @endif

        @if($producto->especificaciones->isNotEmpty())
            <div class="pdp-acc">
                <button class="pdp-acc-btn" type="button"
                        data-bs-toggle="collapse" data-bs-target="#specs-mobile"
                        aria-controls="specs-mobile" aria-expanded="true">
                    Especificaciones <i class="bi bi-chevron-down"></i>
                </button>
                <div id="specs-mobile" class="collapse show">
                    <div class="pdp-acc-body">
                        @include('tienda.partials.producto-specs')
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Barra fija de agregar al carrito --}}
    @if($producto->estaDisponible())
        <div class="pdp-m-bar">
            <form class="form-agregar pdp-comprar">
                @csrf
                <div class="qty-stepper qty-stepper-lg">
                    <button type="button" class="btn-decrement" aria-label="Restar uno" disabled>
                        <i class="bi bi-dash"></i>
                    </button>
                    <input type="number" name="cantidad" value="1" min="1"
                           @if($maxStock !== null) max="{{ $maxStock }}" @endif
                           class="input-cantidad" aria-label="Cantidad">
                    <button type="button" class="btn-increment" aria-label="Sumar uno" {{ $maxStock === 1 ? 'disabled' : '' }}>
                        <i class="bi bi-plus"></i>
                    </button>
                </div>
                <button type="submit" class="btn btn-primary btn-cta">
                    <i class="bi bi-cart-plus"></i> Agregar al carrito
                </button>
            </form>
        </div>
    @endif
</div>
