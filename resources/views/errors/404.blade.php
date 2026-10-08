@extends('layouts.app')

@section('title', 'Página no encontrada')

@section('content')
<div class="container tienda-vacio">
    <div>
        <span class="tienda-vacio-icono"><i class="bi bi-search"></i></span>
        <h1 class="h2 mb-2">Página no encontrada</h1>
        <p class="text-muted mb-4">El producto o la página que buscás no existe o fue eliminado.</p>
        <a href="{{ route('tienda.index') }}" class="btn btn-primary px-4">
            <i class="bi bi-house-door me-2"></i>Volver a la tienda
        </a>
    </div>
</div>
@endsection
