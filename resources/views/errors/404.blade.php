@extends('layouts.app')

@section('title', 'Página no encontrada')

@section('content')
<div class="container estado-pagina">
    <div>
        <div class="estado-icon"><i class="bi bi-search"></i></div>
        <h1 class="page-title mb-2">Página no encontrada</h1>
        <p class="text-muted mb-4">El producto o la página que buscás no existe o fue eliminado.</p>
        <a href="{{ route('tienda.index') }}" class="btn btn-primary btn-cta">
            <i class="bi bi-house-door"></i> Volver a la tienda
        </a>
    </div>
</div>
@endsection
