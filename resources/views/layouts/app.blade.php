<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $seo = App\Services\SeoService::metaTags([
            'title'       => trim($__env->yieldContent('title')),
            'description' => trim($__env->yieldContent('meta_description')),
            'image'       => trim($__env->yieldContent('meta_image')),
            'type'        => trim($__env->yieldContent('meta_type')),
            'canonical'   => trim($__env->yieldContent('meta_canonical')),
        ]);
    @endphp
    <title>{{ $seo->title }}</title>
    <meta name="description" content="{{ $seo->description }}">
    @if($seo->tieneKeywords())
        <meta name="keywords" content="{{ $seo->keywords }}">
    @endif
    <meta name="robots" content="{{ $seo->robotsContent() }}">
    <link rel="canonical" href="{{ $seo->url }}">

    <meta property="og:type" content="{{ $seo->type }}">
    <meta property="og:url" content="{{ $seo->url }}">
    <meta property="og:title" content="{{ $seo->title }}">
    <meta property="og:description" content="{{ $seo->description }}">
    <meta property="og:image" content="{{ $seo->image }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo->title }}">
    <meta name="twitter:description" content="{{ $seo->description }}">
    <meta name="twitter:image" content="{{ $seo->image }}">

    @php $googleSiteVerification = App\Models\Configuracion::googleSiteVerification(); @endphp
    @if($googleSiteVerification)
        <meta name="google-site-verification" content="{{ $googleSiteVerification }}">
    @endif

    <script type="application/ld+json">{!! App\Services\SeoService::jsonLd(App\Services\SeoService::organizationSchema()) !!}</script>
    @stack('schema')

    @stack('meta')

    @php $googleAnalyticsId = App\Models\Configuracion::googleAnalyticsId(); @endphp
    @if($googleAnalyticsId)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleAnalyticsId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', {!! json_encode($googleAnalyticsId) !!});
        </script>
    @endif

    @php $favicon = App\Models\Configuracion::favicon(); @endphp
    @if($favicon)
        <link rel="icon" href="{{ url('storage/' . $favicon) }}" type="image/x-icon">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/tienda.css') }}?v={{ filemtime(public_path('css/tienda.css')) }}" rel="stylesheet">
    @php
        $paleta = App\Models\Configuracion::getPaletaActual();
        $menuEnSidebar = App\Models\Configuracion::menuEnSidebar();
    @endphp
    {{-- Color de marca del tenant: tienda.css deriva todos los acentos de esta variable. --}}
    <style>:root { --color-primary: {{ $paleta['primary'] }}; }</style>
    <noscript><style>.img-fade { opacity: 1; }</style></noscript>
    @stack('styles')
