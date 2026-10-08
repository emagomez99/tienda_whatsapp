@extends('layouts.admin')

@section('title', 'Nuevo proveedor')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-plus-circle"></i> Nuevo proveedor</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-proveedores" titulo="Cómo funcionan los proveedores">
            @include('admin.ayudas.proveedores')
        </x-ayuda>
        <a href="{{ route('admin.proveedores.index') }}" class="btn btn-outline-secondary" title="Volver">
            <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
        </a>
    </div>
</div>

<form action="{{ route('admin.proveedores.store') }}" method="POST" id="form-proveedor">
    @csrf
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre" value="{{ old('nombre') }}" required>
                        @error('nombre')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="prefijo" class="form-label">Letras para los códigos</label>
                        <input type="text" class="form-control text-uppercase @error('prefijo') is-invalid @enderror" id="prefijo" name="prefijo" value="{{ old('prefijo') }}" placeholder="Ej: COCA" maxlength="20" autocapitalize="characters">
                        @error('prefijo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Opcional. Al cargar un producto de este proveedor, el botón <i class="bi bi-arrow-clockwise"></i> le inventa un código que empieza con estas letras (ej: COCA-482913).</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="contacto" class="form-label">Contacto</label>
                        <input type="text" class="form-control @error('contacto') is-invalid @enderror" id="contacto" name="contacto" value="{{ old('contacto') }}">
                        @error('contacto')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        @include('partials.intl-tel-input', ['inputId' => 'telefono-input', 'fieldName' => 'telefono', 'value' => ''])
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="notas" class="form-label">Notas</label>
                    <textarea class="form-control @error('notas') is-invalid @enderror" id="notas" name="notas" rows="3">{{ old('notas') }}</textarea>
                    @error('notas')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="activo">Proveedor activo</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.barra-guardar', [
    'textoGuardar' => 'Crear proveedor',
    'cancelarUrl'  => route('admin.proveedores.index'),
    'formId'       => 'form-proveedor',
])
</form>
@endsection
