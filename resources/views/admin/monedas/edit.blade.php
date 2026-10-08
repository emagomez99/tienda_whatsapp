@extends('layouts.admin')

@section('title', 'Editar moneda')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-pencil"></i> Editar moneda <span class="text-muted fw-normal">{{ $moneda->codigo }}</span></h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-monedas" titulo="Cómo funcionan las monedas">
            @include('admin.ayudas.monedas')
        </x-ayuda>
        <a href="{{ route('admin.monedas.index') }}" class="btn btn-outline-secondary" title="Volver">
            <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
        </a>
    </div>
</div>

<form id="form-moneda" action="{{ route('admin.monedas.update', $moneda) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="row">
        <div class="col-lg-8">
            @if(!empty($usos))
                <div class="alert alert-warning d-flex gap-2 py-2 px-3">
                    <i class="bi bi-exclamation-triangle mt-1"></i>
                    <div class="small">
                        Esta moneda está en uso en {{ implode(', ', $usos) }}. No se puede eliminar ni desactivar,
                        y cambiar su cotización recalcula los precios de los productos que trabajan con margen.
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h5 class="mb-0">Datos de la moneda</h5></div>
                <div class="card-body">
                    @include('admin.monedas.partials.campos')
                </div>
            </div>

        </div>
    </div>

    @include('admin.partials.barra-guardar', [
        'textoGuardar' => 'Guardar cambios',
        'cancelarUrl'  => route('admin.monedas.index'),
        'formId'       => 'form-moneda',
    ])
</form>

@include('admin.monedas.partials.overlay-recalculo')
@endsection