</head>
<body class="{{ $menuEnSidebar ? 'tienda-con-sidebar' : '' }}">
    @php
        $cantidadCarrito = array_sum(session()->get('carrito', []));
        $logoTienda = App\Models\Configuracion::logo();
        $mostrarNombre = App\Models\Configuracion::mostrarNombreTienda();
        $nombreTienda = App\Models\Configuracion::nombreTienda();
        $enCarrito = request()->routeIs('carrito.*');
    @endphp
    <header class="site-header">
        <nav class="navbar navbar-expand-lg" aria-label="Principal">
            <div class="container">

                {{-- === BARRA MOBILE: menú, marca centrada y carrito (oculta en desktop) === --}}
                <div class="d-flex d-lg-none align-items-center w-100 gap-2">
                    <div class="header-side">
                        <button class="header-icon-btn" type="button"
                                data-bs-toggle="offcanvas" data-bs-target="#menuDrawer"
                                aria-controls="menuDrawer" aria-label="Abrir menú">
                            <i class="bi bi-list"></i>
                        </button>
                    </div>

                    <a class="site-brand" href="{{ route('tienda.index') }}">
                        @include('partials.marca-tienda')
                    </a>

                    <div class="header-side justify-content-end">
                        <a href="{{ route('carrito.index') }}"
                           class="header-icon-btn{{ $enCarrito ? ' active' : '' }}" aria-label="Carrito">
                            <i class="bi bi-cart3"></i>
                            <span class="cart-count cart-badge-mobile{{ $cantidadCarrito > 0 ? '' : ' d-none' }}">
                                {{ $cantidadCarrito ?: '' }}
                            </span>
                        </a>
                    </div>
                </div>

                {{-- === MARCA DESKTOP === --}}
                <a class="site-brand d-none d-lg-inline-flex me-4" href="{{ route('tienda.index') }}">
                    @include('partials.marca-tienda')
                </a>

                {{-- === MENÚ Y ACCIONES DESKTOP (en mobile van dentro del drawer) === --}}
                <div class="collapse navbar-collapse" id="navbarNav">
                    @if(!$menuEnSidebar)
                        @include('components.menu-tienda')
                    @endif
                    <div class="header-actions ms-auto">
                        @auth
                            @if(auth()->user()->isAdmin())
                                <a class="header-link" href="{{ route('admin.dashboard') }}">
                                    <i class="bi bi-gear"></i> Admin
                                </a>
                            @endif
                            <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="header-link">
                                    <i class="bi bi-box-arrow-right"></i> Salir
                                </button>
                            </form>
                        @else
                            <a class="header-link" href="{{ route('login') }}">
                                <i class="bi bi-person"></i> Acceder
                            </a>
                        @endauth
                        <span class="header-divider" aria-hidden="true"></span>
                        <a class="header-icon-btn{{ $enCarrito ? ' active' : '' }}" href="{{ route('carrito.index') }}" aria-label="Carrito">
                            <i class="bi bi-cart3"></i>
                            <span class="cart-count cart-badge-desktop{{ $cantidadCarrito > 0 ? '' : ' d-none' }}">
                                {{ $cantidadCarrito ?: '' }}
                            </span>
                        </a>
                    </div>
                </div>

            </div>
        </nav>
    </header>

    {{-- Drawer mobile --}}
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="menuDrawer" aria-labelledby="menuDrawerLabel">
        <div class="drawer-head">
            <a class="site-brand" href="{{ route('tienda.index') }}" id="menuDrawerLabel">
                @include('partials.marca-tienda')
            </a>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <nav class="drawer-nav flex-grow-1 overflow-auto" aria-label="Categorías">
                @include('components.menu-drawer')
            </nav>
            <div class="drawer-foot">
                <a href="{{ route('carrito.index') }}" class="drawer-foot-link">
                    <i class="bi bi-cart3"></i> Mi carrito
                </a>
                @auth
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="drawer-foot-link">
                            <i class="bi bi-gear"></i> Admin
                        </a>
                    @endif
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="drawer-foot-link">
                            <i class="bi bi-box-arrow-right"></i> Salir
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="drawer-foot-link">
                        <i class="bi bi-person"></i> Acceder
                    </a>
                @endauth
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        @if(session('success'))
            <div class="toast align-items-center text-bg-success border-0" role="alert" data-bs-autohide="true" data-bs-delay="4000">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-check-circle me-1"></i> {{ session('success') }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        @endif
        @if(session('warning'))
            <div class="toast align-items-center text-bg-warning border-0" role="alert" data-bs-autohide="true" data-bs-delay="5000">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-exclamation-triangle me-1"></i> {{ session('warning') }}</div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="toast align-items-center text-bg-danger border-0" role="alert" data-bs-autohide="true" data-bs-delay="4000">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-x-circle me-1"></i> {{ session('error') }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        @endif
    </div>

    <main>
        @if($menuEnSidebar)
            <div class="container py-4">
                <div class="row g-4">
                    <div class="col-lg-3 col-md-4">
                        <div class="sidebar-menu">
                            @include('components.menu-sidebar')
                        </div>
                    </div>
                    <div class="col-lg-9 col-md-8">
                        @yield('content-inner')
                        @yield('content')
                    </div>
                </div>
            </div>
        @else
            @yield('content')
        @endif
    </main>

    @php
        $footerSocial = [];
        $inst = App\Models\Configuracion::socialInstagram();
        $face = App\Models\Configuracion::socialFacebook();
        $twit = App\Models\Configuracion::socialTwitter();
        $tikt = App\Models\Configuracion::socialTiktok();
        $yout = App\Models\Configuracion::socialYoutube();
        $wapp = App\Models\Configuracion::socialWhatsapp();
        if ($inst) $footerSocial[] = ['url' => $inst, 'icon' => 'bi-instagram',  'color' => '#E1306C', 'label' => 'Instagram'];
        if ($face) $footerSocial[] = ['url' => $face, 'icon' => 'bi-facebook',   'color' => '#1877F2', 'label' => 'Facebook'];
        if ($twit) $footerSocial[] = ['url' => $twit, 'icon' => 'bi-twitter-x',  'color' => '#000',    'label' => 'Twitter/X'];
        if ($tikt) $footerSocial[] = ['url' => $tikt, 'icon' => 'bi-tiktok',     'color' => '#010101', 'label' => 'TikTok'];
        if ($yout) $footerSocial[] = ['url' => $yout, 'icon' => 'bi-youtube',    'color' => '#FF0000', 'label' => 'YouTube'];
        if ($wapp) $footerSocial[] = ['url' => 'https://wa.me/' . preg_replace('/\D/', '', $wapp), 'icon' => 'bi-whatsapp', 'color' => '#25D366', 'label' => 'WhatsApp'];
    @endphp
    <footer class="site-footer">
        <div class="container">
            <div class="site-footer-top">
                <a class="site-brand" href="{{ route('tienda.index') }}">
                    @include('partials.marca-tienda')
                </a>
                @if(count($footerSocial) > 0)
                    <div class="social-list">
                        @foreach($footerSocial as $red)
                            <a href="{{ $red['url'] }}" target="_blank" rel="noopener noreferrer"
                               aria-label="{{ $red['label'] }}"
                               class="social-btn"
                               style="--social-color: {{ $red['color'] }};">
                                <i class="bi {{ $red['icon'] }}"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="site-footer-bottom">
                <span>&copy; {{ date('Y') }} {{ $nombreTienda }}. Todos los derechos reservados.</span>
                <a href="https://tredevs.com.ar/" target="_blank" rel="noopener" class="credito">
                    Creado por <img src="/img/tredevs.ico" alt="Tredevs">
                </a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Inicializar toasts de flash al cargar
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('#toast-container .toast').forEach(function (el) {
                new bootstrap.Toast(el).show();
            });
        });

        // Función global para mostrar toasts desde JS
        function showToast(message, type) {
            type = type || 'success';
            var icons = { success: 'bi-check-circle', danger: 'bi-x-circle', warning: 'bi-exclamation-triangle', info: 'bi-info-circle' };
            var icon = icons[type] || 'bi-info-circle';
            var id = 'toast-' + Date.now();
            var el = document.createElement('div');
            el.id = id;
            el.className = 'toast align-items-center text-bg-' + type + ' border-0';
            el.setAttribute('role', 'alert');
            el.setAttribute('data-bs-autohide', 'true');
            el.setAttribute('data-bs-delay', '4000');
            el.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="bi ' + icon + ' me-1"></i> ' + message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
            document.getElementById('toast-container').appendChild(el);
            var toast = new bootstrap.Toast(el);
            toast.show();
            el.addEventListener('hidden.bs.toast', function () { el.remove(); });
        }

        // Actualizar badge del carrito (mobile y desktop)
        function updateCartBadge(cantidad) {
            document.querySelectorAll('.cart-badge-mobile, .cart-badge-desktop').forEach(function(badge) {
                if (cantidad > 0) {
                    badge.textContent = cantidad;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
            });
        }

        // Estados de #productos-container mientras se busca o filtra por AJAX. Los
        // comparten la búsqueda (tienda/index) y los filtros en cascada para que la
        // carga, los avisos y los errores se vean igual. Los textos que reciben son
        // siempre propios, nunca input del usuario: se insertan como HTML.
        var EstadoProductos = {
            cargando: function () {
                var tarjeta =
                    '<div class="skeleton-card">' +
                        '<div class="skeleton-img skeleton-shimmer"></div>' +
                        '<span class="skeleton-line skeleton-shimmer" style="width:85%"></span>' +
                        '<span class="skeleton-line skeleton-shimmer" style="width:55%"></span>' +
                        '<span class="skeleton-line skeleton-shimmer is-precio" style="width:40%"></span>' +
                    '</div>';
                return '<div role="status">' +
                    '<span class="visually-hidden">Cargando productos…</span>' +
                    '<div class="productos-grid" aria-hidden="true">' + tarjeta.repeat(8) + '</div>' +
                '</div>';
            },
            aviso: function (icono, titulo, detalle, variante) {
                return '<div class="tienda-aviso' + (variante ? ' is-' + variante : '') + '">' +
                    '<div class="tienda-aviso-icon"><i class="bi ' + icono + '"></i></div>' +
                    '<div class="tienda-aviso-title">' + titulo + '</div>' +
                    (detalle ? '<p class="mb-0">' + detalle + '</p>' : '') +
                '</div>';
            },
            filtrosPendientes: function () {
                return this.aviso('bi-funnel', 'Seleccioná los filtros', 'Completalos arriba para ver los productos disponibles.');
            },
            error: function (detalle) {
                return this.aviso('bi-exclamation-triangle', 'No pudimos cargar los productos', detalle, 'error');
            }
        };
    </script>
    <script>
        // Manejo de submenús anidados
        document.addEventListener('DOMContentLoaded', function() {
            // Para dispositivos táctiles y clics
            document.querySelectorAll('.dropdown-menu .dropend > .dropdown-toggle').forEach(function(element) {
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Cerrar otros submenús del mismo nivel
                    var parent = this.closest('.dropdown-menu');
                    parent.querySelectorAll('.dropend > .dropdown-menu.show').forEach(function(openMenu) {
                        if (openMenu !== this.nextElementSibling) {
                            openMenu.classList.remove('show');
                        }
                    }.bind(this));

                    // Toggle del submenú actual
                    var submenu = this.nextElementSibling;
                    if (submenu) {
                        submenu.classList.toggle('show');
                    }
                });
            });

            // Cerrar submenús cuando se cierra el menú padre
            document.querySelectorAll('.nav-item.dropdown').forEach(function(dropdown) {
                dropdown.addEventListener('hidden.bs.dropdown', function() {
                    this.querySelectorAll('.dropdown-menu.show').forEach(function(submenu) {
                        submenu.classList.remove('show');
                    });
                });
            });
        });
    </script>
    @include('partials.modal-confirmar')
    @stack('scripts')
</body>
</html>
