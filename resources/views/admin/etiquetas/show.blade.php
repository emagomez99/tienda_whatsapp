@extends('layouts.admin')

@section('title', 'Valores de ' . $etiqueta->nombre)

@section('content')
@php $puedeEditar = auth()->user()->puede('etiquetas.editar'); @endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-tag"></i> {{ $etiqueta->nombre }}</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-caracteristicas" titulo="Cómo funcionan las características">
            @include('admin.ayudas.caracteristicas')
        </x-ayuda>
        <div class="d-flex gap-2">
            @if($puedeEditar)
                <a href="{{ route('admin.etiquetas.edit', $etiqueta) }}" class="btn btn-outline-primary">
                    <i class="bi bi-pencil"></i> Editar característica
                </a>
            @endif
            <a href="{{ route('admin.etiquetas.index') }}" class="btn btn-outline-secondary" title="Volver">
                <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
            </a>
        </div>
    </div>
</div>

@include('admin.etiquetas.partials.fusion-propuesta')

<x-filtros :url="route('admin.etiquetas.show', $etiqueta)" buscar="Buscar valor" titulo="Filtrar valores"
           :campos="[
               ['nombre' => 'visible', 'titulo' => 'En la tienda', 'opciones' => ['1' => 'Se muestran', '0' => 'Ocultos']],
               ['nombre' => 'uso', 'titulo' => 'Productos', 'opciones' => ['con' => 'Con productos', 'sin' => 'Sin productos']],
           ]" />

<div class="card">
    <div class="card-header text-muted small">
        {{ number_format($valores->total(), 0, ',', '.') }} {{ $valores->total() === 1 ? 'valor' : 'valores' }}
        @if($buscar !== '') con «{{ $buscar }}» @endif
    </div>
    <div class="card-body py-2">
        @forelse($valores as $valor)
            @include('admin.etiquetas.partials.valor-fila', ['valor' => $valor, 'puedeEditar' => $puedeEditar])
        @empty
            <div class="text-muted py-2">No hay valores{{ $buscar !== '' ? ' que coincidan' : '' }}.</div>
        @endforelse
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    {{ $valores->withQueryString()->links() }}
</div>
@endsection

@push('scripts')
    @include('admin.etiquetas.partials.valores-scripts')
@endpush
