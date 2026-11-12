@extends('layouts.admin')

@section('title', 'Editar producto')

@section('content')
@php
    $vuelta = App\Http\Controllers\Admin\ProductoController::vueltaAlPanel(request('_back_url'));
    $backUrl = $vuelta
        ? $vuelta
        : route('admin.productos.index') . (request('_back') ? '?' . request('_back') : '');
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-pencil"></i> Editar producto <span class="text-muted fw-normal">#{{ $producto->id }}</span></h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-productos" titulo="Cómo cargar productos">
            @include('admin.ayudas.productos')
        </x-ayuda>
        <div class="d-flex gap-2">
            {{-- Pestaña nueva: así no se pierde lo que se esté editando acá. --}}
            <a href="{{ $producto->url() }}" target="_blank" rel="noopener" class="btn btn-outline-primary"
               title="{{ $producto->disponible ? 'Abrir la página del producto en la tienda' : 'No está disponible: los clientes no lo ven en los listados' }}">
                <i class="bi bi-box-arrow-up-right"></i><span class="d-none d-sm-inline"> Ver en la tienda</span>
                @unless($producto->disponible)
                    <span class="badge bg-secondary ms-1">oculto</span>
                @endunless
            </a>
            <a href="{{ $backUrl }}" class="btn btn-outline-secondary" title="Volver">
                <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
            </a>
        </div>
    </div>
</div>

<form id="form-producto" action="{{ route('admin.productos.update', $producto) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    @include('admin.productos.partials.formulario')

    @if($vuelta)
        <input type="hidden" name="_back_url" value="{{ $vuelta }}">
    @else
        <input type="hidden" name="_back" value="{{ request('_back', '') }}">
    @endif
    @include('admin.partials.barra-guardar', ['textoGuardar' => 'Guardar cambios', 'cancelarUrl' => $backUrl])
</form>

<!-- Modal: ajuste de stock -->
@include('admin.productos.partials.modal-ajuste-stock')
@endsection
