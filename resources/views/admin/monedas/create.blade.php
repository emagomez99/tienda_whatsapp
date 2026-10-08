@extends('layouts.admin')

@section('title', 'Nueva moneda')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-plus-circle"></i> Nueva moneda</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-monedas" titulo="Cómo funcionan las monedas">
            @include('admin.ayudas.monedas')
        </x-ayuda>
        <a href="{{ route('admin.monedas.index') }}" class="btn btn-outline-secondary" title="Volver">
            <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
        </a>
    </div>
</div>

<form action="{{ route('admin.monedas.store') }}" method="POST" id="form-moneda">
    @csrf
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Datos de la moneda</h5></div>
                <div class="card-body">
                    @include('admin.monedas.partials.campos')
                </div>
            </div>

        </div>
    </div>

    @include('admin.partials.barra-guardar', [
        'textoGuardar' => 'Crear moneda',
        'cancelarUrl'  => route('admin.monedas.index'),
        'formId'       => 'form-moneda',
    ])
</form>
@endsection
