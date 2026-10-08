@extends('layouts.app')

@section('title', 'Demasiadas solicitudes')

@section('content')
<div class="container tienda-vacio">
    <div>
        <span class="tienda-vacio-icono"><i class="bi bi-hourglass-split"></i></span>
        <h1 class="h2 mb-2">Demasiadas solicitudes</h1>
        <p class="text-muted mb-4">Estás haciendo muchas consultas en poco tiempo.<br>Esperá un momento e intentá de nuevo.</p>
        <a href="{{ route('tienda.index') }}" class="btn btn-primary px-4">
            <i class="bi bi-house-door me-2"></i>Volver a la tienda
        </a>
    </div>
</div>
@endsection
