@extends('layouts.admin')

@section('title', 'Editar característica')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-pencil"></i> {{ $etiqueta->nombre }}</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-caracteristicas" titulo="Cómo funcionan las características">
            @include('admin.ayudas.caracteristicas')
        </x-ayuda>
        <a href="{{ route('admin.etiquetas.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<form action="{{ route('admin.etiquetas.update', $etiqueta) }}" method="POST" id="form-caracteristica">
    @csrf
    @method('PUT')
    @include('admin.etiquetas.partials.formulario')
</form>
@endsection
