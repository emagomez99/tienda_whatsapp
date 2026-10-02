@php $tieneEspecificaciones = $producto->especificaciones->isNotEmpty(); @endphp
<div class="container pdp d-none d-md-block">
    <nav class="crumbs" aria-label="Ruta de navegación">
        <a href="{{ route('tienda.index') }}">Inicio</a>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">{{ $producto->descripcion }}</span>
    </nav>

    <div class="row g-4 g-xl-5">
        {{-- Galería --}}
        <div class="col-md-6">
            <div class="pdp-gallery">
                @if($galeria->count() > 1)
                    <div id="carousel-producto-desktop" class="carousel slide pdp-stage" data-bs-ride="false">
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
                        <button class="carousel-control-prev" type="button"
                                data-bs-target="#carousel-producto-desktop" data-bs-slide="prev">
                            <span class="pdp-arrow" aria-hidden="true"><i class="bi bi-chevron-left"></i></span>
                            <span class="visually-hidden">Imagen anterior</span>
                        </button>
                        <button class="carousel-control-next" type="button"
                                data-bs-target="#carousel-producto-desktop" data-bs-slide="next">
                            <span class="pdp-arrow" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                            <span class="visually-hidden">Imagen siguiente</span>
                        </button>
                    </div>
                    <div class="pdp-thumbs" id="thumbs-producto-desktop">
                        @foreach($galeria as $i => $img)
                            <button type="button"
                                    class="pdp-thumb{{ $i === 0 ? ' activa' : '' }}"
                                    data-bs-target="#carousel-producto-desktop"
                                    data-bs-slide-to="{{ $i }}"
                                    aria-current="{{ $i === 0 ? 'true' : 'false' }}"
                                    aria-label="Ver imagen {{ $i + 1 }}">
                                <img src="{{ $img->imagen_url }}" alt="" loading="lazy"
                                     onerror="this.onerror=null;this.src='/img/no-image.svg';">
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="pdp-stage">
                        <img src="{{ $producto->imagen_url ?? '/img/no-image.svg' }}"
                             alt="{{ $producto->descripcion }}"
                             class="img-fade"
                             onload="this.classList.add('is-loaded')"
                             onerror="this.onerror=null;this.src='/img/no-image.svg';this.classList.add('is-loaded');">
                    </div>
                @endif
            </div>
        </div>

        {{-- Información y compra --}}
        <div class="col-md-6">
            <h1 class="pdp-title">{{ $producto->descripcion }}</h1>
            @include('tienda.partials.producto-meta')

            <div class="pdp-buybox">
                @if($mostrarPrecios)
                    <div class="pdp-price">{{ $producto->precio_con_moneda }}</div>
                @endif

                @include('tienda.partials.producto-stock')

                @if($producto->estaDisponible())
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
                @endif

                <div class="pdp-secondary">
                    <button type="button" class="link-quiet btn-volver" data-volver="{{ route('tienda.index') }}">
                        <i class="bi bi-arrow-left"></i> Volver a la tienda
                    </button>
                    <button type="button" class="link-quiet btn-compartir">
                        <i class="bi bi-share"></i> Compartir
                    </button>
                </div>
            </div>

            @include('tienda.partials.producto-etiquetas')
        </div>
    </div>

    {{-- Descripción y especificaciones --}}
    @if($producto->detalle || $tieneEspecificaciones)
        <div class="row g-4 mt-2">
            @if($producto->detalle)
                <div class="{{ $tieneEspecificaciones ? 'col-lg-7' : 'col-12' }}">
                    <section class="pdp-section">
                        <h2 class="pdp-section-title">Descripción</h2>
                        <div class="pdp-detalle">{!! $producto->detalle !!}</div>
                    </section>
                </div>
            @endif
            @if($tieneEspecificaciones)
                <div class="{{ $producto->detalle ? 'col-lg-5' : 'col-12' }}">
                    <section class="pdp-section">
                        <h2 class="pdp-section-title">Especificaciones</h2>
                        @include('tienda.partials.producto-specs', ['dosColumnas' => !$producto->detalle])
                    </section>
                </div>
            @endif
        </div>
    @endif
</div>
