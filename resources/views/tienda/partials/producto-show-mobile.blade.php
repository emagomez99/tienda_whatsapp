<div class="d-block d-md-none">

    {{-- Imagen --}}
    <div class="bg-light d-flex align-items-center justify-content-center" style="height:260px;">
        <img src="{{ $producto->url_imagen ? $producto->imagen_url : '/img/no-image.svg' }}"
             alt="{{ $producto->descripcion }}"
             style="width:100%; height:100%; object-fit:contain;"
             onerror="this.onerror=null;this.src='/img/no-image.svg';">
    </div>

    <div class="px-3 pt-3 pb-5">

        {{-- Nombre + código --}}
        <h2 class="fs-5 fw-bold mb-1">{{ $producto->descripcion }}</h2>
        @if($producto->id_proveedor)
            <p class="text-muted small mb-2">Código: {{ $producto->id_proveedor }}</p>
        @endif
        @if($producto->proveedor && App\Models\Configuracion::mostrarProveedor())
            <p class="text-muted small mb-2">Proveedor: {{ $producto->proveedor->nombre }}</p>
        @endif

        {{-- Precio --}}
        @if($mostrarPrecios)
            <div class="fs-4 fw-bold text-primary mb-3">${{ number_format($producto->precio, 2) }}</div>
        @endif

        {{-- Stock --}}
        <div class="d-flex gap-2 flex-wrap mb-3">
            @if($producto->stock > 0)
                <span class="badge bg-success"><i class="bi bi-check-circle"></i> En stock ({{ $producto->stock }})</span>
            @elseif($producto->stock !== null)
                <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Sin stock</span>
            @endif
            @if($producto->por_encargue)
                <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Por encargue</span>
            @endif
        </div>

        {{-- Detalle colapsable --}}
        @if($producto->detalle)
            <div class="accordion mb-3" id="acc-detalle-mobile">
                <div class="accordion-item border-0 border-bottom">
                    <h2 class="accordion-header">
                        <button class="accordion-button px-0 bg-transparent shadow-none fw-semibold"
                                type="button" data-bs-toggle="collapse" data-bs-target="#detalle-mobile">
                            Descripción
                        </button>
                    </h2>
                    <div id="detalle-mobile" class="accordion-collapse collapse show">
                        <div class="accordion-body px-0">{!! $producto->detalle !!}</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Especificaciones colapsables --}}
        @if($producto->especificaciones->count() > 0)
            <div class="accordion mb-3" id="acc-specs-mobile">
                <div class="accordion-item border-0 border-bottom">
                    <h2 class="accordion-header">
                        <button class="accordion-button px-0 bg-transparent shadow-none fw-semibold"
                                type="button" data-bs-toggle="collapse" data-bs-target="#specs-mobile">
                            Especificaciones
                        </button>
                    </h2>
                    <div id="specs-mobile" class="accordion-collapse collapse show">
                        <div class="accordion-body px-0">
                            <table class="table table-sm table-striped">
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
        @if($producto->etiquetas->where('visible_usuarios', true)->count() > 0)
            <div class="mb-3">
                @foreach($producto->etiquetas->where('visible_usuarios', true) as $etiqueta)
                    <span class="badge bg-info me-1">{{ $etiqueta->nombre }}: {{ $etiqueta->pivot->valor }}</span>
                @endforeach
            </div>
        @endif

        {{-- Volver + Compartir --}}
        <div class="d-flex gap-2 mt-2">
            <a href="{{ route('tienda.index') }}" class="btn btn-outline-secondary flex-grow-1">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <button type="button" class="btn btn-outline-secondary btn-compartir" title="Compartir">
                <i class="bi bi-share"></i>
            </button>
        </div>
    </div>

    {{-- Barra sticky de agregar al carrito --}}
    @if($producto->estaDisponible())
        <div class="position-fixed bottom-0 start-0 end-0 bg-white border-top shadow-lg px-3 py-2" style="z-index:1030;">
            <form class="form-agregar">
                @csrf
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-decrement"
                            style="width:40px;height:40px;padding:0;flex-shrink:0;" disabled>
                        <i class="bi bi-dash"></i>
                    </button>
                    <input type="number" name="cantidad" value="1" min="1"
                           @if($maxStock !== null) max="{{ $maxStock }}" @endif
                           class="form-control text-center input-cantidad"
                           style="width:56px;height:40px;-moz-appearance:textfield;flex-shrink:0;">
                    <button type="button" class="btn btn-outline-secondary btn-increment"
                            style="width:40px;height:40px;padding:0;flex-shrink:0;" {{ $maxStock === 1 ? 'disabled' : '' }}>
                        <i class="bi bi-plus"></i>
                    </button>
                    <button type="submit" class="btn btn-primary flex-grow-1" style="height:40px;">
                        <i class="bi bi-cart-plus"></i> Agregar al carrito
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
