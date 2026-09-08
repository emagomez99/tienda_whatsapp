@extends('layouts.admin')

@section('title', 'Editar Moneda')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-pencil"></i> Editar Moneda <span class="text-muted fw-normal">{{ $moneda->codigo }}</span></h3>
    <a href="{{ route('admin.monedas.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form id="form-moneda" action="{{ route('admin.monedas.update', $moneda) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="row">
        <div class="col-md-8">
            @if(!empty($usos))
                <div class="alert alert-warning d-flex gap-2 py-2 px-3">
                    <i class="bi bi-exclamation-triangle mt-1"></i>
                    <div class="small">
                        Esta moneda está en uso en {{ implode(', ', $usos) }}. No se puede eliminar ni desactivar,
                        y cambiar su cotización recalcula los precios de los productos que trabajan con margen.
                    </div>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Información</h5></div>
                <div class="card-body">
                    @include('admin.monedas.partials.campos')
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Guardar Cambios
                </button>
                <a href="{{ route('admin.monedas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

@include('admin.monedas.partials.overlay-recalculo')
@endsection
