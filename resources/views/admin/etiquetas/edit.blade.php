@extends('layouts.admin')

@section('title', 'Editar característica')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-pencil"></i> {{ $etiqueta->nombre }}</h3>
    <a href="{{ route('admin.etiquetas.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form action="{{ route('admin.etiquetas.update', $etiqueta) }}" method="POST">
    @csrf
    @method('PUT')
    @include('admin.etiquetas.partials.formulario')
</form>
@endsection
