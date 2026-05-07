@extends('layouts.admin')

@section('title', 'Nueva Etiqueta')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-plus-circle"></i> Nueva Etiqueta</h3>
    <a href="{{ route('admin.etiquetas.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form action="{{ route('admin.etiquetas.store') }}" method="POST">
    @csrf
    <div class="row">
        <div class="col-md-{{ $proveedores->isNotEmpty() ? '8' : '6' }}">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Información</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control @error('nombre') is-invalid @enderror"
                               id="nombre" name="nombre" value="{{ old('nombre') }}"
                               placeholder="Ej: Categoria, Modelo, Marca" required>
                        @error('nombre')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Tipo de etiqueta. El valor se asigna por producto.</div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="visible_usuarios"
                                   name="visible_usuarios" value="1" {{ old('visible_usuarios', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="visible_usuarios">Visible para usuarios</label>
                        </div>
                        <div class="form-text">Si está marcado, los usuarios podrán ver esta etiqueta en la tienda.</div>
                    </div>
                </div>
            </div>

            @if($proveedores->isNotEmpty())
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Proveedores</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Proveedor</th>
                                <th class="text-center" style="width:260px">Aplica</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($proveedores as $proveedor)
                                @php $oldVal = old("proveedores_config.{$proveedor->id}", 'no'); @endphp
                                <tr>
                                    <td class="ps-3">{{ $proveedor->nombre }}</td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <input type="radio" class="btn-check"
                                                   name="proveedores_config[{{ $proveedor->id }}]"
                                                   id="pc_{{ $proveedor->id }}_no"
                                                   value="no" {{ $oldVal === 'no' ? 'checked' : '' }}>
                                            <label class="btn btn-outline-secondary" for="pc_{{ $proveedor->id }}_no">No aplica</label>

                                            <input type="radio" class="btn-check"
                                                   name="proveedores_config[{{ $proveedor->id }}]"
                                                   id="pc_{{ $proveedor->id }}_opc"
                                                   value="opcional" {{ $oldVal === 'opcional' ? 'checked' : '' }}>
                                            <label class="btn btn-outline-primary" for="pc_{{ $proveedor->id }}_opc">Opcional</label>

                                            <input type="radio" class="btn-check"
                                                   name="proveedores_config[{{ $proveedor->id }}]"
                                                   id="pc_{{ $proveedor->id }}_obl"
                                                   value="obligatoria" {{ $oldVal === 'obligatoria' ? 'checked' : '' }}>
                                            <label class="btn btn-outline-danger" for="pc_{{ $proveedor->id }}_obl">Obligatoria</label>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Guardar Etiqueta
                </button>
                <a href="{{ route('admin.etiquetas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>
@endsection
