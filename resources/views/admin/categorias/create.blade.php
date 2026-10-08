@extends('layouts.admin')

@section('title', 'Nueva categoría')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-plus-circle"></i> Nueva categoría</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-categorias" titulo="Cómo funcionan las categorías">
            @include('admin.ayudas.categorias')
        </x-ayuda>
        <a href="{{ route('admin.categorias.index') }}" class="btn btn-outline-secondary" title="Volver">
            <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
        </a>
    </div>
</div>

<form action="{{ route('admin.categorias.store') }}" method="POST" id="form-categoria">
    @csrf
    @include('admin.categorias.partials.formulario')
</form>
@endsection
