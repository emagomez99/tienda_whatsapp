@extends('layouts.admin')

@section('title', 'Nuevo Producto')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3><i class="bi bi-plus-circle"></i> Nuevo Producto</h3>
    <a href="{{ route('admin.productos.index') }}" class="btn btn-outline-secondary" title="Volver">
        <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
    </a>
</div>

<form id="form-producto" action="{{ route('admin.productos.store') }}" method="POST" enctype="multipart/form-data">
    @csrf

    @include('admin.productos.partials.formulario')

    @include('admin.productos.partials.barra-guardar', ['textoGuardar' => 'Crear producto', 'cancelarUrl' => route('admin.productos.index')])
</form>
@endsection
