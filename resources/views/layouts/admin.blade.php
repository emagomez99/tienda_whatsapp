<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - {{ config('app.name', 'Tienda MC') }}</title>
    @php $favicon = App\Models\Configuracion::favicon(); @endphp
    @if($favicon)
        <link rel="icon" href="{{ url('storage/' . $favicon) }}" type="image/x-icon">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.75);
            padding: 0.75rem 1rem;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,.1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.2);
        }
        .sidebar .nav-link i {
            margin-right: 0.5rem;
        }
        .sidebar .nav-submenu .nav-link {
            padding: 0.45rem 1rem 0.45rem 1.75rem;
            font-size: 0.875rem;
            color: rgba(255,255,255,.6);
        }
        .sidebar .nav-submenu .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,.08);
        }
        .sidebar .nav-submenu .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.15);
        }
        .sidebar .collapse-toggle .bi-chevron-down {
            transition: transform 0.2s ease;
            font-size: 0.75rem;
        }
        .sidebar .collapse-toggle[aria-expanded="true"] .bi-chevron-down {
            transform: rotate(180deg);
        }
        .content-wrapper {
            min-height: calc(100vh - 56px);
        }
    </style>
    @stack('styles')
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="{{ route('admin.dashboard') }}">
                @php $logoAdmin = App\Models\Configuracion::logo(); @endphp
                @if($logoAdmin)
                    <img src="{{ url('storage/' . $logoAdmin) }}" alt="{{ App\Models\Configuracion::nombreTienda() }}" style="max-height: 35px;" class="me-2">
                @else
                    <i class="bi bi-gear-fill me-2"></i>
                @endif
                Panel de Administración
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarAdmin">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarAdmin">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('tienda.index') }}" target="_blank">
                            <i class="bi bi-shop"></i> Ver Tienda
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> {{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    @php $user = auth()->user(); @endphp
                    <ul class="nav flex-column">
                        @if($user->puede('dashboard.ver'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        @endif
                        @if($user->puede('pedidos.ver'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.pedidos.*') ? 'active' : '' }}" href="{{ route('admin.pedidos.index') }}">
                                <i class="bi bi-bag-check"></i> Pedidos
                            </a>
                        </li>
                        @endif
                        @if($user->puede('productos.ver'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.productos.*') ? 'active' : '' }}" href="{{ route('admin.productos.index') }}">
                                <i class="bi bi-box-seam"></i> Productos
                            </a>
                        </li>
                        @endif
                        @if($user->puede('proveedores.ver'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.proveedores.*') ? 'active' : '' }}" href="{{ route('admin.proveedores.index') }}">
                                <i class="bi bi-truck"></i> Proveedores
                            </a>
                        </li>
                        @endif
                        @php
                            $configActive = request()->routeIs('admin.configuraciones.*')
                                || request()->routeIs('admin.etiquetas.*')
                                || request()->routeIs('admin.menus.*')
                                || request()->routeIs('admin.usuarios.*')
                                || request()->routeIs('admin.perfiles.*');
                            $verConfigGrupo = $user->puede('configuraciones.ver')
                                || $user->puede('etiquetas.ver')
                                || $user->puede('menus.ver')
                                || $user->puede('usuarios.ver')
                                || $user->puede('perfiles.ver');
                        @endphp
                        @if($verConfigGrupo)
                        <li class="nav-item">
                            <a class="nav-link collapse-toggle d-flex justify-content-between align-items-center {{ $configActive ? 'active' : '' }}"
                               href="#menu-configuracion"
                               data-bs-toggle="collapse"
                               role="button"
                               aria-expanded="{{ $configActive ? 'true' : 'false' }}"
                               aria-controls="menu-configuracion">
                                <span><i class="bi bi-gear"></i> Configuración</span>
                                <i class="bi bi-chevron-down"></i>
                            </a>
                            <div class="collapse {{ $configActive ? 'show' : '' }}" id="menu-configuracion">
                                <ul class="nav flex-column nav-submenu">
                                    @if($user->puede('configuraciones.ver'))
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.configuraciones.*') ? 'active' : '' }}" href="{{ route('admin.configuraciones.index') }}">
                                            <i class="bi bi-sliders"></i> Ajustes
                                        </a>
                                    </li>
                                    @endif
                                    @if($user->puede('etiquetas.ver'))
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.etiquetas.*') ? 'active' : '' }}" href="{{ route('admin.etiquetas.index') }}">
                                            <i class="bi bi-tags"></i> Etiquetas
                                        </a>
                                    </li>
                                    @endif
                                    @if($user->puede('menus.ver'))
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.menus.*') ? 'active' : '' }}" href="{{ route('admin.menus.index') }}">
                                            <i class="bi bi-list-nested"></i> Menú Tienda
                                        </a>
                                    </li>
                                    @endif
                                    @if($user->puede('usuarios.ver'))
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}" href="{{ route('admin.usuarios.index') }}">
                                            <i class="bi bi-people"></i> Usuarios
                                        </a>
                                    </li>
                                    @endif
                                    @if($user->puede('perfiles.ver'))
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.perfiles.*') ? 'active' : '' }}" href="{{ route('admin.perfiles.index') }}">
                                            <i class="bi bi-shield-check"></i> Perfiles
                                        </a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-wrapper">
                <div class="py-4">

                    @yield('content')
                </div>
            </main>
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
        @if(session('error'))
            <div class="toast align-items-center text-bg-danger border-0" role="alert" data-bs-autohide="true" data-bs-delay="4000">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-x-circle me-1"></i> {{ session('error') }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('#toast-container .toast').forEach(function (el) {
                new bootstrap.Toast(el).show();
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
