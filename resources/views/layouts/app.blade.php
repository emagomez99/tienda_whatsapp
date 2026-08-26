<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $seo = App\Services\SeoService::metaTags([
            'title'       => trim($__env->yieldContent('title')),
            'description' => trim($__env->yieldContent('meta_description')),
            'image'       => trim($__env->yieldContent('meta_image')),
            'type'        => trim($__env->yieldContent('meta_type')),
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
            gtag('config', '{{ $googleAnalyticsId }}');
        </script>
    @endif

    @php $favicon = App\Models\Configuracion::favicon(); @endphp
    @if($favicon)
        <link rel="icon" href="{{ url('storage/' . $favicon) }}" type="image/x-icon">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    @php
        $paleta = App\Models\Configuracion::getPaletaActual();
        $menuEnSidebar = App\Models\Configuracion::menuEnSidebar();
    @endphp
    <style>
        :root {
            --color-primary: {{ $paleta['primary'] }};
        }
        .navbar-brand { font-weight: bold; }
        /* Product cards */
        .producto-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 1px 6px rgba(0,0,0,.07);
            transition: box-shadow .25s ease, transform .25s ease;
            overflow: hidden;
        }
        .producto-card:hover {
            box-shadow: 0 10px 32px rgba(0,0,0,.13);
            transform: translateY(-4px);
        }
        .producto-img-wrap { display: block; position: relative; overflow: hidden; flex-shrink: 0; }
        .producto-sin-stock-overlay {
            position: absolute; inset: 0;
            background: rgba(255,255,255,.6);
            display: flex; align-items: center; justify-content: center;
        }
        .producto-sin-stock-overlay span {
            background: rgba(0,0,0,.48); color: #fff;
            padding: .25rem .9rem; border-radius: 4px;
            font-size: .8rem; font-weight: 500;
        }
        .producto-card:hover .card-img-top { transform: scale(1.05); }
        .producto-nombre {
            font-size: .88rem;
            font-weight: 600;
            line-height: 1.45;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .producto-sin-stock-overlay {
            position: absolute; inset: 0;
            background: rgba(255,255,255,.6);
            display: flex; align-items: center; justify-content: center;
        }
        .producto-sin-stock-overlay span {
            background: rgba(0,0,0,.48); color: #fff;
            padding: .25rem .9rem; border-radius: 4px;
            font-size: .8rem; font-weight: 500; letter-spacing: .03em;
        }
        /* Search bar */
        .search-bar .form-control {
            border-right: 0;
            border-radius: 10px 0 0 10px;
            padding-left: 1.1rem;
            background: #fff;
        }
        .search-bar .form-control:focus { box-shadow: none; }
        .search-bar #btn-buscar { border-radius: 0 10px 10px 0; }
        .search-bar #btn-limpiar-busqueda { border-radius: 10px; margin-left: 6px; }
        .badge-etiqueta { font-size: 0.75rem; }
        .metadata-list { font-size: 0.85rem; }
        footer { margin-top: auto; }
        body { min-height: 100vh; display: flex; flex-direction: column; }
        .bg-purple { background-color: #6f42c1 !important; }
        .btn-primary, .bg-custom-primary {
            background-color: var(--color-primary) !important;
            border-color: var(--color-primary) !important;
        }
        .btn-primary:hover {
            background-color: color-mix(in srgb, var(--color-primary) 85%, black) !important;
            border-color: color-mix(in srgb, var(--color-primary) 85%, black) !important;
        }
        .text-primary { color: var(--color-primary) !important; }
        .navbar-custom { background-color: var(--color-primary) !important; }

        /* Navbar mobile */
        @media (max-width: 991.98px) {
            .navbar { padding-top: .75rem; padding-bottom: .75rem; }
        }

        /* Drawer offcanvas */
        #menuDrawer { background-color: var(--color-primary); width: 280px; }
        .drawer-item {
            display: block;
            width: 100%;
            color: rgba(255,255,255,.92);
            text-decoration: none;
            padding: .9rem 1.5rem;
            font-size: 1rem;
            border: none;
            background: none;
            border-bottom: 1px solid rgba(255,255,255,.1);
            text-align: left;
            cursor: pointer;
        }
        .drawer-item:hover, .drawer-item:focus {
            background: rgba(255,255,255,.1);
            color: #fff;
            outline: none;
        }
        .drawer-chevron { transition: transform .2s ease; font-size: .85rem; opacity: .7; }
        .drawer-item[aria-expanded="true"] .drawer-chevron { transform: rotate(180deg); }
        .drawer-subitem {
            display: block;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            padding: .7rem 1.5rem .7rem 2.25rem;
            font-size: .95rem;
            border-bottom: 1px solid rgba(255,255,255,.07);
            background: rgba(0,0,0,.18);
        }
        .drawer-subitem:hover { background: rgba(0,0,0,.3); color: #fff; }
        .drawer-footer-link {
            display: block;
            color: rgba(255,255,255,.8);
            text-decoration: none;
            padding: .5rem 0;
            font-size: .95rem;
        }
        .drawer-footer-link:hover { color: #fff; }

        /* Estilos para submenús anidados */
        .dropdown-menu .dropend .dropdown-menu {
            top: 0;
            left: 100%;
            margin-left: 0;
        }
        .dropdown-menu .dropend .dropdown-toggle::after {
            vertical-align: middle;
            border-left: .3em solid;
            border-top: .3em solid transparent;
            border-bottom: .3em solid transparent;
            border-right: 0;
            margin-left: auto;
        }
        .dropdown-menu .dropend .dropdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dropdown-menu .dropend:hover > .dropdown-menu {
            display: block;
        }
        .dropdown-menu .dropend > .dropdown-menu {
            position: absolute;
        }

        /* Estilos para menú sidebar */
        .sidebar-menu {
            position: sticky;
            top: 1rem;
        }
        .sidebar-menu .list-group-item {
            border-left: 0;
            border-right: 0;
        }
        .sidebar-menu .list-group-item:first-child {
            border-top: 0;
        }
        .sidebar-menu .list-group-item a:hover {
            color: var(--color-primary) !important;
        }
        .sidebar-menu .card-header {
            background-color: var(--color-primary) !important;
        }
    </style>
    @stack('styles')
</head>
<body>
    @php
        $cantidadCarrito = array_sum(session()->get('carrito', []));
        $logoTienda = App\Models\Configuracion::logo();
        $mostrarNombre = App\Models\Configuracion::mostrarNombreTienda();
        $nombreTienda = App\Models\Configuracion::nombreTienda();
    @endphp
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
        <div class="container">

            {{-- === BARRA MOBILE: 3 columnas flex, oculta en desktop === --}}
            <div class="d-flex d-lg-none align-items-center w-100">

                {{-- Izquierda: abre drawer --}}
                <div style="flex:1;">
                    <button class="navbar-toggler border-0 p-1" type="button"
                            data-bs-toggle="offcanvas" data-bs-target="#menuDrawer" aria-label="Abrir menú">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                </div>

                {{-- Centro: logo --}}
                <a class="navbar-brand mb-0 d-flex align-items-center" href="{{ route('tienda.index') }}">
                    @if($logoTienda)
                        <img src="{{ url('storage/' . $logoTienda) }}" alt="{{ $nombreTienda }}"
                             style="max-height: 40px;" class="{{ $mostrarNombre ? 'me-2' : '' }}">
                    @else
                        <i class="bi bi-shop me-2"></i>
                    @endif
                    @if($mostrarNombre || !$logoTienda)
                        {{ $nombreTienda }}
                    @endif
                </a>

                {{-- Derecha: carrito --}}
                <div style="flex:1; text-align:right;">
                    <a href="{{ route('carrito.index') }}"
                       class="text-white text-decoration-none position-relative p-1 {{ request()->routeIs('carrito.*') ? 'opacity-75' : '' }}"
                       style="font-size: 1.4rem; line-height: 1;">
                        <i class="bi bi-cart3"></i>
                        <span class="cart-badge-mobile badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle{{ $cantidadCarrito > 0 ? '' : ' d-none' }}"
                              style="font-size: .6rem; min-width: 1.1rem; padding: .2em .4em;">
                            {{ $cantidadCarrito ?: '' }}
                        </span>
                    </a>
                </div>
            </div>

            {{-- === LOGO DESKTOP (oculto en mobile) === --}}
            <a class="navbar-brand d-none d-lg-flex align-items-center" href="{{ route('tienda.index') }}">
                @if($logoTienda)
                    <img src="{{ url('storage/' . $logoTienda) }}" alt="{{ $nombreTienda }}"
                         style="max-height: 40px;" class="{{ $mostrarNombre ? 'me-2' : '' }}">
                @else
                    <i class="bi bi-shop me-2"></i>
                @endif
                @if($mostrarNombre || !$logoTienda)
                    {{ $nombreTienda }}
                @endif
            </a>

            {{-- === MENÚ COLAPSABLE === --}}
            <div class="collapse navbar-collapse" id="navbarNav">
                @if(!$menuEnSidebar)
                    @include('components.menu-tienda')
                @endif
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item d-none d-lg-block">
                        <a class="nav-link {{ request()->routeIs('carrito.*') ? 'active' : '' }}" href="{{ route('carrito.index') }}">
                            <i class="bi bi-cart3" style="font-size:1.4rem;"></i>
                            <span class="cart-badge-desktop badge bg-danger{{ $cantidadCarrito > 0 ? '' : ' d-none' }}">
                                {{ $cantidadCarrito ?: '' }}
                            </span>
                        </a>
                    </li>
                    @auth
                        @if(auth()->user()->isAdmin())
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.dashboard') }}">
                                    <i class="bi bi-gear" style="font-size:1.4rem;"></i> Admin
                                </a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="nav-link btn btn-link">
                                    <i class="bi bi-box-arrow-right" style="font-size:1.4rem;"></i> Salir
                                </button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">
                                <i class="bi bi-person" style="font-size:1.4rem;"></i> Acceder
                            </a>
                        </li>
                    @endauth
                </ul>
            </div>

        </div>
    </nav>

    {{-- Drawer mobile --}}
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="menuDrawer" aria-labelledby="menuDrawerLabel">
        <div class="offcanvas-header" style="border-bottom: 1px solid rgba(255,255,255,.15);">
            <a class="text-white text-decoration-none fw-bold fs-5" href="{{ route('tienda.index') }}" id="menuDrawerLabel">
                @if($logoTienda)
                    <img src="{{ url('storage/' . $logoTienda) }}" alt="{{ $nombreTienda }}" style="max-height:32px;">
                    @if($mostrarNombre) <span class="ms-2">{{ $nombreTienda }}</span> @endif
                @else
                    <i class="bi bi-shop me-2"></i>{{ $nombreTienda }}
                @endif
            </a>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <nav class="flex-grow-1 overflow-auto">
                @include('components.menu-drawer')
            </nav>
            <div class="p-3" style="border-top: 1px solid rgba(255,255,255,.15);">
                @auth
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="drawer-footer-link">
                            <i class="bi bi-gear me-2"></i> Admin
                        </a>
                    @endif
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="drawer-footer-link btn btn-link p-0 w-100 text-start">
                            <i class="bi bi-box-arrow-right me-2"></i> Salir
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="drawer-footer-link">
                        <i class="bi bi-person me-2"></i> Acceder
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

    <main class="flex-grow-1">

        @if($menuEnSidebar)
            <div class="container py-4">
                <div class="row">
                    <div class="col-lg-3 col-md-4 mb-4 mb-md-0">
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

    <footer class="bg-light py-3 mt-4 border-top">
        <div class="container text-center text-muted small">
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
            @if(count($footerSocial) > 0)
                <div class="d-flex justify-content-center gap-3 mb-2">
                    @foreach($footerSocial as $red)
                        <a href="{{ $red['url'] }}" target="_blank" rel="noopener noreferrer"
                           aria-label="{{ $red['label'] }}"
                           class="footer-social-icon text-muted fs-5"
                           data-color="{{ $red['color'] }}">
                            <i class="bi {{ $red['icon'] }}"></i>
                        </a>
                    @endforeach
                </div>
            @endif
            <div>&copy; {{ date('Y') }} {{ App\Models\Configuracion::obtener('nombre_tienda', 'Tienda MC') }}. Todos los derechos reservados.</div>
            <div class="mt-2">
                <a href="https://tredevs.com.ar/" target="_blank" rel="noopener" class="d-inline-flex align-items-center gap-1 text-muted text-decoration-none" style="opacity:.4;transition:opacity .2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.4">
                    Creado por <img src="/img/tredevs.ico" alt="Tredevs" style="height:16px;width:16px;object-fit:contain;">
                </a>
            </div>
        </div>
    </footer>
    <script>
        document.querySelectorAll('.footer-social-icon').forEach(function (el) {
            var color = el.dataset.color;
            el.addEventListener('mouseenter', function () { el.style.color = color; });
            el.addEventListener('mouseleave', function () { el.style.color = ''; });
        });
    </script>

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
    @stack('scripts')
</body>
</html>
