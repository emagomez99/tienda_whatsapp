{{--
    Inicio de sesión. Quien entra acá es el equipo de la tienda (los usuarios se crean
    desde el panel), así que la pantalla tiene el aspecto del panel -- public/css/admin.css,
    color de acción propio -- y no el de la tienda: un botón amarillo de marca no se lee,
    y es la puerta de entrada al panel. El color de la tienda queda como identidad, en la
    inicial (igual que en el menú lateral del panel).
--}}
@php
    $nombreTienda = App\Models\Configuracion::nombreTienda();
    $logoLogin    = App\Models\Configuracion::logo();
    $favicon      = App\Models\Configuracion::favicon();
    $colorTienda  = App\Models\Configuracion::colorPrimario();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - {{ $nombreTienda }}</title>
    <meta name="robots" content="noindex, nofollow">
    @if($favicon)
        <link rel="icon" href="{{ url('storage/' . $favicon) }}" type="image/x-icon">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}" rel="stylesheet">
    <style>
        :root {
            --admin-color: {{ $colorTienda->hex() }};
            --admin-texto: {{ $colorTienda->textoLegible()->hex() }};
        }
    </style>
</head>
<body class="panel">
    <main class="panel-acceso">
        <div class="panel-acceso-caja">
            <div class="text-center mb-4">
                @if($logoLogin)
                    <img src="{{ url('storage/' . $logoLogin) }}" alt="{{ $nombreTienda }}" class="panel-acceso-logo">
                @else
                    <span class="panel-menu-inicial panel-acceso-inicial">{{ mb_strtoupper(mb_substr($nombreTienda, 0, 1)) }}</span>
                @endif
                <h1 class="h4 fw-semibold mt-3 mb-1">Iniciar sesión</h1>
                <p class="text-muted mb-0">Entrá al panel de {{ $nombreTienda }}</p>
            </div>

            {{-- Avisos al volver acá: "Usuario desactivado", "Acceso no autorizado"... --}}
            @if(session('error'))
                <div class="alert alert-danger py-2 small"><i class="bi bi-x-circle me-1"></i> {{ session('error') }}</div>
            @endif
            @if(session('success'))
                <div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i> {{ session('success') }}</div>
            @endif

            <div class="card">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                                   value="{{ old('email') }}" autocomplete="username" required autofocus>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password"
                                   autocomplete="current-password" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-4 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Recordarme en este equipo</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-box-arrow-in-right"></i> Ingresar
                        </button>
                    </form>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="{{ route('tienda.index') }}" class="text-muted text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Volver a la tienda
                </a>
            </div>
        </div>
    </main>
</body>
</html>
