@extends('layouts.admin')

@section('title', 'Nueva característica')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-plus-circle"></i> Nueva característica</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-caracteristicas" titulo="Cómo funcionan las características">
            @include('admin.ayudas.caracteristicas')
        </x-ayuda>
        <a href="{{ route('admin.etiquetas.index') }}" class="btn btn-outline-secondary" title="Volver">
            <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
        </a>
    </div>
</div>

<form action="{{ route('admin.etiquetas.store') }}" method="POST" id="form-caracteristica">
    @csrf
    @include('admin.etiquetas.partials.formulario')
</form>
@endsection
