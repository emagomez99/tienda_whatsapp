@extends('layouts.admin')

@section('title', $tema['titulo'] . ' — Ayuda')

{{-- Un tema de la Ayuda: la misma explicación que "Cómo funciona" en su pantalla, y el botón para ir a ella. --}}
@section('content')
<nav aria-label="Estás en" class="small mb-2">
    <a href="{{ route('admin.ayuda.index') }}" class="text-decoration-none"><i class="bi bi-question-circle"></i> Ayuda</a>
    <span class="text-muted mx-1">›</span>
    <span class="text-muted">{{ $tema['titulo'] }}</span>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h3 class="mb-0"><i class="bi {{ $tema['icono'] }}"></i> {{ $tema['titulo'] }}</h3>
    @if($tema['ruta'])
        <a href="{{ route($tema['ruta']) }}" class="btn btn-primary">{{ $tema['boton'] }} <i class="bi bi-arrow-right"></i></a>
    @endif
</div>

<div class="card" style="max-width: 760px;">
    <div class="card-body ayuda-pagina">
        @include($tema['vista'])
    </div>
</div>

<a href="{{ route('admin.ayuda.index') }}" class="btn btn-outline-secondary mt-3"><i class="bi bi-arrow-left"></i> Todos los temas</a>
@endsection
