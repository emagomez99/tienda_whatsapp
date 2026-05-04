<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Tienda MC'))</title>
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
        .card-img-top { height: 200px; object-fit: cover; }
        .producto-card { transition: transform 0.2s; }
        .producto-card:hover { transform: translateY(-5px); }
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
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="{{ route('tienda.index') }}">
                @php
                    $logoTienda = App\Models\Configuracion::logo();
                    $mostrarNombre = App\Models\Configuracion::mostrarNombreTienda();
                @endphp
                @if($logoTienda)
                    <img src="{{ url('storage/' . $logoTienda) }}" alt="{{ App\Models\Configuracion::nombreTienda() }}" style="max-height: 40px;" class="{{ $mostrarNombre ? 'me-2' : '' }}">
                @else
                    <i class="bi bi-shop me-2"></i>
                @endif
                @if($mostrarNombre || !$logoTienda)
                    {{ App\Models\Configuracion::nombreTienda() }}
                @endif
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                @if(!$menuEnSidebar)
                    @include('components.menu-tienda')
                @endif
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('carrito.*') ? 'active' : '' }}" href="{{ route('carrito.index') }}">
                            <i class="bi bi-cart3"></i> Carrito
                            @php
                                $cantidadCarrito = array_sum(session()->get('carrito', []));
                            @endphp
                            @if($cantidadCarrito > 0)
                                <span class="badge bg-danger">{{ $cantidadCarrito }}</span>
                            @endif
                        </a>
                    </li>
                    @auth
                        @if(auth()->user()->isAdmin())
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.dashboard') }}">
                                    <i class="bi bi-gear"></i> Admin
                                </a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="nav-link btn btn-link">
                                    <i class="bi bi-box-arrow-right"></i> Salir
                                </button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">
                                <i class="bi bi-person"></i> Acceder
                            </a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

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
            <div>&copy; {{ date('Y') }} {{ App\Models\Configuracion::obtener('nombre_tienda', 'Tienda MC') }}. Todos los derechos reservados.</div>
            <div class="mt-1">Desarrollado por <a href="https://tredevs.com.ar/" target="_blank" rel="noopener" class="text-muted">Tredevs</a></div>
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

        // Actualizar badge del carrito
        function updateCartBadge(cantidad) {
            var badge = document.querySelector('.nav-link .badge.bg-danger');
            var cartLink = document.querySelector('a[href="{{ route("carrito.index") }}"]');
            if (cantidad > 0) {
                if (badge) {
                    badge.textContent = cantidad;
                } else if (cartLink) {
                    var b = document.createElement('span');
                    b.className = 'badge bg-danger';
                    b.textContent = cantidad;
                    cartLink.appendChild(b);
                }
            }
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
