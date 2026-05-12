@extends('layouts.app')

@section('title', 'Demasiadas solicitudes')

@section('content')
<div class="container d-flex align-items-center justify-content-center" style="min-height: 50vh;">
    <div class="text-center px-3">
        <div class="mb-3" style="font-size: 5rem; color: #dee2e6;">
            <i class="bi bi-hourglass-split"></i>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-2">Demasiadas solicitudes</h1>
        <p class="text-muted mb-4">Estás haciendo muchas consultas en poco tiempo.<br>Esperá un momento e intentá de nuevo.</p>
        <a href="{{ route('tienda.index') }}" class="btn btn-primary px-4">
            <i class="bi bi-house-door me-2"></i>Volver a la tienda
        </a>
    </div>
</div>
@endsection
