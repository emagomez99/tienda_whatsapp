@extends('layouts.app')

@section('title', 'Página no encontrada')

@section('content')
<div class="container d-flex align-items-center justify-content-center" style="min-height: 50vh;">
    <div class="text-center px-3">
        <div class="mb-3" style="font-size: 5rem; color: #dee2e6;">
            <i class="bi bi-search"></i>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-2">Página no encontrada</h1>
        <p class="text-muted mb-4">El producto o la página que buscás no existe o fue eliminado.</p>
        <a href="{{ route('tienda.index') }}" class="btn btn-primary px-4">
            <i class="bi bi-house-door me-2"></i>Volver a la tienda
        </a>
    </div>
</div>
@endsection
