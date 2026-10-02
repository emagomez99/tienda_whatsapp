@extends('layouts.app')

@section('title', 'Demasiadas solicitudes')

@section('content')
<div class="container estado-pagina">
    <div>
        <div class="estado-icon"><i class="bi bi-hourglass-split"></i></div>
        <h1 class="page-title mb-2">Demasiadas solicitudes</h1>
        <p class="text-muted mb-4">Estás haciendo muchas consultas en poco tiempo.<br>Esperá un momento e intentá de nuevo.</p>
        <a href="{{ route('tienda.index') }}" class="btn btn-primary btn-cta">
            <i class="bi bi-house-door"></i> Volver a la tienda
        </a>
    </div>
</div>
@endsection
