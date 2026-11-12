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
            gtag('config', @json($googleAnalyticsId));
        </script>
    @endif

    @php $favicon = App\Models\Configuracion::favicon(); @endphp
    @if($favicon)
        <link rel="icon" href="{{ url('storage/' . $favicon) }}" type="image/x-icon">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- El aspecto de la tienda: tokens y componentes (ver public/css/tienda.css). El
         ?v= cambia con el archivo, así el navegador no se queda con uno viejo. --}}
    <link href="{{ asset('css/tienda.css') }}?v={{ @filemtime(public_path('css/tienda.css')) }}" rel="stylesheet">
    @php
        $colorPrimario = App\Models\Configuracion::colorPrimario();
        $textoSobrePrimario = $colorPrimario->textoLegible();
        $menuEnSidebar = App\Models\Configuracion::menuEnSidebar();
    @endphp
    {{-- Sólo lo que depende de los Ajustes de cada tienda; todo lo demás está en tienda.css. --}}
    <style>
        :root {
            --color-primary: {{ $colorPrimario->hex() }};
            --color-primary-rgb: {{ $colorPrimario->rgb() }};
            /* Texto legible sobre el color principal: blanco u oscuro según su contraste. */
            --color-primary-texto: {{ $textoSobrePrimario->hex() }};
            --color-primary-texto-rgb: {{ $textoSobrePrimario->rgb() }};
            /* El color principal como texto sobre blanco (precios, links). */
            --color-primary-tinta: {{ $colorPrimario->legibleSobreBlanco()->hex() }};
            --logo-alto: {{ App\Models\Configuracion::logoAlto() }}px;
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
    <nav class="navbar navbar-expand-lg {{ $colorPrimario->esClaro() ? 'navbar-light' : 'navbar-dark' }} navbar-custom sticky-top">
        <div class="container">

            {{-- === BARRA MOBILE: 3 columnas flex, oculta en desktop === --}}
            <div class="d-flex d-lg-none align-items-center w-100">

                {{-- Izquierda: abre drawer --}}
                <div class="barra-movil-lado">
                    <button class="navbar-toggler border-0 p-1" type="button"
                            data-bs-toggle="offcanvas" data-bs-target="#menuDrawer" aria-label="Abrir menú">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                </div>

                {{-- Centro: logo --}}
                <a class="navbar-brand mb-0 d-flex align-items-center" href="{{ route('tienda.index') }}">
                    @if($logoTienda)
                        <img src="{{ url('storage/' . $logoTienda) }}" alt="{{ $nombreTienda }}"
                             class="logo-tienda {{ $mostrarNombre ? 'me-2' : '' }}">
                    @else
                        <i class="bi bi-shop me-2"></i>
                    @endif
                    @if($mostrarNombre || !$logoTienda)
                        {{ $nombreTienda }}
                    @endif
                </a>

                {{-- Derecha: carrito --}}
                <div class="barra-movil-lado barra-movil-lado-derecho">
                    <a href="{{ route('carrito.index') }}"
                       class="icono-barra text-sobre-primario text-decoration-none position-relative p-1 {{ request()->routeIs('carrito.*') ? 'opacity-75' : '' }}">
                        <i class="bi bi-cart3"></i>
                        <span class="cart-badge-mobile badge-carrito badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle{{ $cantidadCarrito > 0 ? '' : ' d-none' }}">
                            {{ $cantidadCarrito ?: '' }}
                        </span>
                    </a>
                </div>
            </div>

            {{-- === LOGO DESKTOP (oculto en mobile) === --}}
            <a class="navbar-brand d-none d-lg-flex align-items-center" href="{{ route('tienda.index') }}">
                @if($logoTienda)
                    <img src="{{ url('storage/' . $logoTienda) }}" alt="{{ $nombreTienda }}"
                         class="logo-tienda {{ $mostrarNombre ? 'me-2' : '' }}">
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
                            <i class="bi bi-cart3 icono-barra"></i>
                            <span class="cart-badge-desktop badge bg-danger{{ $cantidadCarrito > 0 ? '' : ' d-none' }}">
                                {{ $cantidadCarrito ?: '' }}
                            </span>
                        </a>
                    </li>
                    @auth
                        @if(auth()->user()->isAdmin())
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.dashboard') }}">
                                    <i class="bi bi-gear icono-barra"></i> Admin
                                </a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="nav-link btn btn-link">
                                    <i class="bi bi-box-arrow-right icono-barra"></i> Salir
                                </button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">
                                <i class="bi bi-person icono-barra"></i> Acceder
                            </a>
                        </li>
                    @endauth
                </ul>
            </div>

        </div>
    </nav>

    {{-- Drawer mobile --}}
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="menuDrawer" aria-labelledby="menuDrawerLabel">
        <div class="offcanvas-header">
            <a class="text-sobre-primario text-decoration-none fw-bold fs-5" href="{{ route('tienda.index') }}" id="menuDrawerLabel">
                @if($logoTienda)
                    <img src="{{ url('storage/' . $logoTienda) }}" alt="{{ $nombreTienda }}" class="logo-tienda-drawer">
                    @if($mostrarNombre) <span class="ms-2">{{ $nombreTienda }}</span> @endif
                @else
                    <i class="bi bi-shop me-2"></i>{{ $nombreTienda }}
                @endif
            </a>
            <button type="button" class="btn-close {{ $colorPrimario->esClaro() ? '' : 'btn-close-white' }}" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <nav class="flex-grow-1 overflow-auto">
                @include('components.menu-drawer')
            </nav>
            <div class="p-3 drawer-pie">
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

    <div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3">
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
            {{-- El menú al costado sólo en pantallas grandes: más chico se usa el de las
                 tres rayitas, y los productos ocupan todo el ancho. --}}
            <div class="container py-4">
                <div class="tienda-con-lateral">
                    <aside class="sidebar-menu d-none d-lg-block">
                        @include('components.menu-sidebar')
                    </aside>
                    <div class="tienda-con-lateral-contenido">
                        @yield('content-inner')
                        @yield('content')
                    </div>
                </div>
            </div>
        @else
            @yield('content')
        @endif
    </main>

    <footer class="tienda-pie py-3 mt-5">
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
                           style="--red: {{ $red['color'] }};">
                            <i class="bi {{ $red['icon'] }}"></i>
                        </a>
                    @endforeach
                </div>
            @endif
            <div>&copy; {{ date('Y') }} {{ App\Models\Configuracion::obtener('nombre_tienda', 'Tienda MC') }}. Todos los derechos reservados.</div>
            <div class="mt-2">
                <a href="https://tredevs.com.ar/" target="_blank" rel="noopener" class="creado-por d-inline-flex align-items-center gap-1 text-muted text-decoration-none">
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
