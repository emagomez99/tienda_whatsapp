<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - {{ config('app.name', 'Tienda MC') }}</title>
    <meta name="robots" content="noindex, nofollow">
    @php $favicon = App\Models\Configuracion::favicon(); @endphp
    @if($favicon)
        <link rel="icon" href="{{ url('storage/' . $favicon) }}" type="image/x-icon">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { overflow-x: hidden; }
        .content-wrapper { min-height: calc(100vh - 56px); }
        .navbar-brand, .navbar .nav-link { text-decoration: none; }

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
        #adminDrawer nav { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.15) transparent; }
        #adminDrawer nav::-webkit-scrollbar { width: 4px; }
        #adminDrawer nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 2px; }
        #adminDrawer nav::-webkit-scrollbar-track { background: transparent; }
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
    <nav class="navbar navbar-dark bg-dark sticky-top">
        <div class="container-fluid d-flex align-items-center">
            {{-- Izquierda: hamburger --}}
            <div style="flex:1;">
                <button class="btn btn-link text-white p-1"
                        data-bs-toggle="offcanvas" data-bs-target="#adminDrawer"
                        aria-label="Abrir menú" style="font-size:1.3rem;line-height:1;">
                    <i class="bi bi-list"></i>
                </button>
            </div>

            {{-- Centro: logo --}}
            <a class="navbar-brand mb-0 d-flex align-items-center" href="{{ route('admin.dashboard') }}">
                @if($logoAdmin)
                    <img src="{{ url('storage/' . $logoAdmin) }}" alt="{{ $nombreAdmin }}" style="max-height:34px;">
                @else
                    {{ $nombreAdmin }}
                @endif
            </a>

            {{-- Derecha: acciones --}}
            <div style="flex:1;" class="d-flex align-items-center justify-content-end gap-2">
                <a class="nav-link text-white d-none d-md-block" href="{{ route('tienda.index') }}" target="_blank" style="font-size:.9rem;">
                    <i class="bi bi-shop me-1"></i> Ver Tienda
                </a>
                <div class="dropdown">
                    <button class="btn btn-link text-white p-1 d-flex align-items-center gap-1"
                            data-bs-toggle="dropdown" style="font-size:.9rem;text-decoration:none;">
                        <i class="bi bi-person-circle" style="font-size:1.2rem;"></i>
                        <span class="d-none d-md-inline">{{ $user->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted small">{{ $user->name }}</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-md-none" href="{{ route('tienda.index') }}" target="_blank">
                                <i class="bi bi-shop me-1"></i> Ver Tienda
                            </a>
                        </li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="bi bi-box-arrow-right me-1"></i> Cerrar Sesión
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    {{-- ═══════════════════════════════════════════════
         DRAWER (mobile + desktop)
    ═══════════════════════════════════════════════ --}}
    <div class="offcanvas offcanvas-start" tabindex="-1" id="adminDrawer" data-bs-scroll="true" data-bs-backdrop="true">
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
         CONTENIDO
    ═══════════════════════════════════════════════ --}}
    <main class="content-wrapper px-3 px-md-4">
        <div class="py-3 py-md-4">
            @yield('content')
        </div>
    </main>

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
    document.addEventListener('show.bs.collapse', function (e) {
        var btn = document.querySelector('[data-bs-target="#' + e.target.id + '"]');
        if (btn) { var c = btn.querySelector('.filtros-chevron'); if (c) c.style.transform = 'rotate(180deg)'; }
    });
    document.addEventListener('hide.bs.collapse', function (e) {
        var btn = document.querySelector('[data-bs-target="#' + e.target.id + '"]');
        if (btn) { var c = btn.querySelector('.filtros-chevron'); if (c) c.style.transform = 'rotate(0deg)'; }
    });
    </script>
    @include('partials.modal-confirmar')
    @stack('scripts')

    {{-- Va DESPUÉS del stack para que los popovers que agreguen las vistas también
         queden inicializados. getOrCreateInstance evita duplicar la instancia si
         alguna vista ya lo hizo por su cuenta. --}}
    <script>
        (function () {
            if (typeof bootstrap === 'undefined' || !bootstrap.Popover) return;

            document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
                bootstrap.Popover.getOrCreateInstance(el);
            });
        })();
    </script>
</body>
</html>
