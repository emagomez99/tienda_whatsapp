@extends('layouts.admin')

@section('title', 'Valores de ' . $etiqueta->nombre)

@section('content')
@php $puedeEditar = auth()->user()->puede('etiquetas.editar'); @endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-tag"></i> {{ $etiqueta->nombre }}</h3>
    <div class="d-flex gap-2">
        @if($puedeEditar)
            <a href="{{ route('admin.etiquetas.edit', $etiqueta) }}" class="btn btn-outline-primary">
                <i class="bi bi-pencil"></i> Editar etiqueta
            </a>
        @endif
        <a href="{{ route('admin.etiquetas.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

@include('admin.etiquetas.partials.fusion-propuesta')

<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('admin.etiquetas.show', $etiqueta) }}" method="GET" class="row g-2">
            <div class="col">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar valor..." value="{{ $buscar }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i> Buscar</button>
                @if($buscar !== '')
                    <a href="{{ route('admin.etiquetas.show', $etiqueta) }}" class="btn btn-outline-secondary">Limpiar</a>
                @endif
            </div>
        </form>
    </div>
</div>

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
    {{ $valores->links() }}
</div>
@endsection

@push('scripts')
    @include('admin.etiquetas.partials.valores-scripts')
@endpush
