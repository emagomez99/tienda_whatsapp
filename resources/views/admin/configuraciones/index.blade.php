@extends('layouts.admin')

@section('title', 'Configuraciones')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-sliders"></i> Configuraciones</h3>
</div>

<form action="{{ route('admin.configuraciones.update') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')


<ul class="nav nav-tabs mb-0" id="tabs-config" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-apariencia" type="button" role="tab">
            <i class="bi bi-palette"></i> Apariencia
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-tienda" type="button" role="tab">
            <i class="bi bi-shop"></i> Tienda
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-redes" type="button" role="tab">
            <i class="bi bi-share"></i> Redes Sociales
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-seo" type="button" role="tab">
            <i class="bi bi-search"></i> SEO
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-whatsapp" type="button" role="tab">
            <i class="bi bi-whatsapp"></i> Pedidos
        </button>
    </li>
    @if(auth()->user()->esSuperAdmin())
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-avanzada" type="button" role="tab">
            <i class="bi bi-star-fill"></i> Avanzada
        </button>
    </li>
    @endif
</ul>

<div class="tab-content border border-top-0 rounded-bottom bg-white p-3 p-md-4">
<div class="tab-pane fade show active" id="pane-apariencia" role="tabpanel">
                    @php
                        $logoActual   = App\Models\Configuracion::logo();
                        $logoAlto     = (int) old('logo_alto', App\Models\Configuracion::logoAlto());
                        $colorActual  = App\Models\Configuracion::colorPrimario();
                        $colorElegido = old('color_primario', $colorActual->hex());
                        if (! App\Support\ColorHex::esValido($colorElegido)) {
                            $colorElegido = $colorActual->hex();
                        }
                        $colorPreview = App\Support\ColorHex::desde($colorElegido);
                        $mostrarNombrePreview = old('mostrar_nombre_tienda', App\Models\Configuracion::obtener('mostrar_nombre_tienda', 'true')) === 'true';
                    @endphp

                    {{-- Vista previa de la cabecera de la tienda. Refleja en tiempo real el
                         color, el logo (incluso uno recién elegido, antes de guardar), su
                         tamaño y el nombre. El texto pasa a oscuro solo si el color es claro. --}}
                    {{-- Fija bajo la barra del admin: sigue a la vista mientras se ajustan los controles de abajo. --}}
                    <div class="mb-4 pb-2 bg-white" style="position: sticky; top: 60px; z-index: 1010;">
                        <div class="form-label pt-2">Vista previa de la cabecera</div>
                        <div id="cabecera_preview" class="rounded px-3 d-flex align-items-center justify-content-between gap-3"
                             style="min-height: 72px; background-color: {{ $colorPreview->hex() }}; color: {{ $colorPreview->textoLegible()->hex() }};">
                            <div class="d-flex align-items-center gap-2 fw-bold text-truncate">
                                <img id="cabecera_preview_logo" src="{{ $logoActual ? url('storage/' . $logoActual) : '' }}" alt="Logo"
                                     class="{{ $logoActual ? '' : 'd-none' }}"
                                     style="height: {{ $logoAlto }}px; width: auto; max-width: 320px; object-fit: contain;">
                                <i id="cabecera_preview_icono" class="bi bi-shop {{ $logoActual ? 'd-none' : '' }}"></i>
                                <span id="cabecera_preview_nombre" class="{{ $logoActual && ! $mostrarNombrePreview ? 'd-none' : '' }}">{{ old('nombre_tienda', App\Models\Configuracion::nombreTienda()) }}</span>
                            </div>
                            <i class="bi bi-cart3 fs-5"></i>
                        </div>
                    </div>

                    {{-- Logo y favicon: tarjetas compactas con miniatura, botones y nombre del archivo
                         elegido. Los <input type="file"> van ocultos y se abren desde los botones. --}}
                    @php $faviconActual = App\Models\Configuracion::favicon(); @endphp
                    <div class="row g-3 mb-4">
                        <div class="col-lg-8">
                            <div class="ajuste-tarjeta h-100">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="imagen-miniatura" style="width: 128px; height: 72px;">
                                        <img id="logo_miniatura" src="{{ $logoActual ? url('storage/' . $logoActual) : '' }}" alt="Logo"
                                             class="{{ $logoActual ? '' : 'd-none' }}" style="max-height: 56px; max-width: 112px;">
                                        <i class="bi bi-image text-muted fs-3 {{ $logoActual ? 'd-none' : '' }}" data-miniatura-vacia="logo"></i>
                                    </div>
                                    <div class="flex-grow-1" style="min-width: 0;">
                                        <div class="fw-semibold">Logo</div>
                                        <div class="small text-muted text-truncate" id="logo_estado"
                                             data-texto-inicial="JPG, PNG o GIF, hasta 2 MB. Los bordes transparentes se recortan solos.">
                                            JPG, PNG o GIF, hasta 2 MB. Los bordes transparentes se recortan solos.
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <label for="logo" class="btn btn-sm btn-outline-primary mb-0">
                                                <i class="bi bi-upload"></i> {{ $logoActual ? 'Cambiar' : 'Subir' }}
                                            </label>
                                            @if($logoActual)
                                                <input type="checkbox" class="btn-check" id="eliminar_logo" name="eliminar_logo" value="1" autocomplete="off">
                                                <label class="btn btn-sm btn-outline-danger mb-0" for="eliminar_logo">
                                                    <i class="bi bi-trash"></i> Quitar
                                                </label>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <input type="file" class="d-none" id="logo" name="logo" accept="image/*"
                                       data-miniatura="logo_miniatura" data-estado="logo_estado" data-quitar="eliminar_logo">
                                @error('logo')
                                    <div class="small text-danger mt-2">{{ $message }}</div>
                                @enderror

                                <div class="mt-3">
                                    <label for="logo_alto" class="form-label d-flex justify-content-between small mb-0">
                                        <span>Tamaño en la tienda</span>
                                        <span class="text-muted"><span id="logo_alto_valor">{{ $logoAlto }}</span> px de alto · en celular hasta 44 px</span>
                                    </label>
                                    <input type="range" class="form-range" id="logo_alto" name="logo_alto"
                                           min="{{ App\Models\Configuracion::LOGO_ALTO_MIN }}" max="{{ App\Models\Configuracion::LOGO_ALTO_MAX }}" step="2"
                                           value="{{ $logoAlto }}">
                                    @error('logo_alto')
                                        <div class="small text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="ajuste-tarjeta h-100">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="imagen-miniatura" style="width: 56px; height: 56px;">
                                        <img id="favicon_miniatura" src="{{ $faviconActual ? url('storage/' . $faviconActual) : '' }}" alt="Favicon"
                                             class="{{ $faviconActual ? '' : 'd-none' }}" style="width: 32px; height: 32px; object-fit: contain;">
                                        <i class="bi bi-app text-muted fs-4 {{ $faviconActual ? 'd-none' : '' }}" data-miniatura-vacia="favicon"></i>
                                    </div>
                                    <div class="flex-grow-1" style="min-width: 0;">
                                        <div class="fw-semibold">Favicon</div>
                                        <div class="small text-muted text-truncate" id="favicon_estado"
                                             data-texto-inicial="ICO, PNG, JPG o SVG, 32×32 o 64×64.">
                                            ICO, PNG, JPG o SVG, 32×32 o 64×64.
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <label for="favicon" class="btn btn-sm btn-outline-primary mb-0">
                                                <i class="bi bi-upload"></i> {{ $faviconActual ? 'Cambiar' : 'Subir' }}
                                            </label>
                                            @if($faviconActual)
                                                <input type="checkbox" class="btn-check" id="eliminar_favicon" name="eliminar_favicon" value="1" autocomplete="off">
                                                <label class="btn btn-sm btn-outline-danger mb-0" for="eliminar_favicon">
                                                    <i class="bi bi-trash"></i> Quitar
                                                </label>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <input type="file" class="d-none" id="favicon" name="favicon" accept=".ico,.png,.jpg,.jpeg,.svg"
                                       data-miniatura="favicon_miniatura" data-estado="favicon_estado" data-quitar="eliminar_favicon">
                                @error('favicon')
                                    <div class="small text-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    @php $posicionMenu = old('posicion_menu', App\Models\Configuracion::posicionMenu()); @endphp
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <div class="ajuste-tarjeta h-100">
                                <label for="nombre_tienda" class="fw-semibold d-block">Nombre de la tienda</label>
                                <input type="text" class="form-control mt-2 @error('nombre_tienda') is-invalid @enderror" id="nombre_tienda" name="nombre_tienda"
                                       value="{{ old('nombre_tienda', App\Models\Configuracion::obtener('nombre_tienda', 'Tienda MC')) }}">
                                @error('nombre_tienda')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-check form-switch mt-2 mb-0">
                                    <input type="hidden" name="mostrar_nombre_tienda" value="false">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="mostrar_nombre_tienda" name="mostrar_nombre_tienda" value="true" {{ $mostrarNombrePreview ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="mostrar_nombre_tienda">Mostrarlo junto al logo</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-5 col-lg-3">
                            <div class="ajuste-tarjeta h-100">
                                <label for="color_primario" class="fw-semibold d-block">Color principal</label>
                                <div class="small text-muted">Cabecera, botones y menú.</div>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <input type="color" class="form-control form-control-color @error('color_primario') is-invalid @enderror"
                                           id="color_primario" name="color_primario" value="{{ $colorElegido }}" title="Elegir color">
                                    <span id="color_primario_valor" class="font-monospace text-muted">{{ $colorElegido }}</span>
                                </div>
                                @error('color_primario')
                                    <div class="small text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-sm-7 col-lg-4">
                            <div class="ajuste-tarjeta h-100">
                                <div class="fw-semibold">Menú de la tienda</div>
                                <div class="small text-muted">Dónde se muestran las categorías.</div>
                                <div class="d-flex gap-2 mt-2">
                                    <input type="radio" class="btn-check" name="posicion_menu" id="menu_superior" value="superior" autocomplete="off" {{ $posicionMenu === 'superior' ? 'checked' : '' }}>
                                    <label class="ajuste-opcion flex-fill text-center" for="menu_superior">
                                        <i class="bi bi-distribute-horizontal d-block fs-5"></i><span class="small">Barra superior</span>
                                    </label>
                                    <input type="radio" class="btn-check" name="posicion_menu" id="menu_lateral" value="lateral" autocomplete="off" {{ $posicionMenu === 'lateral' ? 'checked' : '' }}>
                                    <label class="ajuste-opcion flex-fill text-center" for="menu_lateral">
                                        <i class="bi bi-layout-sidebar d-block fs-5"></i><span class="small">Menú lateral</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
</div>
<div class="tab-pane fade" id="pane-tienda" role="tabpanel">
                    <div class="ajuste-seccion">Qué ve el cliente</div>
                    <div class="row g-3 mb-4">
                        <div class="col-lg-7">
                            <div class="row g-3">
                            <div class="col-12">
                                @include('admin.configuraciones.partials.interruptor', [
                                    'nombre' => 'mostrar_precios',
                                    'titulo' => 'Mostrar los precios',
                                    'ayuda'  => 'Si se ocultan, no aparecen en ningún lugar de la tienda.',
                                    'activo' => App\Models\Configuracion::obtener('mostrar_precios', 'true') === 'true',
                                ])
                            </div>
                            <div class="col-12">
                                @include('admin.configuraciones.partials.interruptor', [
                                    'nombre' => 'mostrar_productos_sin_stock',
                                    'titulo' => 'Mostrar productos sin stock',
                                    'ayuda'  => 'Si se ocultan, solo se ven los disponibles.',
                                    'activo' => App\Models\Configuracion::obtener('mostrar_productos_sin_stock', 'true') === 'true',
                                ])
                            </div>
                            <div class="col-12">
                                @include('admin.configuraciones.partials.interruptor', [
                                    'nombre' => 'mostrar_proveedor',
                                    'titulo' => 'Mostrar el proveedor',
                                    'ayuda'  => 'En la ficha de cada producto.',
                                    'activo' => App\Models\Configuracion::obtener('mostrar_proveedor', 'false') === 'true',
                                ])
                            </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            {{-- Vista previa del listado y de la ficha de producto: responde en tiempo
                                 real a los tres switches de la izquierda, con el color elegido en
                                 Apariencia. Usa productos y proveedores reales de la tienda si los hay. --}}
                            <div class="ajuste-tarjeta" id="preview_cards" style="position: sticky; top: 76px;">
                                <div class="small text-muted mb-2">Vista previa en el listado</div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        @include('admin.configuraciones.partials.card-producto-preview', ['producto' => $ejemploConStock, 'sinStock' => false, 'ejemplo' => 'Producto de ejemplo'])
                                    </div>
                                    <div class="col-6">
                                        <div id="preview_card_sin_stock" class="h-100">
                                            @include('admin.configuraciones.partials.card-producto-preview', ['producto' => $ejemploSinStock, 'sinStock' => true, 'ejemplo' => 'Producto agotado'])
                                        </div>
                                        <div id="preview_card_oculta" class="preview-card-oculta h-100 d-none">
                                            <i class="bi bi-eye-slash fs-4"></i>
                                            <span class="small">Los productos sin stock no se muestran</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- El proveedor no está en la card del listado sino en la ficha (ver
                                     tienda/partials/producto-show-desktop.blade.php): se muestra ahí. --}}
                                <div class="small text-muted mt-3 mb-2">Vista previa en la ficha del producto</div>
                                <div class="border rounded p-2 small bg-white">
                                    <div class="fw-semibold">{{ $ejemploConStock ? $ejemploConStock->descripcion : 'Producto de ejemplo' }}</div>
                                    <div class="text-muted">Código: {{ $ejemploConStock && $ejemploConStock->id_proveedor ? $ejemploConStock->id_proveedor : 'ABC-123' }}</div>
                                    <div class="text-muted" data-preview-proveedor>Proveedor: {{ $proveedorEjemplo }}</div>
                                    <div class="fw-bold preview-card-precio mt-1" data-preview-precio>
                                        {{ $ejemploConStock ? $ejemploConStock->precio_con_moneda : (optional(App\Models\Moneda::base())->simbolo ?: '$') . number_format(15000, 2) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- "¿En qué moneda cobrás?" y "¿en qué moneda vendés más?" van juntas y acá,
                         no en el ABM de monedas: son decisiones de la tienda, no propiedades de
                         una moneda. Repartidas en dos pantallas obligaban a entender la
                         diferencia para saber dónde buscar cada una. --}}
                    @if(auth()->user()->puede('monedas.ver') && $monedas->isNotEmpty())
                    @php
                        $monedaTienda   = App\Models\Moneda::base();
                        $monedaFavorita = App\Models\Moneda::porDefecto();
                    @endphp
                    <div class="ajuste-seccion">Monedas</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="ajuste-tarjeta h-100">
                                <label for="moneda_tienda" class="fw-semibold d-block">¿En qué moneda cobrás?</label>
                                <div class="small text-muted">Las cotizaciones de las demás se escriben en ésta.</div>
                                <select class="form-select mt-2 @error('moneda_tienda') is-invalid @enderror" id="moneda_tienda" name="moneda_tienda">
                                    @foreach($monedas as $moneda)
                                        <option value="{{ $moneda->id }}"
                                            {{ old('moneda_tienda', $monedaTienda ? $monedaTienda->id : null) == $moneda->id ? 'selected' : '' }}>
                                            {{ $moneda->nombre }} ({{ $moneda->simbolo }} {{ $moneda->codigo }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('moneda_tienda')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="ajuste-tarjeta h-100">
                                <label for="moneda_favorita" class="fw-semibold d-block">¿En qué moneda vendés más?</label>
                                <div class="small text-muted">Viene elegida al cargar un producto nuevo.</div>
                                <select class="form-select mt-2 @error('moneda_favorita') is-invalid @enderror" id="moneda_favorita" name="moneda_favorita">
                                    <option value="">Ninguna en particular</option>
                                    @foreach($monedas as $moneda)
                                        <option value="{{ $moneda->id }}"
                                            {{ old('moneda_favorita', $monedaFavorita ? $monedaFavorita->id : null) == $moneda->id ? 'selected' : '' }}>
                                            {{ $moneda->nombre }} ({{ $moneda->simbolo }} {{ $moneda->codigo }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('moneda_favorita')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    @endif
</div>
<div class="tab-pane fade" id="pane-redes" role="tabpanel">
                    <div class="ajuste-tarjeta">
                        <div class="fw-semibold">Redes sociales</div>
                        <div class="small text-muted mb-3">Aparecen como íconos en el pie de la tienda. Dejá vacías las que no uses; para WhatsApp alcanza con el número.</div>
                        <div class="row g-2">
                        <div class="col-md-6">
                            <label for="social_instagram" class="visually-hidden">Instagram</label>
                            <div class="input-group">
                                <span class="input-group-text" title="Instagram" style="width: 2.75rem; justify-content: center;"><i class="bi bi-instagram" style="color: #E1306C;"></i></span>
                                <input type="url" class="form-control @error('social_instagram') is-invalid @enderror"
                                       id="social_instagram" name="social_instagram" placeholder="https://instagram.com/tutienda"
                                       value="{{ old('social_instagram', App\Models\Configuracion::socialInstagram()) }}">
                                @error('social_instagram')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="social_facebook" class="visually-hidden">Facebook</label>
                            <div class="input-group">
                                <span class="input-group-text" title="Facebook" style="width: 2.75rem; justify-content: center;"><i class="bi bi-facebook" style="color: #1877F2;"></i></span>
                                <input type="url" class="form-control @error('social_facebook') is-invalid @enderror"
                                       id="social_facebook" name="social_facebook" placeholder="https://facebook.com/tutienda"
                                       value="{{ old('social_facebook', App\Models\Configuracion::socialFacebook()) }}">
                                @error('social_facebook')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="social_twitter" class="visually-hidden">Twitter / X</label>
                            <div class="input-group">
                                <span class="input-group-text" title="Twitter / X" style="width: 2.75rem; justify-content: center;"><i class="bi bi-twitter-x"></i></span>
                                <input type="url" class="form-control @error('social_twitter') is-invalid @enderror"
                                       id="social_twitter" name="social_twitter" placeholder="https://x.com/tutienda"
                                       value="{{ old('social_twitter', App\Models\Configuracion::socialTwitter()) }}">
                                @error('social_twitter')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="social_tiktok" class="visually-hidden">TikTok</label>
                            <div class="input-group">
                                <span class="input-group-text" title="TikTok" style="width: 2.75rem; justify-content: center;"><i class="bi bi-tiktok"></i></span>
                                <input type="url" class="form-control @error('social_tiktok') is-invalid @enderror"
                                       id="social_tiktok" name="social_tiktok" placeholder="https://tiktok.com/@tutienda"
                                       value="{{ old('social_tiktok', App\Models\Configuracion::socialTiktok()) }}">
                                @error('social_tiktok')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="social_youtube" class="visually-hidden">YouTube</label>
                            <div class="input-group">
                                <span class="input-group-text" title="YouTube" style="width: 2.75rem; justify-content: center;"><i class="bi bi-youtube" style="color: #FF0000;"></i></span>
                                <input type="url" class="form-control @error('social_youtube') is-invalid @enderror"
                                       id="social_youtube" name="social_youtube" placeholder="https://youtube.com/@tutienda"
                                       value="{{ old('social_youtube', App\Models\Configuracion::socialYoutube()) }}">
                                @error('social_youtube')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="social_whatsapp" class="visually-hidden">WhatsApp</label>
                            <div class="input-group">
                                <span class="input-group-text" title="WhatsApp" style="width: 2.75rem; justify-content: center;"><i class="bi bi-whatsapp" style="color: #25D366;"></i></span>
                                <input type="text" class="form-control @error('social_whatsapp') is-invalid @enderror"
                                       id="social_whatsapp" name="social_whatsapp" placeholder="+54 9 11 1234 5678"
                                       value="{{ old('social_whatsapp', App\Models\Configuracion::socialWhatsapp()) }}">
                                @error('social_whatsapp')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        </div>
                    </div>
</div>
<div class="tab-pane fade" id="pane-seo" role="tabpanel">
                    <div class="ajuste-seccion">Cómo se ve en Google</div>
                    <div class="row g-3 mb-4">
                        <div class="col-lg-7">
                            <div class="ajuste-tarjeta h-100">
                                <div class="small text-muted mb-2">Se usa en las páginas que no tienen su propio título o descripción. Conviene mencionar tus marcas o rubros: es lo que la gente busca.</div>
                                <div class="mb-2">
                                    <label for="seo_titulo_default" class="form-label small mb-1 d-flex justify-content-between">
                                        <span>Título</span><span class="text-muted contador-caracteres" data-max="60" data-para="seo_titulo_default">0/60</span>
                                    </label>
                                    <input type="text" class="form-control @error('seo_titulo_default') is-invalid @enderror"
                                           id="seo_titulo_default" name="seo_titulo_default" maxlength="60"
                                           value="{{ old('seo_titulo_default', App\Models\Configuracion::seoTituloDefault()) }}">
                                    @error('seo_titulo_default')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-2">
                                    <label for="seo_descripcion_default" class="form-label small mb-1 d-flex justify-content-between">
                                        <span>Descripción</span><span class="text-muted contador-caracteres" data-max="160" data-para="seo_descripcion_default">0/160</span>
                                    </label>
                                    <textarea class="form-control @error('seo_descripcion_default') is-invalid @enderror"
                                              id="seo_descripcion_default" name="seo_descripcion_default" rows="2" maxlength="160">{{ old('seo_descripcion_default', App\Models\Configuracion::seoDescripcionDefault()) }}</textarea>
                                    @error('seo_descripcion_default')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div>
                                    <label for="seo_keywords" class="form-label small mb-1">Palabras clave <span class="text-muted">(separadas por coma)</span></label>
                                    <input type="text" class="form-control @error('seo_keywords') is-invalid @enderror"
                                           id="seo_keywords" name="seo_keywords"
                                           value="{{ old('seo_keywords', App\Models\Configuracion::seoKeywords()) }}">
                                    @error('seo_keywords')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            {{-- Vista previa del resultado de búsqueda, en tiempo real. --}}
                            <div class="ajuste-tarjeta h-100">
                                <div class="small text-muted mb-2">Vista previa</div>
                                <div class="small text-muted text-truncate">{{ request()->getHost() }}</div>
                                <div id="seo_preview_titulo" class="text-truncate" style="color: #1a0dab; font-size: 1.15rem;"
                                     data-vacio="{{ App\Models\Configuracion::nombreTienda() }}"></div>
                                <div id="seo_preview_descripcion" class="small" style="color: #4d5156;"
                                     data-vacio="Sin descripción: Google va a tomar un fragmento del contenido de la página."></div>
                            </div>
                        </div>
                    </div>

                    <div class="ajuste-seccion">Ubicación (SEO local)</div>
                    @php $ubicacionActiva = old('ubicacion_activa', App\Models\Configuracion::ubicacionActiva() ? 'true' : 'false') === 'true'; @endphp
                    <div class="ajuste-tarjeta mb-4">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <label for="ubicacion_activa" class="mb-0">
                                <span class="fw-semibold d-block">Declarar la ubicación de la tienda</span>
                                <span class="small text-muted">Ayuda a que Google la asocie con su ciudad. Desactivado, los datos se guardan pero no se usan.</span>
                            </label>
                            <div class="form-check form-switch m-0 flex-shrink-0">
                                <input type="hidden" name="ubicacion_activa" value="false">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="ubicacion_activa" name="ubicacion_activa" value="true" {{ $ubicacionActiva ? 'checked' : '' }}>
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-md-5">
                                <label for="ciudad" class="form-label small mb-1">Ciudad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('ciudad') is-invalid @enderror"
                                       id="ciudad" name="ciudad" placeholder="Bahía Blanca"
                                       value="{{ old('ciudad', App\Models\Configuracion::ciudad()) }}">
                                @error('ciudad')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="provincia" class="form-label small mb-1">Provincia</label>
                                <input type="text" class="form-control @error('provincia') is-invalid @enderror"
                                       id="provincia" name="provincia" placeholder="Buenos Aires"
                                       value="{{ old('provincia', App\Models\Configuracion::provincia()) }}">
                                @error('provincia')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label for="codigo_postal" class="form-label small mb-1">Código postal</label>
                                <input type="text" class="form-control @error('codigo_postal') is-invalid @enderror"
                                       id="codigo_postal" name="codigo_postal" placeholder="B8000"
                                       value="{{ old('codigo_postal', App\Models\Configuracion::codigoPostal()) }}">
                                @error('codigo_postal')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label for="direccion" class="form-label small mb-1">Dirección del local <span class="text-muted">(opcional)</span></label>
                                <input type="text" class="form-control @error('direccion') is-invalid @enderror"
                                       id="direccion" name="direccion" placeholder="Ej: Alsina 250"
                                       value="{{ old('direccion', App\Models\Configuracion::direccion()) }}">
                                @error('direccion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="small text-muted mt-1">Solo si un cliente puede venir a comprar o retirar. Si vendés online o despachás desde un depósito, dejala vacía.</div>
                            </div>
                        </div>
                    </div>

                    <div class="ajuste-seccion">Google</div>
                    <div class="row g-3">
                        <div class="col-lg-4">
                            @include('admin.configuraciones.partials.interruptor', [
                                'nombre' => 'robots_index',
                                'titulo' => 'Permitir que Google indexe la tienda',
                                'ayuda'  => 'Desactivalo mientras armás la tienda.',
                                'activo' => App\Models\Configuracion::robotsIndex(),
                            ])
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="ajuste-tarjeta h-100">
                                <label for="google_analytics_id" class="fw-semibold d-block">Google Analytics (GA4)</label>
                                <input type="text" class="form-control mt-2 @error('google_analytics_id') is-invalid @enderror"
                                       id="google_analytics_id" name="google_analytics_id" placeholder="G-XXXXXXXXXX"
                                       value="{{ old('google_analytics_id', App\Models\Configuracion::googleAnalyticsId()) }}">
                                @error('google_analytics_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="ajuste-tarjeta h-100">
                                <label for="google_site_verification" class="fw-semibold d-block">Verificación de Search Console</label>
                                <input type="text" class="form-control mt-2 @error('google_site_verification') is-invalid @enderror"
                                       id="google_site_verification" name="google_site_verification"
                                       value="{{ old('google_site_verification', App\Models\Configuracion::googleSiteVerification()) }}">
                                @error('google_site_verification')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
</div>
<div class="tab-pane fade" id="pane-whatsapp" role="tabpanel">
                    <div class="ajuste-seccion">Cómo llega el pedido</div>
                    <div class="row g-3 mb-4">
                        <div class="col-lg-6">
                            <div class="ajuste-tarjeta h-100">
                                @include('partials.intl-tel-input', [
                                    'inputId'   => 'whatsapp-input',
                                    'fieldName' => 'whatsapp_admin',
                                    'value'     => App\Models\Configuracion::obtener('whatsapp_admin', ''),
                                    'label'     => 'WhatsApp de la tienda',
                                ])
                                <div class="small text-muted mt-1">Adonde llegan los pedidos de los clientes.</div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            @include('admin.configuraciones.partials.interruptor', [
                                'nombre' => 'pedir_direccion_envio',
                                'titulo' => 'Pedir dirección de envío',
                                'ayuda'  => 'Al finalizar el pedido. Si se desactiva, el cliente no la carga.',
                                'activo' => App\Models\Configuracion::obtener('pedir_direccion_envio', 'true') === 'true',
                            ])
                        </div>
                    </div>

                    <div class="ajuste-seccion">Mensaje de WhatsApp</div>
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="ajuste-tarjeta h-100">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <label for="template_whatsapp" class="fw-semibold d-block">Plantilla</label>
                                        <div class="small text-muted">
                                            Usá <code>*texto*</code> para negrita y <code>_texto_</code> para cursiva.
                                            Lo que quede vacío (por ejemplo la dirección, si no se pide) se quita solo, con su título.
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" id="btn-reset-template">
                                        <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                    </button>
                                </div>
                                <textarea class="form-control font-monospace mt-2 @error('template_whatsapp') is-invalid @enderror"
                                          id="template_whatsapp" name="template_whatsapp" rows="14"
                                          placeholder="{{ App\Models\Configuracion::templateWhatsappDefault() }}">{{ App\Models\Configuracion::templateWhatsapp() }}</textarea>
                                @error('template_whatsapp')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="small text-muted mt-3 mb-1">Variables: tocá una para insertarla donde está el cursor. Pasá el mouse para ver qué contiene.</div>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach(App\Support\MensajeWhatsapp::VARIABLES as $variable => $descripcion)
                                        <button type="button" class="btn btn-sm btn-light border font-monospace variable-whatsapp"
                                                data-variable="{{ '{' . $variable . '}' }}" title="{{ $descripcion }}">{{ '{' . $variable . '}' }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            {{-- Vista previa con un pedido de ejemplo. La arma el servidor con la misma
                                 clase que el envío real (MensajeWhatsapp), así es exactamente lo que llega. --}}
                            <div class="whatsapp-preview" style="position: sticky; top: 76px;">
                                <div class="whatsapp-preview-cabecera">
                                    <i class="bi bi-whatsapp"></i>
                                    <span class="fw-semibold">Vista previa</span>
                                    <span class="small opacity-75 ms-auto">pedido de ejemplo</span>
                                </div>
                                <div class="whatsapp-preview-chat">
                                    <div class="whatsapp-preview-burbuja">
                                        <div id="whatsapp-preview-texto"></div>
                                        <div class="whatsapp-preview-hora">12:34 <i class="bi bi-check2-all"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="small text-muted mt-2">
                                Respeta "Pedir dirección de envío" (arriba) y "Mostrar los precios" (pestaña Tienda), aunque no los hayas guardado.
                            </div>
                        </div>
                    </div>
</div>
<div class="tab-pane fade" id="pane-avanzada" role="tabpanel">
    @if(auth()->user()->esSuperAdmin())
                    @php
                        $modoActual            = old('modo_imagen_producto', App\Models\Configuracion::modoImagenProducto());
                        $imgAdicionalesActivas = App\Models\Configuracion::imagenesAdicionalesActivas();
                        $maxImgAdicionales     = App\Models\Configuracion::maxImagenesAdicionales();
                    @endphp
                    <div class="small text-muted mb-3">
                        <span class="badge bg-warning text-dark me-1">Superadmin</span> Solo vos ves estos ajustes.
                    </div>

                    <div class="ajuste-seccion">Imágenes de productos</div>
                    <div class="ajuste-tarjeta mb-3">
                        <div class="fw-semibold">Cómo se cargan</div>
                        <div class="small text-muted">Qué opciones tienen los usuarios al crear o editar un producto.</div>
                        <div class="row g-2 mt-1">
                            <div class="col-md-4">
                                <input type="radio" class="btn-check" name="modo_imagen_producto" id="modo_ambos" value="ambos" autocomplete="off" {{ $modoActual === 'ambos' ? 'checked' : '' }}>
                                <label class="ajuste-opcion h-100" for="modo_ambos">
                                    <span class="fw-semibold d-block"><i class="bi bi-images"></i> Ambos</span>
                                    <span class="small text-muted">Subir un archivo o pegar una URL externa.</span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <input type="radio" class="btn-check" name="modo_imagen_producto" id="modo_solo_url" value="solo_url" autocomplete="off" {{ $modoActual === 'solo_url' ? 'checked' : '' }}>
                                <label class="ajuste-opcion h-100" for="modo_solo_url">
                                    <span class="fw-semibold d-block"><i class="bi bi-link-45deg"></i> Solo URL</span>
                                    <span class="small text-muted">Solo una URL externa. <span class="text-success">No usa espacio en el servidor.</span></span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <input type="radio" class="btn-check" name="modo_imagen_producto" id="modo_solo_archivo" value="solo_archivo" autocomplete="off" {{ $modoActual === 'solo_archivo' ? 'checked' : '' }}>
                                <label class="ajuste-opcion h-100" for="modo_solo_archivo">
                                    <span class="fw-semibold d-block"><i class="bi bi-upload"></i> Solo archivo</span>
                                    <span class="small text-muted">Solo subir al servidor. <span class="text-danger">Consume espacio.</span></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-7">
                            @include('admin.configuraciones.partials.interruptor', [
                                'nombre' => 'imagenes_adicionales_activas',
                                'titulo' => 'Imágenes adicionales por producto',
                                'ayuda'  => 'Fotos extra además de la portada, en un carrusel en la ficha.',
                                'activo' => $imgAdicionalesActivas,
                            ])
                        </div>
                        <div class="col-md-5">
                            <div class="ajuste-tarjeta h-100">
                                <label for="max_imagenes_adicionales" class="fw-semibold d-block">Máximo por producto</label>
                                <div class="small text-muted">Sin contar la portada. Entre 1 y 20.</div>
                                <div class="input-group mt-2" style="max-width: 160px;">
                                    <input type="number" class="form-control @error('max_imagenes_adicionales') is-invalid @enderror"
                                           id="max_imagenes_adicionales" name="max_imagenes_adicionales"
                                           value="{{ old('max_imagenes_adicionales', $maxImgAdicionales) }}" min="1" max="20">
                                    <span class="input-group-text">fotos</span>
                                </div>
                                @error('max_imagenes_adicionales')
                                    <div class="small text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
    @endif
</div>
</div>

    <div class="position-sticky bottom-0 bg-white border-top py-2 mt-3" style="z-index:5;">
        <div class="d-flex justify-content-end align-items-center gap-3">
            <span class="small text-muted d-none d-sm-inline">Los cambios de todas las pestañas se guardan juntos.</span>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check2"></i> Guardar
            </button>
        </div>
    </div>
</form>

@push('styles')
<style>
    .ajuste-seccion {
        font-size: .75rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--bs-secondary-color, #6c757d);
        margin-bottom: .5rem;
    }
    .ajuste-tarjeta {
        border: 1px solid var(--bs-border-color);
        border-radius: .5rem;
        padding: 1rem;
        background: #fff;
    }
    .ajuste-interruptor { cursor: pointer; }
    .ajuste-interruptor:hover { border-color: #adb5bd; }
    .ajuste-opcion {
        display: block;
        border: 1px solid var(--bs-border-color);
        border-radius: .5rem;
        padding: .6rem .75rem;
        cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease;
    }
    .ajuste-opcion:hover { border-color: #adb5bd; }
    .btn-check:checked + .ajuste-opcion {
        border-color: var(--bs-primary);
        background-color: rgba(var(--bs-primary-rgb), .08);
        box-shadow: inset 0 0 0 1px var(--bs-primary);
    }
    .btn-check:focus-visible + .ajuste-opcion { outline: 2px solid var(--bs-primary); outline-offset: 2px; }
    .preview-card { font-size: .85rem; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 6px rgba(0,0,0,.07); border: none; }
    .preview-card-nombre { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .preview-card-precio { color: var(--preview-primario, #0d6efd); font-size: 1rem; }
    .preview-card-boton { background: var(--preview-primario, #0d6efd) !important; border-color: var(--preview-primario, #0d6efd) !important; color: var(--preview-primario-texto, #fff) !important; opacity: 1 !important; }
    .preview-card-agotado {
        position: absolute; inset: 0; background: rgba(255,255,255,.6);
        display: flex; align-items: center; justify-content: center;
    }
    .preview-card-agotado span { background: rgba(0,0,0,.48); color: #fff; padding: .15rem .6rem; border-radius: 4px; font-size: .75rem; }
    .preview-card-oculta {
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .25rem;
        min-height: 200px; border: 2px dashed var(--bs-border-color); border-radius: 10px;
        color: var(--bs-secondary-color, #6c757d); text-align: center; padding: .75rem;
    }
    .whatsapp-preview { border-radius: .75rem; overflow: hidden; border: 1px solid var(--bs-border-color); }
    .whatsapp-preview-cabecera {
        display: flex; align-items: center; gap: .5rem;
        background: #075e54; color: #fff; padding: .6rem .9rem;
    }
    .whatsapp-preview-chat { background: #efeae2; padding: 1rem; max-height: 520px; overflow-y: auto; }
    .whatsapp-preview-burbuja {
        background: #d9fdd3; border-radius: .5rem .5rem 0 .5rem; padding: .5rem .65rem .3rem;
        margin-left: 12%; box-shadow: 0 1px .5px rgba(0,0,0,.13);
        font-size: .875rem; line-height: 1.4; word-break: break-word;
    }
    #whatsapp-preview-texto { white-space: pre-wrap; }
    .whatsapp-preview-burbuja.cargando { opacity: .6; }
    .whatsapp-preview-hora { text-align: right; font-size: .68rem; color: #667781; margin-top: .15rem; }
    .whatsapp-preview-hora .bi { color: #53bdeb; }
    .imagen-miniatura {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--bs-border-color);
        border-radius: .375rem;
        background-color: #fff;
        background-image:
            linear-gradient(45deg, #eee 25%, transparent 25%), linear-gradient(-45deg, #eee 25%, transparent 25%),
            linear-gradient(45deg, transparent 75%, #eee 75%), linear-gradient(-45deg, transparent 75%, #eee 75%);
        background-size: 12px 12px;
        background-position: 0 0, 0 6px, 6px -6px, -6px 0;
        transition: opacity .15s ease;
    }
    .imagen-miniatura.se-quita { opacity: .35; }
</style>
@endpush

@push('scripts')
<script>
document.getElementById('btn-reset-template').addEventListener('click', function () {
    var plantilla = document.getElementById('template_whatsapp');
    plantilla.value = @json(App\Models\Configuracion::templateWhatsappDefault());
    plantilla.dispatchEvent(new Event('input'));   // actualiza la vista previa
});

// Contador de caracteres para los campos de SEO
document.querySelectorAll('.contador-caracteres').forEach(function (contador) {
    var campo = document.getElementById(contador.dataset.para);
    if (!campo) return;
    var max = contador.dataset.max;
    function actualizar() {
        contador.textContent = campo.value.length + '/' + max;
        contador.classList.toggle('text-danger', campo.value.length > max);
    }
    campo.addEventListener('input', actualizar);
    actualizar();
});

// Color de texto legible (blanco u oscuro) sobre un fondo. Replica
// ColorHex::textoLegible() (contraste WCAG) para las vistas previas.
function textoLegibleSobre(hex) {
    function luminancia(color) {
        return [1, 3, 5].map(function (i) {
            var c = parseInt(color.substr(i, 2), 16) / 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        }).reduce(function (total, c, i) {
            return total + c * [0.2126, 0.7152, 0.0722][i];
        }, 0);
    }
    function contraste(a, b) {
        var la = luminancia(a), lb = luminancia(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }
    return contraste(hex, '#ffffff') >= contraste(hex, '#212529') ? '#ffffff' : '#212529';
}

// Tienda: cards de producto que responden a los switches y al color principal.
(function () {
    var contenedor  = document.getElementById('preview_cards');
    var precios     = document.getElementById('mostrar_precios');
    var sinStock    = document.getElementById('mostrar_productos_sin_stock');
    var proveedor   = document.getElementById('mostrar_proveedor');
    var color       = document.getElementById('color_primario');
    var cardAgotada = document.getElementById('preview_card_sin_stock');
    var cardOculta  = document.getElementById('preview_card_oculta');

    function actualizar() {
        contenedor.style.setProperty('--preview-primario', color.value);
        contenedor.style.setProperty('--preview-primario-texto', textoLegibleSobre(color.value));

        contenedor.querySelectorAll('[data-preview-precio]').forEach(function (precio) {
            precio.classList.toggle('d-none', !precios.checked);
        });

        cardAgotada.classList.toggle('d-none', !sinStock.checked);
        cardOculta.classList.toggle('d-none', sinStock.checked);

        contenedor.querySelectorAll('[data-preview-proveedor]').forEach(function (linea) {
            linea.classList.toggle('d-none', !proveedor.checked);
        });
    }

    precios.addEventListener('change', actualizar);
    sinStock.addEventListener('change', actualizar);
    proveedor.addEventListener('change', actualizar);
    color.addEventListener('input', actualizar);
    actualizar();
})();

// Pedidos: vista previa del mensaje de WhatsApp. La arma el servidor con la plantilla
// y los switches que hay en pantalla; acá sólo se le da el formato de WhatsApp.
(function () {
    var plantilla  = document.getElementById('template_whatsapp');
    var direccion  = document.getElementById('pedir_direccion_envio');
    var precios    = document.getElementById('mostrar_precios');
    var destino    = document.getElementById('whatsapp-preview-texto');
    var burbuja    = destino.parentElement;
    var url        = @json(route('admin.configuraciones.vista-previa-whatsapp'));
    var token      = document.querySelector('meta[name="csrf-token"]').content;
    var espera     = null;
    var pedido     = 0;

    // *negrita*, _cursiva_, ~tachado~ y ```monoespaciado```, como los muestra WhatsApp.
    function formatoWhatsapp(texto) {
        var div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML
            .replace(/```([\s\S]+?)```/g, '<code>$1</code>')
            .replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>')
            .replace(/(^|[\s(])_([^_\n]+)_/g, '$1<em>$2</em>')
            .replace(/~([^~\n]+)~/g, '<del>$1</del>');
    }

    function actualizar() {
        var este = ++pedido;
        burbuja.classList.add('cargando');

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({
                plantilla: plantilla.value,
                pedir_direccion: direccion.checked,
                mostrar_precios: precios.checked
            })
        })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
            .then(function (datos) {
                if (este !== pedido) return;   // llegó tarde: ya hay una más nueva
                destino.innerHTML = formatoWhatsapp(datos.mensaje);
            })
            .catch(function () {
                if (este === pedido) destino.textContent = 'No se pudo generar la vista previa.';
            })
            .finally(function () {
                if (este === pedido) burbuja.classList.remove('cargando');
            });
    }

    function programar() {
        clearTimeout(espera);
        espera = setTimeout(actualizar, 300);
    }

    plantilla.addEventListener('input', programar);
    direccion.addEventListener('change', programar);
    precios.addEventListener('change', programar);
    actualizar();
})();

// WhatsApp: tocar una variable la inserta donde está el cursor.
(function () {
    var mensaje = document.getElementById('template_whatsapp');
    document.querySelectorAll('.variable-whatsapp').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var variable = boton.dataset.variable;
            var inicio = mensaje.selectionStart, fin = mensaje.selectionEnd;
            mensaje.value = mensaje.value.slice(0, inicio) + variable + mensaje.value.slice(fin);
            mensaje.focus();
            mensaje.selectionStart = mensaje.selectionEnd = inicio + variable.length;
            mensaje.dispatchEvent(new Event('input'));   // actualiza la vista previa
        });
    });
})();

// SEO: vista previa del resultado en Google mientras se escribe.
(function () {
    var titulo      = document.getElementById('seo_titulo_default');
    var descripcion = document.getElementById('seo_descripcion_default');
    var vTitulo      = document.getElementById('seo_preview_titulo');
    var vDescripcion = document.getElementById('seo_preview_descripcion');

    function actualizar() {
        vTitulo.textContent      = titulo.value.trim() || vTitulo.dataset.vacio;
        vDescripcion.textContent = descripcion.value.trim() || vDescripcion.dataset.vacio;
    }

    titulo.addEventListener('input', actualizar);
    descripcion.addEventListener('input', actualizar);
    actualizar();
})();

// Tarjetas de logo y favicon: muestran el archivo elegido y el estado de "Quitar".
document.querySelectorAll('input[type=file][data-miniatura]').forEach(function (input) {
    var miniatura = document.getElementById(input.dataset.miniatura);
    var vacia     = document.querySelector('[data-miniatura-vacia="' + input.id + '"]');
    var estado    = document.getElementById(input.dataset.estado);
    var quitar    = document.getElementById(input.dataset.quitar);
    var srcInicial = miniatura.getAttribute('src');

    function refrescar() {
        var nuevo = input.files.length ? input.files[0] : null;
        // Un archivo nuevo gana sobre "Quitar", igual que al guardar.
        var seQuita = !nuevo && quitar && quitar.checked;

        miniatura.parentElement.classList.toggle('se-quita', !!seQuita);
        estado.classList.toggle('text-danger', !!seQuita);
        estado.textContent = nuevo ? nuevo.name
            : (seQuita ? 'Se quita al guardar.' : estado.dataset.textoInicial);

        if (!nuevo) {
            miniatura.setAttribute('src', srcInicial || '');
            miniatura.classList.toggle('d-none', !srcInicial);
            if (vacia) vacia.classList.toggle('d-none', !!srcInicial);
            return;
        }

        var lector = new FileReader();
        lector.onload = function (e) {
            miniatura.setAttribute('src', e.target.result);
            miniatura.classList.remove('d-none');
            if (vacia) vacia.classList.add('d-none');
        };
        lector.readAsDataURL(nuevo);
    }

    input.addEventListener('change', refrescar);
    if (quitar) quitar.addEventListener('change', refrescar);
});

// Vista previa de la cabecera: color, logo, tamaño y nombre en tiempo real.
(function () {
    var preview       = document.getElementById('cabecera_preview');
    var logo          = document.getElementById('cabecera_preview_logo');
    var icono         = document.getElementById('cabecera_preview_icono');
    var nombre        = document.getElementById('cabecera_preview_nombre');
    var color         = document.getElementById('color_primario');
    var colorValor    = document.getElementById('color_primario_valor');
    var alto          = document.getElementById('logo_alto');
    var altoValor     = document.getElementById('logo_alto_valor');
    var archivo       = document.getElementById('logo');
    var eliminar      = document.getElementById('eliminar_logo');
    var campoNombre   = document.getElementById('nombre_tienda');
    var mostrarNombre = document.getElementById('mostrar_nombre_tienda');

    // Un logo nuevo elegido gana sobre "eliminar logo", igual que al guardar.
    function hayLogo() {
        if (archivo.files.length) return true;
        return !!logo.getAttribute('src') && !(eliminar && eliminar.checked);
    }

    function actualizar() {
        var hex = color.value.toLowerCase();
        colorValor.textContent = hex;
        preview.style.backgroundColor = hex;
        preview.style.color = textoLegibleSobre(hex);

        altoValor.textContent = alto.value;
        logo.style.height = alto.value + 'px';

        var conLogo = hayLogo();
        logo.classList.toggle('d-none', !conLogo);
        icono.classList.toggle('d-none', conLogo);

        nombre.textContent = campoNombre.value;
        // Sin logo el nombre se muestra siempre, igual que en la tienda.
        nombre.classList.toggle('d-none', conLogo && !mostrarNombre.checked);
    }

    archivo.addEventListener('change', function () {
        if (!archivo.files.length) {
            actualizar();
            return;
        }
        var lector = new FileReader();
        lector.onload = function (e) {
            logo.setAttribute('src', e.target.result);
            actualizar();
        };
        lector.readAsDataURL(archivo.files[0]);
    });

    [color, alto, campoNombre].forEach(function (el) { el.addEventListener('input', actualizar); });
    [mostrarNombre, eliminar].forEach(function (el) { if (el) el.addEventListener('change', actualizar); });

    actualizar();
})();
</script>
@endpush
@endsection
