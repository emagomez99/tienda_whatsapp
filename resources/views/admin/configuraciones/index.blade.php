@extends('layouts.admin')

@section('title', 'Configuraciones')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-sliders"></i> Configuraciones</h3>
</div>

<form action="{{ route('admin.configuraciones.update') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="row">
        <div class="col-lg-6 mb-4">
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
                        <label class="form-label">Mostrar nombre de la tienda</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_nombre_tienda" id="nombre_si" value="true" {{ old('mostrar_nombre_tienda', App\Models\Configuracion::obtener('mostrar_nombre_tienda', 'true')) === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="nombre_si">
                                    <i class="bi bi-eye"></i> Sí
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_nombre_tienda" id="nombre_no" value="false" {{ old('mostrar_nombre_tienda', App\Models\Configuracion::obtener('mostrar_nombre_tienda', 'true')) === 'false' ? 'checked' : '' }}>
                                <label class="form-check-label" for="nombre_no">
                                    <i class="bi bi-eye-slash"></i> No, solo logo
                                </label>
                            </div>
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

        <div class="col-lg-6 mb-4">
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
                        <label class="form-label">Mostrar Precios</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_precios" id="precios_si" value="true" {{ old('mostrar_precios', App\Models\Configuracion::obtener('mostrar_precios', 'true')) === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="precios_si">
                                    <i class="bi bi-eye"></i> Sí
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_precios" id="precios_no" value="false" {{ old('mostrar_precios', App\Models\Configuracion::obtener('mostrar_precios', 'true')) === 'false' ? 'checked' : '' }}>
                                <label class="form-check-label" for="precios_no">
                                    <i class="bi bi-eye-slash"></i> Ocultar
                                </label>
                            </div>
                        </div>
                        <small class="text-muted">Si se ocultan, los clientes no verán el precio en ningun lugar de la tienda</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Mostrar Proveedor en Productos</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_proveedor" id="proveedor_si" value="true" {{ old('mostrar_proveedor', App\Models\Configuracion::obtener('mostrar_proveedor', 'false')) === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="proveedor_si">
                                    <i class="bi bi-eye"></i> Sí
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_proveedor" id="proveedor_no" value="false" {{ old('mostrar_proveedor', App\Models\Configuracion::obtener('mostrar_proveedor', 'false')) === 'false' ? 'checked' : '' }}>
                                <label class="form-check-label" for="proveedor_no">
                                    <i class="bi bi-eye-slash"></i> Ocultar
                                </label>
                            </div>
                        </div>
                        <small class="text-muted">Si se oculta, los clientes no verán el nombre del proveedor en la ficha del producto</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Solicitar Dirección de Envío</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="pedir_direccion_envio" id="direccion_si" value="true" {{ old('pedir_direccion_envio', App\Models\Configuracion::obtener('pedir_direccion_envio', 'true')) === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="direccion_si">
                                    <i class="bi bi-geo-alt"></i> Sí
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="pedir_direccion_envio" id="direccion_no" value="false" {{ old('pedir_direccion_envio', App\Models\Configuracion::obtener('pedir_direccion_envio', 'true')) === 'false' ? 'checked' : '' }}>
                                <label class="form-check-label" for="direccion_no">
                                    <i class="bi bi-geo-alt-fill"></i> No
                                </label>
                            </div>
                        </div>
                        <small class="text-muted">Si se desactiva, el cliente no deberá ingresar su dirección al hacer el pedido</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Mostrar Productos sin Stock</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_productos_sin_stock" id="sin_stock_si" value="true" {{ old('mostrar_productos_sin_stock', App\Models\Configuracion::obtener('mostrar_productos_sin_stock', 'true')) === 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="sin_stock_si">
                                    <i class="bi bi-eye"></i> Sí
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mostrar_productos_sin_stock" id="sin_stock_no" value="false" {{ old('mostrar_productos_sin_stock', App\Models\Configuracion::obtener('mostrar_productos_sin_stock', 'true')) === 'false' ? 'checked' : '' }}>
                                <label class="form-check-label" for="sin_stock_no">
                                    <i class="bi bi-eye-slash"></i> Ocultar
                                </label>
                            </div>
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

    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-search"></i> SEO</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Valores por defecto que se usan cuando una página, producto o menú no tiene su propio
                        título/descripción SEO cargado.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="seo_titulo_default" class="form-label">Meta title por defecto</label>
                            <input type="text" class="form-control @error('seo_titulo_default') is-invalid @enderror"
                                   id="seo_titulo_default" name="seo_titulo_default" maxlength="60"
                                   value="{{ old('seo_titulo_default', App\Models\Configuracion::seoTituloDefault()) }}">
                            @error('seo_titulo_default')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted contador-caracteres" data-max="60">0/60</small>
                        </div>
                        <div class="col-md-6">
                            <label for="seo_keywords" class="form-label">Palabras clave (separadas por coma)</label>
                            <input type="text" class="form-control @error('seo_keywords') is-invalid @enderror"
                                   id="seo_keywords" name="seo_keywords"
                                   value="{{ old('seo_keywords', App\Models\Configuracion::seoKeywords()) }}">
                            @error('seo_keywords')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label for="seo_descripcion_default" class="form-label">Meta description por defecto</label>
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
                            <label for="google_site_verification" class="form-label">Google Search Console (código de verificación)</label>
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
                                Ayuda a que Google asocie la tienda con la ciudad. Si además cargás la calle,
                                se interpreta como un local visitable por el público; si dejás la calle vacía,
                                solo indica que la tienda está radicada/opera desde esa ciudad.
                            </p>
                        </div>
                        <div class="col-12">
                            <label class="form-label">¿Declarar ubicación de la tienda?</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="ubicacion_activa" id="ubicacion_si" value="true"
                                           {{ old('ubicacion_activa', App\Models\Configuracion::ubicacionActiva() ? 'true' : 'false') === 'true' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="ubicacion_si">
                                        <i class="bi bi-check-circle text-success"></i> Sí
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="ubicacion_activa" id="ubicacion_no" value="false"
                                           {{ old('ubicacion_activa', App\Models\Configuracion::ubicacionActiva() ? 'true' : 'false') === 'false' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="ubicacion_no">
                                        <i class="bi bi-x-circle text-danger"></i> No
                                    </label>
                                </div>
                            </div>
                            <small class="text-muted">Si elegís "No", los campos de abajo se guardan pero no se usan en el SEO.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="ciudad" class="form-label">Ciudad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('ciudad') is-invalid @enderror"
                                   id="ciudad" name="ciudad" placeholder="Bahía Blanca"
                                   value="{{ old('ciudad', App\Models\Configuracion::ciudad()) }}">
                            @error('ciudad')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="provincia" class="form-label">Provincia</label>
                            <input type="text" class="form-control @error('provincia') is-invalid @enderror"
                                   id="provincia" name="provincia" placeholder="Buenos Aires"
                                   value="{{ old('provincia', App\Models\Configuracion::provincia()) }}">
                            @error('provincia')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-8">
                            <label for="direccion" class="form-label">Dirección <span class="text-muted fw-normal">(solo si hay local visitable)</span></label>
                            <input type="text" class="form-control @error('direccion') is-invalid @enderror"
                                   id="direccion" name="direccion" placeholder="Ej: Alsina 250"
                                   value="{{ old('direccion', App\Models\Configuracion::direccion()) }}">
                            @error('direccion')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="codigo_postal" class="form-label">Código postal</label>
                            <input type="text" class="form-control @error('codigo_postal') is-invalid @enderror"
                                   id="codigo_postal" name="codigo_postal"
                                   value="{{ old('codigo_postal', App\Models\Configuracion::codigoPostal()) }}">
                            @error('codigo_postal')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12"><hr></div>
                        <div class="col-12">
                            <label class="form-label">Permitir que los buscadores indexen la tienda</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="robots_index" id="robots_si" value="true" {{ old('robots_index', App\Models\Configuracion::robotsIndex() ? 'true' : 'false') === 'true' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="robots_si">
                                        <i class="bi bi-check-circle text-success"></i> Sí
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="robots_index" id="robots_no" value="false" {{ old('robots_index', App\Models\Configuracion::robotsIndex() ? 'true' : 'false') === 'false' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="robots_no">
                                        <i class="bi bi-x-circle text-danger"></i> No
                                    </label>
                                </div>
                            </div>
                            <small class="text-muted">Desactivalo mientras armás la tienda para evitar que Google la indexe antes de tiempo.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                                    <label class="form-label small mb-1">Habilitar imágenes adicionales</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="imagenes_adicionales_activas"
                                                   id="img_ad_si" value="true"
                                                   {{ old('imagenes_adicionales_activas', $imgAdicionalesActivas ? 'true' : 'false') === 'true' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="img_ad_si">
                                                <i class="bi bi-check-circle text-success"></i> Sí
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="imagenes_adicionales_activas"
                                                   id="img_ad_no" value="false"
                                                   {{ old('imagenes_adicionales_activas', $imgAdicionalesActivas ? 'true' : 'false') === 'false' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="img_ad_no">
                                                <i class="bi bi-x-circle text-danger"></i> No
                                            </label>
                                        </div>
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

    <div class="row">
        <div class="col-12">
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-circle"></i> Guardar Configuraciones
                </button>
            </div>
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
