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
        /* ── Desktop sidebar ── */
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
        .sidebar .nav-link i { margin-right: 0.5rem; }
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
        .content-wrapper { min-height: calc(100vh - 56px); }
        body { overflow-x: hidden; }

        /* ── Mobile drawer ── */
        #adminDrawer { background-color: #343a40; width: 280px; }
        .admin-drawer-item {
            display: block;
            width: 100%;
            color: rgba(255,255,255,.85);
            text-decoration: none;
            padding: .85rem 1.5rem;
            font-size: .97rem;
            border: none;
            background: none;
            border-bottom: 1px solid rgba(255,255,255,.08);
            text-align: left;
            cursor: pointer;
        }
        .admin-drawer-item:hover, .admin-drawer-item:focus {
            background: rgba(255,255,255,.1);
            color: #fff;
            outline: none;
        }
        .admin-drawer-item.active {
            background: rgba(255,255,255,.18);
            color: #fff;
        }
        .admin-drawer-sub {
            display: block;
            color: rgba(255,255,255,.65);
            text-decoration: none;
            padding: .65rem 1.5rem .65rem 2.5rem;
            font-size: .9rem;
            border-bottom: 1px solid rgba(255,255,255,.06);
            background: rgba(0,0,0,.2);
        }
        .admin-drawer-sub:hover { background: rgba(0,0,0,.32); color: #fff; }
        .admin-drawer-sub.active { color: #fff; background: rgba(0,0,0,.3); }
        .admin-drawer-chevron { transition: transform .2s ease; font-size: .8rem; opacity: .65; }
        .admin-drawer-toggle[aria-expanded="true"] .admin-drawer-chevron { transform: rotate(180deg); }
        .admin-drawer-footer-link {
            display: block;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            padding: .5rem 0;
            font-size: .95rem;
        }
        .admin-drawer-footer-link:hover { color: #fff; }
    </style>
    @stack('styles')
</head>
<body>
    @php
        $user = auth()->user();
        $logoAdmin = App\Models\Configuracion::logo();
        $nombreAdmin = App\Models\Configuracion::nombreTienda();
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

    {{-- ═══════════════════════════════════════════════
         NAVBAR
    ═══════════════════════════════════════════════ --}}
    <nav class="navbar navbar-expand-md navbar-dark bg-dark sticky-top">
        <div class="container-fluid">

            {{-- Mobile: 3 columnas --}}
            <div class="d-flex d-md-none align-items-center w-100">
                <div style="flex:1;">
                    <button class="btn btn-link text-white p-1"
                            data-bs-toggle="offcanvas" data-bs-target="#adminDrawer"
                            aria-label="Abrir menú" style="font-size:1.3rem;line-height:1;">
                        <i class="bi bi-list"></i>
                    </button>
                </div>
                <a class="navbar-brand mb-0 d-flex align-items-center" href="{{ route('admin.dashboard') }}">
                    @if($logoAdmin)
                        <img src="{{ url('storage/' . $logoAdmin) }}" alt="{{ $nombreAdmin }}" style="max-height:32px;">
                    @else
                        <span style="font-size:.95rem;">{{ $nombreAdmin }}</span>
                    @endif
                </a>
                <div style="flex:1;"></div>
            </div>

            {{-- Desktop --}}
            <button class="btn btn-link text-white p-1 me-2 d-none d-md-inline-flex align-items-center"
                    id="btn-toggle-sidebar" title="Colapsar menú" style="font-size:1.2rem;line-height:1;">
                <i class="bi bi-layout-sidebar"></i>
            </button>
            <a class="navbar-brand d-none d-md-flex align-items-center" href="{{ route('admin.dashboard') }}">
                @if($logoAdmin)
                    <img src="{{ url('storage/' . $logoAdmin) }}" alt="{{ $nombreAdmin }}" style="max-height:35px;" class="me-2">
                @else
                    {{ $nombreAdmin }}
                @endif
            </a>
            <div class="collapse navbar-collapse d-none d-md-flex" id="navbarAdmin">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('tienda.index') }}" target="_blank">
                            <i class="bi bi-shop"></i> Ver Tienda
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> {{ $user->name }}
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

    {{-- ═══════════════════════════════════════════════
         DRAWER MOBILE
    ═══════════════════════════════════════════════ --}}
    <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="adminDrawer" data-bs-scroll="true" data-bs-backdrop="true">
        <div class="offcanvas-header" style="border-bottom:1px solid rgba(255,255,255,.12);">
            <span class="text-white fw-bold fs-6">
                @if($logoAdmin)
                    <img src="{{ url('storage/' . $logoAdmin) }}" alt="{{ $nombreAdmin }}" style="max-height:28px;">
                @else
                    <i class="bi bi-gear me-1"></i> Admin
                @endif
            </span>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0" style="overflow:hidden;">
            <nav class="overflow-y-auto" style="flex:1 1 0;min-height:0;">
                @if($user->puede('dashboard.ver'))
                    <a href="{{ route('admin.dashboard') }}"
                       class="admin-drawer-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                @endif
                @if($user->puede('pedidos.ver'))
                    <a href="{{ route('admin.pedidos.index') }}"
                       class="admin-drawer-item {{ request()->routeIs('admin.pedidos.*') ? 'active' : '' }}">
                        <i class="bi bi-bag-check me-2"></i> Pedidos
                    </a>
                @endif
                @if($user->puede('productos.ver'))
                    <a href="{{ route('admin.productos.index') }}"
                       class="admin-drawer-item {{ request()->routeIs('admin.productos.*') ? 'active' : '' }}">
                        <i class="bi bi-box-seam me-2"></i> Productos
                    </a>
                @endif
                @if($user->puede('proveedores.ver'))
                    <a href="{{ route('admin.proveedores.index') }}"
                       class="admin-drawer-item {{ request()->routeIs('admin.proveedores.*') ? 'active' : '' }}">
                        <i class="bi bi-truck me-2"></i> Proveedores
                    </a>
                @endif
                @if($verConfigGrupo)
                    <button class="admin-drawer-item admin-drawer-toggle d-flex justify-content-between align-items-center"
                            data-bs-toggle="collapse" data-bs-target="#drawer-config"
                            aria-expanded="{{ $configActive ? 'true' : 'false' }}">
                        <span><i class="bi bi-gear me-2"></i> Configuración</span>
                        <i class="bi bi-chevron-down admin-drawer-chevron"></i>
                    </button>
                    <div class="collapse {{ $configActive ? 'show' : '' }}" id="drawer-config">
                        @if($user->puede('configuraciones.ver'))
                            <a href="{{ route('admin.configuraciones.index') }}"
                               class="admin-drawer-sub {{ request()->routeIs('admin.configuraciones.*') ? 'active' : '' }}">
                                <i class="bi bi-sliders me-2"></i> Ajustes
                            </a>
                        @endif
                        @if($user->puede('etiquetas.ver'))
                            <a href="{{ route('admin.etiquetas.index') }}"
                               class="admin-drawer-sub {{ request()->routeIs('admin.etiquetas.*') ? 'active' : '' }}">
                                <i class="bi bi-tags me-2"></i> Etiquetas
                            </a>
                        @endif
                        @if($user->puede('menus.ver'))
                            <a href="{{ route('admin.menus.index') }}"
                               class="admin-drawer-sub {{ request()->routeIs('admin.menus.*') ? 'active' : '' }}">
                                <i class="bi bi-list-nested me-2"></i> Menú Tienda
                            </a>
                        @endif
                        @if($user->puede('usuarios.ver'))
                            <a href="{{ route('admin.usuarios.index') }}"
                               class="admin-drawer-sub {{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}">
                                <i class="bi bi-people me-2"></i> Usuarios
                            </a>
                        @endif
                        @if($user->puede('perfiles.ver'))
                            <a href="{{ route('admin.perfiles.index') }}"
                               class="admin-drawer-sub {{ request()->routeIs('admin.perfiles.*') ? 'active' : '' }}">
                                <i class="bi bi-shield-check me-2"></i> Perfiles
                            </a>
                        @endif
                    </div>
                @endif
            </nav>
            <div class="p-3" style="border-top:1px solid rgba(255,255,255,.12);">
                <a href="{{ route('tienda.index') }}" target="_blank" class="admin-drawer-footer-link">
                    <i class="bi bi-shop me-2"></i> Ver Tienda
                </a>
                <form action="{{ route('logout') }}" method="POST" class="mt-1">
                    @csrf
                    <button type="submit" class="admin-drawer-footer-link btn btn-link p-0 w-100 text-start">
                        <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         CONTENIDO PRINCIPAL
    ═══════════════════════════════════════════════ --}}
    <div class="container-fluid">
        <div class="row">
            {{-- Sidebar desktop --}}
            <nav class="col-md-3 col-lg-2 d-none d-md-block sidebar" id="adminSidebar">
                <div class="position-sticky pt-3">
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

            <main class="col-md-9 col-lg-10 px-md-4 content-wrapper" id="adminContent">
                <div class="py-3 py-md-4">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    {{-- Toasts --}}
    <div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1100;">
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var sidebar = document.getElementById('adminSidebar');
            var content = document.getElementById('adminContent');
            var btn = document.getElementById('btn-toggle-sidebar');
            if (!sidebar || !content || !btn) return;

            var collapsed = localStorage.getItem('adminSidebarCollapsed') === '1';

            function applyState(isCollapsed) {
                if (isCollapsed) {
                    sidebar.classList.remove('d-md-block');
                    sidebar.classList.add('d-none');
                    content.classList.remove('col-md-9', 'col-lg-10');
                    content.classList.add('col-12');
                    btn.querySelector('i').className = 'bi bi-layout-sidebar-inset';
                    btn.title = 'Expandir menú';
                } else {
                    sidebar.classList.remove('d-none');
                    sidebar.classList.add('d-md-block');
                    content.classList.remove('col-12');
                    content.classList.add('col-md-9', 'col-lg-10');
                    btn.querySelector('i').className = 'bi bi-layout-sidebar';
                    btn.title = 'Colapsar menú';
                }
            }

            if (collapsed) applyState(true);

            btn.addEventListener('click', function () {
                collapsed = !collapsed;
                localStorage.setItem('adminSidebarCollapsed', collapsed ? '1' : '0');
                applyState(collapsed);
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
