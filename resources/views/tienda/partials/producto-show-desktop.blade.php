<div class="container py-4 d-none d-md-block">
    <nav aria-label="breadcrumb" style="--bs-breadcrumb-divider: '›';">
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item">
                <a href="{{ route('tienda.index') }}" class="text-decoration-none">
                    <i class="bi bi-house-door"></i> Tienda
                </a>
            </li>
            <li class="breadcrumb-item active text-truncate" style="max-width: 480px;" aria-current="page">
                {{ $producto->descripcion }}
            </li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="rounded shadow overflow-hidden bg-light d-flex align-items-center justify-content-center"
                 style="height: 420px;">
                <img src="{{ $producto->url_imagen ? $producto->imagen_url : '/img/no-image.svg' }}"
                     alt="{{ $producto->descripcion }}"
                     style="width:100%; height:100%; object-fit:contain;"
                     onerror="this.onerror=null;this.src='/img/no-image.svg';">
            </div>
        </div>
        <div class="col-md-7">
            <h2 class="fs-3">{{ $producto->descripcion }}</h2>

            @if($producto->id_proveedor)
                <p class="text-muted mb-1">Código: {{ $producto->id_proveedor }}</p>
            @endif

            @if($producto->proveedor && App\Models\Configuracion::mostrarProveedor())
                <p class="text-muted mb-1">Proveedor: {{ $producto->proveedor->nombre }}</p>
            @endif

            @if($producto->detalle)
                <div class="my-3">{!! $producto->detalle !!}</div>
                <hr>
            @endif

            @if($mostrarPrecios)
                <h3 class="text-primary my-3">${{ number_format($producto->precio, 2) }}</h3>
            @endif

            @if($producto->etiquetas->where('visible_usuarios', true)->count() > 0)
                <div class="mb-3">
                    <strong>Etiquetas:</strong><br>
                    @foreach($producto->etiquetas->where('visible_usuarios', true) as $etiqueta)
                        <span class="badge bg-info me-1">{{ $etiqueta->nombre }}: {{ $etiqueta->pivot->valor }}</span>
                    @endforeach
                </div>
            @endif

            @if($producto->especificaciones->count() > 0)
                <div class="mb-3">
                    <strong>Especificaciones:</strong>
                    <table class="table table-sm table-striped mt-2">
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
                @if($producto->stock > 0)
                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> En stock ({{ $producto->stock }} disponibles)</span>
                @elseif($producto->stock !== null)
                    <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Sin stock</span>
                @endif
                @if($producto->por_encargue)
                    <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Disponible por encargue</span>
                @endif
            </div>

            @if($producto->estaDisponible())
                <form class="form-agregar mt-4">
                    @csrf
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-decrement"
                                style="width:38px;height:38px;padding:0;flex-shrink:0;" disabled>
                            <i class="bi bi-dash"></i>
                        </button>
                        <input type="number" name="cantidad" value="1" min="1"
                               @if($maxStock !== null) max="{{ $maxStock }}" @endif
                               class="form-control text-center input-cantidad"
                               style="width:60px;height:38px;-moz-appearance:textfield;flex-shrink:0;">
                        <button type="button" class="btn btn-outline-secondary btn-increment"
                                style="width:38px;height:38px;padding:0;flex-shrink:0;" {{ $maxStock === 1 ? 'disabled' : '' }}>
                            <i class="bi bi-plus"></i>
                        </button>
                        <button type="submit" class="btn btn-primary flex-grow-1" style="height:38px;">
                            <i class="bi bi-cart-plus"></i> Agregar al carrito
                        </button>
                    </div>
                </form>
            @endif

            <div class="d-flex gap-2 mt-3">
                <a href="{{ route('tienda.index') }}" class="btn btn-outline-secondary flex-grow-1" style="height:38px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-arrow-left me-1"></i> Volver a la tienda
                </a>
                <button type="button" class="btn btn-outline-secondary btn-compartir" style="height:38px;">
                    <i class="bi bi-share me-1"></i> Compartir
                </button>
            </div>
        </div>
    </div>
</div>
