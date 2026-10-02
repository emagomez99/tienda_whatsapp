@extends('layouts.app')

@section('title', 'Iniciar Sesión')

@section('content')
<div class="container">
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="text-center mb-4">
                @php $logoLogin = App\Models\Configuracion::logo(); @endphp
                @if($logoLogin)
                    <img src="{{ url('storage/' . $logoLogin) }}" alt="{{ App\Models\Configuracion::nombreTienda() }}" class="auth-logo">
                @else
                    <div class="tienda-aviso-icon mx-auto"><i class="bi bi-person-lock"></i></div>
                @endif
                <h1 class="h4 fw-bold mb-1">Iniciar sesión</h1>
                <p class="text-muted small mb-0">Ingresá con tu cuenta para continuar.</p>
            </div>

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="current-password" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Recordarme</label>
                </div>
                <button type="submit" class="btn btn-primary btn-cta w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Ingresar
                </button>
            </form>
        </div>
        <div class="text-center mt-3">
            <a href="{{ route('tienda.index') }}" class="link-quiet">
                <i class="bi bi-arrow-left"></i> Volver a la tienda
            </a>
        </div>
    </div>
</div>
@endsection
