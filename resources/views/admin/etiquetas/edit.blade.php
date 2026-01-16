@extends('layouts.admin')

@section('title', 'Editar Etiqueta')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-pencil"></i> Editar Etiqueta</h2>
    <a href="{{ route('admin.etiquetas.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.etiquetas.update', $etiqueta) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre" value="{{ old('nombre', $etiqueta->nombre) }}" placeholder="Ej: Categoria, Modelo, Marca" required>
                        @error('nombre')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Este es el tipo de etiqueta (ej: Categoria). El valor se asigna por producto.</div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="visible_usuarios" name="visible_usuarios" value="1" {{ old('visible_usuarios', $etiqueta->visible_usuarios) ? 'checked' : '' }}>
                            <label class="form-check-label" for="visible_usuarios">
                                Visible para usuarios
                            </label>
                        </div>
                        <div class="form-text">Si esta marcado, los usuarios podran ver esta etiqueta en la tienda.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Actualizar Etiqueta
                        </button>
                        <a href="{{ route('admin.etiquetas.index') }}" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
