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
            <i class="bi bi-whatsapp"></i> WhatsApp
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
    <div class="row">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-palette"></i> Apariencia</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label for="logo" class="form-label">Logo de la Tienda</label>
                        @php $logoActual = App\Models\Configuracion::logo(); @endphp
                        @if($logoActual)
                            <div class="mb-2 d-flex align-items-center gap-3">
                                <img src="{{ url('storage/' . $logoActual) }}" alt="Logo actual" style="max-height: 60px;" class="rounded border">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="eliminar_logo" name="eliminar_logo" value="1">
                                    <label class="form-check-label text-danger" for="eliminar_logo">
                                        <i class="bi bi-trash"></i> Eliminar logo
                                    </label>
                                </div>
                            </div>
                        @endif
                        <input type="file" class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" accept="image/*">
                        @error('logo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Formatos: JPG, PNG, GIF. Máx: 2MB</small>
                    </div>

                    <div class="mb-4">
                        <label for="favicon" class="form-label">Favicon</label>
                        @php $faviconActual = App\Models\Configuracion::favicon(); @endphp
                        @if($faviconActual)
                            <div class="mb-2 d-flex align-items-center gap-3">
                                <img src="{{ url('storage/' . $faviconActual) }}" alt="Favicon actual" style="max-height: 32px;" class="rounded border">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="eliminar_favicon" name="eliminar_favicon" value="1">
                                    <label class="form-check-label text-danger" for="eliminar_favicon">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </label>
                                </div>
                            </div>
                        @endif
                        <input type="file" class="form-control @error('favicon') is-invalid @enderror" id="favicon" name="favicon" accept=".ico,.png,.jpg,.jpeg,.svg">
                        @error('favicon')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Formatos: ICO, PNG, JPG, SVG. Máx: 512KB. Tamaño recomendado: 32x32 o 64x64</small>
                    </div>

                    <div class="mb-4">
                        <label for="nombre_tienda" class="form-label">Nombre de la Tienda</label>
                        <input type="text" class="form-control @error('nombre_tienda') is-invalid @enderror" id="nombre_tienda" name="nombre_tienda" value="{{ old('nombre_tienda', App\Models\Configuracion::obtener('nombre_tienda', 'Tienda MC')) }}">
                        @error('nombre_tienda')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input type="hidden" name="mostrar_nombre_tienda" value="false">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="mostrar_nombre_tienda" name="mostrar_nombre_tienda" value="true" {{ old('mostrar_nombre_tienda', App\Models\Configuracion::obtener('mostrar_nombre_tienda', 'true')) === 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="mostrar_nombre_tienda">Mostrar el nombre junto al logo</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Paleta de Colores</label>
                        @php
                            $paletas = App\Models\Configuracion::paletas();
                            $paletaActual = App\Models\Configuracion::paleta();
                        @endphp
                        <div class="row g-2">
                            @foreach($paletas as $clave => $paleta)
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="paleta" id="paleta_{{ $clave }}" value="{{ $clave }}" {{ old('paleta', $paletaActual) === $clave ? 'checked' : '' }}>
                                        <label class="form-check-label d-flex align-items-center gap-2" for="paleta_{{ $clave }}">
                                            <span class="rounded-circle d-inline-block border" style="width: 18px; height: 18px; background-color: {{ $paleta['primary'] }};"></span>
                                            {{ $paleta['nombre'] }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Posici&oacute;n del Men&uacute;</label>
                        @php $posicionMenu = App\Models\Configuracion::posicionMenu(); @endphp
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="posicion_menu" id="menu_superior" value="superior" {{ old('posicion_menu', $posicionMenu) === 'superior' ? 'checked' : '' }}>
                                    <label class="form-check-label d-flex align-items-center gap-2" for="menu_superior">
                                        <i class="bi bi-distribute-horizontal"></i> Barra superior
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="posicion_menu" id="menu_lateral" value="lateral" {{ old('posicion_menu', $posicionMenu) === 'lateral' ? 'checked' : '' }}>
                                    <label class="form-check-label d-flex align-items-center gap-2" for="menu_lateral">
                                        <i class="bi bi-layout-sidebar"></i> Men&uacute; lateral
                                    </label>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted">Define la posición del menú de la tienda</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="tab-pane fade" id="pane-tienda" role="tabpanel">
    <div class="row">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-shop"></i> Tienda</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        @include('partials.intl-tel-input', [
                            'inputId'   => 'whatsapp-input',
                            'fieldName' => 'whatsapp_admin',
                            'value'     => App\Models\Configuracion::obtener('whatsapp_admin', ''),
                            'label'     => 'Número de WhatsApp',
                        ])
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input type="hidden" name="mostrar_precios" value="false">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="mostrar_precios" name="mostrar_precios" value="true" {{ old('mostrar_precios', App\Models\Configuracion::obtener('mostrar_precios', 'true')) === 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="mostrar_precios">Mostrar los precios en la tienda</label>
                        </div>
                        <small class="text-muted">Si se ocultan, los clientes no verán el precio en ningun lugar de la tienda</small>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input type="hidden" name="mostrar_proveedor" value="false">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="mostrar_proveedor" name="mostrar_proveedor" value="true" {{ old('mostrar_proveedor', App\Models\Configuracion::obtener('mostrar_proveedor', 'false')) === 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="mostrar_proveedor">Mostrar el proveedor en la ficha del producto</label>
                        </div>
                        <small class="text-muted">Si se oculta, los clientes no verán el nombre del proveedor en la ficha del producto</small>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input type="hidden" name="pedir_direccion_envio" value="false">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="pedir_direccion_envio" name="pedir_direccion_envio" value="true" {{ old('pedir_direccion_envio', App\Models\Configuracion::obtener('pedir_direccion_envio', 'true')) === 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="pedir_direccion_envio">Pedir dirección de envío al finalizar el pedido</label>
                        </div>
                        <small class="text-muted">Si se desactiva, el cliente no deberá ingresar su dirección al hacer el pedido</small>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input type="hidden" name="mostrar_productos_sin_stock" value="false">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="mostrar_productos_sin_stock" name="mostrar_productos_sin_stock" value="true" {{ old('mostrar_productos_sin_stock', App\Models\Configuracion::obtener('mostrar_productos_sin_stock', 'true')) === 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="mostrar_productos_sin_stock">Mostrar productos sin stock</label>
                        </div>
                        <small class="text-muted">Si se ocultan, solo se verán productos disponibles</small>
                    </div>

                    @if($monedas->isNotEmpty())
                    <div class="mb-3">
                        <label for="moneda_default" class="form-label">Moneda por defecto</label>
                        <select class="form-select @error('moneda_default') is-invalid @enderror"
                                id="moneda_default" name="moneda_default">
                            <option value="">Sin moneda por defecto</option>
                            @foreach($monedas as $moneda)
                                <option value="{{ $moneda->id }}"
                                    {{ old('moneda_default', App\Models\Configuracion::monedaDefaultId()) == $moneda->id ? 'selected' : '' }}>
                                    {{ $moneda->nombre }} ({{ $moneda->simbolo }} {{ $moneda->codigo }})
                                </option>
                            @endforeach
                        </select>
                        @error('moneda_default')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Se preseleccionará al cargar un nuevo producto.</small>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
<div class="tab-pane fade" id="pane-redes" role="tabpanel">
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-share"></i> Redes Sociales</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Los íconos configurados aparecerán en el footer de la tienda. Dejá vacíos los que no uses.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="social_instagram" class="form-label">
                                <i class="bi bi-instagram" style="color:#E1306C;"></i> Instagram
                            </label>
                            <input type="url" class="form-control @error('social_instagram') is-invalid @enderror"
                                   id="social_instagram" name="social_instagram"
                                   placeholder="https://instagram.com/tutienda"
                                   value="{{ old('social_instagram', App\Models\Configuracion::socialInstagram()) }}">
                            @error('social_instagram')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="social_facebook" class="form-label">
                                <i class="bi bi-facebook" style="color:#1877F2;"></i> Facebook
                            </label>
                            <input type="url" class="form-control @error('social_facebook') is-invalid @enderror"
                                   id="social_facebook" name="social_facebook"
                                   placeholder="https://facebook.com/tutienda"
                                   value="{{ old('social_facebook', App\Models\Configuracion::socialFacebook()) }}">
                            @error('social_facebook')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="social_twitter" class="form-label">
                                <i class="bi bi-twitter-x"></i> Twitter / X
                            </label>
                            <input type="url" class="form-control @error('social_twitter') is-invalid @enderror"
                                   id="social_twitter" name="social_twitter"
                                   placeholder="https://x.com/tutienda"
                                   value="{{ old('social_twitter', App\Models\Configuracion::socialTwitter()) }}">
                            @error('social_twitter')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="social_tiktok" class="form-label">
                                <i class="bi bi-tiktok"></i> TikTok
                            </label>
                            <input type="url" class="form-control @error('social_tiktok') is-invalid @enderror"
                                   id="social_tiktok" name="social_tiktok"
                                   placeholder="https://tiktok.com/@tutienda"
                                   value="{{ old('social_tiktok', App\Models\Configuracion::socialTiktok()) }}">
                            @error('social_tiktok')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="social_youtube" class="form-label">
                                <i class="bi bi-youtube" style="color:#FF0000;"></i> YouTube
                            </label>
                            <input type="url" class="form-control @error('social_youtube') is-invalid @enderror"
                                   id="social_youtube" name="social_youtube"
                                   placeholder="https://youtube.com/@tutienda"
                                   value="{{ old('social_youtube', App\Models\Configuracion::socialYoutube()) }}">
                            @error('social_youtube')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="social_whatsapp" class="form-label">
                                <i class="bi bi-whatsapp" style="color:#25D366;"></i> WhatsApp
                            </label>
                            <input type="text" class="form-control @error('social_whatsapp') is-invalid @enderror"
                                   id="social_whatsapp" name="social_whatsapp"
                                   placeholder="+54 9 11 1234 5678"
                                   value="{{ old('social_whatsapp', App\Models\Configuracion::socialWhatsapp()) }}">
                            @error('social_whatsapp')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Solo el número (se genera el link wa.me automáticamente).</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="tab-pane fade" id="pane-seo" role="tabpanel">
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-search"></i> SEO <span class="fw-normal">— Cómo se ve en Google</span></h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border d-flex gap-2 py-2 px-3 mb-4">
                        <i class="bi bi-info-circle text-primary mt-1"></i>
                        <div class="small">
                            <span class="fw-semibold d-block mb-1">Esto es lo que Google muestra de tu tienda.</span>
                            <span class="text-muted">
                                Se usa en las páginas que no tienen su propio título o descripción cargado.
                                Conviene que mencione tus marcas o rubros: es lo que la gente busca.
                            </span>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="seo_titulo_default" class="form-label">Título en Google</label>
                            <input type="text" class="form-control @error('seo_titulo_default') is-invalid @enderror"
                                   id="seo_titulo_default" name="seo_titulo_default" maxlength="60"
                                   value="{{ old('seo_titulo_default', App\Models\Configuracion::seoTituloDefault()) }}">
                            @error('seo_titulo_default')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted contador-caracteres" data-max="60">0/60</small>
                        </div>
                        <div class="col-md-6">
                            <label for="seo_keywords" class="form-label">Palabras clave <span class="text-muted fw-normal">(separadas por coma)</span></label>
                            <input type="text" class="form-control @error('seo_keywords') is-invalid @enderror"
                                   id="seo_keywords" name="seo_keywords"
                                   value="{{ old('seo_keywords', App\Models\Configuracion::seoKeywords()) }}">
                            @error('seo_keywords')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label for="seo_descripcion_default" class="form-label">Descripción en Google</label>
                            <textarea class="form-control @error('seo_descripcion_default') is-invalid @enderror"
                                      id="seo_descripcion_default" name="seo_descripcion_default" rows="2" maxlength="160">{{ old('seo_descripcion_default', App\Models\Configuracion::seoDescripcionDefault()) }}</textarea>
                            @error('seo_descripcion_default')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted contador-caracteres" data-max="160">0/160</small>
                        </div>
                        <div class="col-md-6">
                            <label for="google_analytics_id" class="form-label">Google Analytics (GA4)</label>
                            <input type="text" class="form-control @error('google_analytics_id') is-invalid @enderror"
                                   id="google_analytics_id" name="google_analytics_id" placeholder="G-XXXXXXXXXX"
                                   value="{{ old('google_analytics_id', App\Models\Configuracion::googleAnalyticsId()) }}">
                            @error('google_analytics_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="google_site_verification" class="form-label">Código de verificación de Google Search Console</label>
                            <input type="text" class="form-control @error('google_site_verification') is-invalid @enderror"
                                   id="google_site_verification" name="google_site_verification"
                                   value="{{ old('google_site_verification', App\Models\Configuracion::googleSiteVerification()) }}">
                            @error('google_site_verification')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12"><hr></div>
                        <div class="col-12">
                            <label class="form-label fw-semibold mb-0">Ubicación (SEO local)</label>
                            <p class="text-muted small mb-2">
                                Ayuda a que Google asocie la tienda con su ciudad, la tengas visitable o no.
                            </p>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="hidden" name="ubicacion_activa" value="false">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="ubicacion_activa" name="ubicacion_activa" value="true" {{ old('ubicacion_activa', App\Models\Configuracion::ubicacionActiva() ? 'true' : 'false') === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="ubicacion_activa">Declarar la ubicación de la tienda</label>
                            </div>
                            <small class="text-muted">Si elegís "No", los campos de abajo se guardan pero no se usan en el SEO.</small>
                        </div>

                        {{-- Grupo 1: siempre se pueden completar, tengas o no local físico --}}
                        <div class="col-md-5">
                            <label for="ciudad" class="form-label">Ciudad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('ciudad') is-invalid @enderror"
                                   id="ciudad" name="ciudad" placeholder="Bahía Blanca"
                                   value="{{ old('ciudad', App\Models\Configuracion::ciudad()) }}">
                            @error('ciudad')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="provincia" class="form-label">Provincia</label>
                            <input type="text" class="form-control @error('provincia') is-invalid @enderror"
                                   id="provincia" name="provincia" placeholder="Buenos Aires"
                                   value="{{ old('provincia', App\Models\Configuracion::provincia()) }}">
                            @error('provincia')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label for="codigo_postal" class="form-label">Código postal</label>
                            <input type="text" class="form-control @error('codigo_postal') is-invalid @enderror"
                                   id="codigo_postal" name="codigo_postal" placeholder="B8000"
                                   value="{{ old('codigo_postal', App\Models\Configuracion::codigoPostal()) }}">
                            @error('codigo_postal')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Grupo 2: solo si hay un local donde atender público --}}
                        <div class="col-12 mt-2">
                            <label class="form-label small fw-semibold text-uppercase text-muted mb-1">
                                <i class="bi bi-shop"></i> Local físico <span class="fw-normal text-lowercase">(opcional)</span>
                            </label>
                        </div>
                        <div class="col-12">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input type="text" class="form-control @error('direccion') is-invalid @enderror"
                                   id="direccion" name="direccion" placeholder="Ej: Alsina 250"
                                   value="{{ old('direccion', App\Models\Configuracion::direccion()) }}">
                            @error('direccion')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Completala solo si un cliente puede venir a comprar o retirar acá. Si vendés solo online o despachás desde un depósito, dejala vacía.</small>
                        </div>
                        <div class="col-12"><hr></div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="hidden" name="robots_index" value="false">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="robots_index" name="robots_index" value="true" {{ old('robots_index', App\Models\Configuracion::robotsIndex() ? 'true' : 'false') === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="robots_index">Permitir que Google indexe la tienda</label>
                            </div>
                            <small class="text-muted">Desactivalo mientras armás la tienda para evitar que Google la indexe antes de tiempo.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="tab-pane fade" id="pane-whatsapp" role="tabpanel">
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-whatsapp text-success"></i> Template de Mensaje WhatsApp</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <label for="template_whatsapp" class="form-label fw-semibold">Mensaje</label>
                            <textarea
                                class="form-control font-monospace @error('template_whatsapp') is-invalid @enderror"
                                id="template_whatsapp"
                                name="template_whatsapp"
                                rows="10"
                                placeholder="{{ App\Models\Configuracion::templateWhatsappDefault() }}"
                            >{{ App\Models\Configuracion::templateWhatsapp() }}</textarea>
                            @error('template_whatsapp')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="mt-2 d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reset-template">
                                    <i class="bi bi-arrow-counterclockwise"></i> Restaurar default
                                </button>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <p class="form-label fw-semibold mb-2">Variables disponibles</p>
                            <div class="list-group list-group-flush small">
                                <div class="list-group-item px-0 py-2">
                                    <code class="text-success">{pedido_id}</code>
                                    <span class="text-muted ms-2">Número de pedido</span>
                                </div>
                                <div class="list-group-item px-0 py-2">
                                    <code class="text-success">{nombre}</code>
                                    <span class="text-muted ms-2">Nombre del cliente</span>
                                </div>
                                <div class="list-group-item px-0 py-2">
                                    <code class="text-success">{apellido}</code>
                                    <span class="text-muted ms-2">Apellido del cliente</span>
                                </div>
                                <div class="list-group-item px-0 py-2">
                                    <code class="text-success">{email}</code>
                                    <span class="text-muted ms-2">Email del cliente</span>
                                </div>
                                <div class="list-group-item px-0 py-2">
                                    <code class="text-success">{celular}</code>
                                    <span class="text-muted ms-2">Celular del cliente</span>
                                </div>
                                <div class="list-group-item px-0 py-2">
                                    <code class="text-success">{direccion}</code>
                                    <span class="text-muted ms-2">Dirección</span>
                                </div>
                                <div class="list-group-item px-0 py-2">
                                    <code class="text-success">{localidad}</code>
                                    <span class="text-muted ms-2">Localidad</span>
                                </div>
                                <div class="list-group-item px-0 py-2">
                                    <code class="text-success">{provincia}</code>
                                    <span class="text-muted ms-2">Provincia</span>
                                </div>
                                <div class="list-group-item px-0 py-2">
                                    <code class="text-success">{cp}</code>
                                    <span class="text-muted ms-2">Código postal</span>
                                </div>
                                <div class="list-group-item px-0 py-2">
                                    <code class="text-success">{productos}</code>
                                    <span class="text-muted ms-2">Lista de productos (solo nombre, código y cantidad)</span>
                                </div>
                                <div class="list-group-item px-0 py-2">
                                    <code class="text-success">{productos+detalles}</code>
                                    <span class="text-muted ms-2">Lista de productos con etiquetas e información técnica</span>
                                </div>
                                <div class="list-group-item px-0 py-2">
                                    <code class="text-success">{total}</code>
                                    <span class="text-muted ms-2">Total del pedido (si precios visibles)</span>
                                </div>
                            </div>
                            <p class="text-muted small mt-3 mb-0">
                                <i class="bi bi-info-circle"></i>
                                Usá <code>*texto*</code> para <strong>negrita</strong> en WhatsApp.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="tab-pane fade" id="pane-avanzada" role="tabpanel">
    @if(auth()->user()->esSuperAdmin())
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-warning">
                <div class="card-header bg-warning bg-opacity-10 border-warning d-flex align-items-center gap-2">
                    <i class="bi bi-star-fill text-warning"></i>
                    <h5 class="mb-0">Configuración Avanzada <span class="badge bg-warning text-dark ms-1">Superadmin</span></h5>
                </div>
                <div class="card-body">
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Modo de imagen de productos</label>
                        <small class="text-muted d-block mb-3">
                            Controla qué opciones de imagen tienen disponibles los usuarios al crear o editar un producto.
                            <strong>Solo URL</strong> no consume espacio en el VPS.
                        </small>
                        @php $modoActual = App\Models\Configuracion::modoImagenProducto(); @endphp
                        @php
                            $imgAdicionalesActivas = App\Models\Configuracion::imagenesAdicionalesActivas();
                            $maxImgAdicionales     = App\Models\Configuracion::maxImagenesAdicionales();
                        @endphp

                        {{-- Imágenes adicionales --}}
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Imágenes adicionales por producto</label>
                            <small class="text-muted d-block mb-3">
                                Permite agregar imágenes extra a cada producto (además de la portada). Se muestran en un carrusel en la vista de detalle.
                            </small>
                            <div class="row g-3 align-items-start">
                                <div class="col-md-5">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="imagenes_adicionales_activas" value="false">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="imagenes_adicionales_activas" name="imagenes_adicionales_activas" value="true"
                                               {{ old('imagenes_adicionales_activas', $imgAdicionalesActivas ? 'true' : 'false') === 'true' ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="imagenes_adicionales_activas">Habilitar imágenes adicionales</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1" for="max_imagenes_adicionales">
                                        Máximo de imágenes adicionales
                                    </label>
                                    <div class="input-group" style="max-width:140px;">
                                        <input type="number" class="form-control @error('max_imagenes_adicionales') is-invalid @enderror"
                                               id="max_imagenes_adicionales" name="max_imagenes_adicionales"
                                               value="{{ old('max_imagenes_adicionales', $maxImgAdicionales) }}"
                                               min="1" max="20">
                                        <span class="input-group-text text-muted small">fotos</span>
                                    </div>
                                    @error('max_imagenes_adicionales')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">No incluye la portada. Entre 1 y 20. Por defecto: 3.</small>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        {{-- Modo imagen de producto --}}
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="form-check card p-3 h-100 {{ $modoActual === 'ambos' ? 'border-primary bg-primary bg-opacity-10' : '' }}">
                                    <input class="form-check-input" type="radio" name="modo_imagen_producto"
                                           id="modo_ambos" value="ambos"
                                           {{ old('modo_imagen_producto', $modoActual) === 'ambos' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="modo_ambos">
                                        <strong><i class="bi bi-images"></i> Ambos</strong>
                                        <small class="text-muted d-block mt-1">El usuario puede subir archivo o ingresar una URL externa.</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check card p-3 h-100 {{ $modoActual === 'solo_url' ? 'border-success bg-success bg-opacity-10' : '' }}">
                                    <input class="form-check-input" type="radio" name="modo_imagen_producto"
                                           id="modo_solo_url" value="solo_url"
                                           {{ old('modo_imagen_producto', $modoActual) === 'solo_url' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="modo_solo_url">
                                        <strong><i class="bi bi-link-45deg text-success"></i> Solo URL</strong>
                                        <small class="text-muted d-block mt-1">
                                            Solo se puede ingresar una URL externa. <strong class="text-success">No usa espacio en el VPS.</strong>
                                        </small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check card p-3 h-100 {{ $modoActual === 'solo_archivo' ? 'border-danger bg-danger bg-opacity-10' : '' }}">
                                    <input class="form-check-input" type="radio" name="modo_imagen_producto"
                                           id="modo_solo_archivo" value="solo_archivo"
                                           {{ old('modo_imagen_producto', $modoActual) === 'solo_archivo' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="modo_solo_archivo">
                                        <strong><i class="bi bi-upload text-danger"></i> Solo subir archivo</strong>
                                        <small class="text-muted d-block mt-1">
                                            Solo se puede subir una imagen al servidor. <strong class="text-danger">Consume espacio en el VPS.</strong>
                                        </small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
</div>

    <div class="position-sticky bottom-0 bg-white border-top py-3 mt-3" style="z-index:5;">
        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle"></i> Guardar cambios
            </button>
        </div>
    </div>
</form>

@push('scripts')
<script>
document.getElementById('btn-reset-template').addEventListener('click', function () {
    document.getElementById('template_whatsapp').value = @json(App\Models\Configuracion::templateWhatsappDefault());
});

// Contador de caracteres para los campos de SEO
document.querySelectorAll('.contador-caracteres').forEach(function (contador) {
    var campo = contador.parentElement.querySelector('input, textarea');
    if (!campo) return;
    var max = contador.dataset.max;
    function actualizar() {
        contador.textContent = campo.value.length + '/' + max;
        contador.classList.toggle('text-danger', campo.value.length > max);
    }
    campo.addEventListener('input', actualizar);
    actualizar();
});
</script>
@endpush
@endsection
