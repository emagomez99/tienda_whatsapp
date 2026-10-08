@extends('layouts.admin')

@section('title', 'Nueva característica')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-plus-circle"></i> Nueva característica</h3>
    <a href="{{ route('admin.etiquetas.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form action="{{ route('admin.etiquetas.store') }}" method="POST">
    @csrf
    @include('admin.etiquetas.partials.formulario')
</form>
@endsection
