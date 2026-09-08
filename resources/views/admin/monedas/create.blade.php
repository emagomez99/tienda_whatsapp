@extends('layouts.admin')

@section('title', 'Nueva Moneda')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-plus-circle"></i> Nueva Moneda</h3>
    <a href="{{ route('admin.monedas.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form action="{{ route('admin.monedas.store') }}" method="POST">
    @csrf
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Información</h5></div>
                <div class="card-body">
                    @include('admin.monedas.partials.campos')
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Guardar Moneda
                </button>
                <a href="{{ route('admin.monedas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>
@endsection
