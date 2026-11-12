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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- El aspecto del panel: tokens y componentes (ver public/css/admin.css). El
         ?v= cambia con el archivo, así el navegador no se queda con uno viejo. --}}
    <link href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}" rel="stylesheet">
    @php
        // El color de la tienda es identidad (la marca de la opción activa y la vista
        // previa del menú de la tienda); las acciones del panel usan su propio color.
        // Ver App\Support\ColorHex.
        $colorAdmin = App\Models\Configuracion::colorPrimario();
        $textoAdmin = $colorAdmin->textoLegible();
    @endphp
    <style>
        :root {
            --admin-color: {{ $colorAdmin->hex() }};
            --admin-texto: {{ $textoAdmin->hex() }};
            --admin-texto-rgb: {{ $textoAdmin->rgb() }};
        }
    </style>
    @stack('styles')
</head>
<body class="panel">
    @php
        $user = auth()->user();
        $logoAdmin = App\Models\Configuracion::logo();
        $nombreAdmin = App\Models\Configuracion::nombreTienda();
        $gruposMenu = App\Support\MenuDelPanel::para($user);
    @endphp

    {{-- ═══════════════════════════════════════════════
         MENÚ LATERAL: fijo en escritorio, cajón (☰) en celular.
         offcanvas-lg de Bootstrap: el mismo marcado sirve para los dos.
    ═══════════════════════════════════════════════ --}}
    <aside class="panel-menu offcanvas-lg offcanvas-start" tabindex="-1" id="adminDrawer" aria-label="Menú del panel">
        <div class="panel-menu-marca">
            <a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                @if($logoAdmin)
                    <img src="{{ url('storage/' . $logoAdmin) }}" alt="{{ $nombreAdmin }}">
                @else
                    <span class="panel-menu-inicial">{{ mb_strtoupper(mb_substr($nombreAdmin, 0, 1)) }}</span>
                    <span class="panel-menu-nombre">{{ $nombreAdmin }}</span>
                @endif
            </a>
            <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#adminDrawer" aria-label="Cerrar el menú"></button>
        </div>

        <nav class="panel-menu-opciones">
            @foreach($gruposMenu as $titulo => $opciones)
                @if($titulo !== '')
                    <div class="panel-menu-grupo">{{ $titulo }}</div>
                @endif
                @foreach($opciones as $opcion)
                    <a href="{{ $opcion['url'] }}" class="panel-menu-item {{ $opcion['activa'] ? 'active' : '' }}"
                       @if($opcion['activa']) aria-current="page" @endif>
                        <i class="bi {{ $opcion['icono'] }}"></i> {{ $opcion['texto'] }}
                    </a>
                @endforeach
            @endforeach
        </nav>

        {{-- Ayuda siempre a mano. "Ver tienda" y "Cerrar sesión" están arriba a la derecha. --}}
        <div class="panel-menu-pie">
            <a href="{{ route('admin.ayuda.index') }}" class="panel-menu-item {{ request()->routeIs('admin.ayuda.*') ? 'active' : '' }}"
               @if(request()->routeIs('admin.ayuda.*')) aria-current="page" @endif>
                <i class="bi bi-question-circle"></i> Ayuda
            </a>
        </div>
    </aside>

    <div class="panel-principal">
        {{-- ═══════════════════════════════════════════════
             BARRA SUPERIOR
        ═══════════════════════════════════════════════ --}}
        <header class="panel-barra">
            <button class="btn btn-link panel-barra-boton d-lg-none" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#adminDrawer" aria-controls="adminDrawer" aria-label="Abrir el menú">
                <i class="bi bi-list"></i>
            </button>
            <a class="panel-barra-marca d-lg-none" href="{{ route('admin.dashboard') }}">
                @if($logoAdmin)
                    <img src="{{ url('storage/' . $logoAdmin) }}" alt="{{ $nombreAdmin }}">
                @else
                    {{ $nombreAdmin }}
                @endif
            </a>

            <div class="ms-auto d-flex align-items-center gap-1">
                <a class="btn btn-link panel-barra-boton d-none d-md-inline-flex" href="{{ route('tienda.index') }}" target="_blank">
                    <i class="bi bi-shop"></i><span class="ms-1">Ver tienda</span>
                </a>
                <div class="dropdown">
                    <button class="btn btn-link panel-barra-boton" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="panel-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <span class="d-none d-md-inline ms-1">{{ $user->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted small">{{ $user->email }}</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-md-none" href="{{ route('tienda.index') }}" target="_blank">
                                <i class="bi bi-shop me-1"></i> Ver tienda
                            </a>
                        </li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        {{-- ═══════════════════════════════════════════════
             CONTENIDO
        ═══════════════════════════════════════════════ --}}
        <main class="contenido-panel">
            @yield('content')
        </main>
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
        {{-- Un aviso (ej. "No se devolvió el stock de..."): queda hasta que se cierra, para poder leerlo. --}}
        @if(session('warning'))
            <div class="toast align-items-center text-bg-warning border-0" role="alert" data-bs-autohide="false">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-exclamation-triangle me-1"></i> {{ session('warning') }}</div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                </div>
            </div>
        @endif
        {{-- Un error queda hasta que se cierra: en 4 segundos no se llega a leer. --}}
        @if(session('error'))
            <div class="toast align-items-center text-bg-danger border-0" role="alert" data-bs-autohide="false">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-x-circle me-1"></i> {{ session('error') }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                </div>
            </div>
        @endif
        {{-- Lo que no pasó la validación: hay pantallas que no lo muestran al lado del
             campo (cambiar una cantidad, renombrar un valor) y parecía que no pasaba nada. --}}
        @if(isset($errors) && $errors->any())
            <div class="toast align-items-center text-bg-danger border-0" role="alert" data-bs-autohide="false">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-x-circle me-1"></i> No se guardó: {{ $errors->first() }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
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
