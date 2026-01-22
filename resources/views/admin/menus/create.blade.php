@extends('layouts.admin')

@section('title', 'Nuevo Ítem de Menú')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-plus-circle"></i> Nuevo Ítem de Menú</h2>
    <a href="{{ route('admin.menus.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form action="{{ route('admin.menus.store') }}" method="POST">
    @csrf
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Información del Menú</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="nombre" class="form-label">Nombre del Menú *</label>
                            <input type="text" class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre" value="{{ old('nombre') }}" required>
                            @error('nombre')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Texto que se mostrará en el menú</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="orden" class="form-label">Orden</label>
                            <input type="number" class="form-control @error('orden') is-invalid @enderror" id="orden" name="orden" value="{{ old('orden', 0) }}" min="0">
                            @error('orden')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Menú Padre</label>
                        <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                            <option value="">-- Menú Principal (raíz) --</option>
                            @foreach($menusParent as $menuParent)
                                <option value="{{ $menuParent->id }}" {{ old('parent_id') == $menuParent->id ? 'selected' : '' }}>
                                    {{ str_repeat('— ', $menuParent->parent_id ? 1 : 0) }}{{ $menuParent->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Deja vacío para crear un menú de primer nivel</small>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label">Tipo de Enlace *</label>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_enlace" id="tipo_ninguno" value="ninguno" {{ old('tipo_enlace', 'ninguno') == 'ninguno' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="tipo_ninguno">
                                        <span class="badge bg-secondary">Contenedor</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_enlace" id="tipo_proveedor" value="proveedor" {{ old('tipo_enlace') == 'proveedor' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="tipo_proveedor">
                                        <span class="badge bg-primary">Proveedor</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_enlace" id="tipo_categoria" value="categoria" {{ old('tipo_enlace') == 'categoria' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="tipo_categoria">
                                        <span class="badge bg-success">Categoría</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_enlace" id="tipo_especificacion" value="especificacion" {{ old('tipo_enlace') == 'especificacion' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="tipo_especificacion">
                                        <span class="badge bg-info">Especificación</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Campos dinámicos según tipo -->
                    <div id="campos-proveedor" class="campos-tipo" style="display: none;">
                        <div class="mb-3">
                            <label for="proveedor_id" class="form-label">Seleccionar Proveedor</label>
                            <select class="form-select" id="proveedor_id" name="enlace_id">
                                <option value="">Seleccionar...</option>
                                @foreach($proveedores as $proveedor)
                                    <option value="{{ $proveedor->id }}" {{ old('enlace_id') == $proveedor->id ? 'selected' : '' }}>
                                        {{ $proveedor->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div id="campos-categoria" class="campos-tipo" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="categoria_id" class="form-label">Seleccionar Categoría</label>
                                <select class="form-select" id="categoria_id">
                                    <option value="">Seleccionar...</option>
                                    @foreach($categorias as $categoria)
                                        <option value="{{ $categoria->id }}" {{ old('enlace_id') == $categoria->id ? 'selected' : '' }}>
                                            {{ $categoria->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="enlace_id" id="categoria_enlace_id" value="{{ old('enlace_id') }}">
                            </div>
                            <div class="col-md-6 mb-3 position-relative">
                                <label for="categoria_valor" class="form-label">Valor (opcional)</label>
                                <input type="text" class="form-control" id="categoria_valor" name="enlace_valor" value="{{ old('enlace_valor') }}" placeholder="Ej: Gomitas, Arcor..." autocomplete="off">
                                <div class="autocomplete-suggestions list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                                <small class="text-muted">Filtra por un valor específico de la categoría</small>
                            </div>
                        </div>
                    </div>

                    <div id="campos-especificacion" class="campos-tipo" style="display: none;">
                        <div class="mb-3">
                            <label for="especificacion_valor" class="form-label">Valor de Especificación</label>
                            <input type="text" class="form-control" id="especificacion_valor" placeholder="Ej: 1kg, Rojo, Grande...">
                            <input type="hidden" name="enlace_valor" id="especificacion_enlace_valor" value="{{ old('enlace_valor') }}">
                            <small class="text-muted">Filtra productos que tengan este valor en cualquier especificación</small>
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="activo">Menú activo</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-circle"></i> Guardar Menú
                    </button>
                    <a href="{{ route('admin.menus.index') }}" class="btn btn-outline-secondary w-100">
                        Cancelar
                    </a>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header bg-light">
                    <i class="bi bi-lightbulb"></i> Ayuda
                </div>
                <div class="card-body small">
                    <p><strong>Contenedor:</strong> Agrupa otros menús, no filtra productos.</p>
                    <p><strong>Proveedor:</strong> Muestra productos de un proveedor específico.</p>
                    <p><strong>Categoría:</strong> Filtra por categoría. Opcionalmente especifica un valor.</p>
                    <p><strong>Especificación:</strong> Filtra por valor de especificación.</p>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('styles')
<style>
    .autocomplete-suggestions .list-group-item {
        cursor: pointer;
        padding: 0.5rem 0.75rem;
    }
    .autocomplete-suggestions .list-group-item:hover {
        background-color: #e9ecef;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tipoRadios = document.querySelectorAll('input[name="tipo_enlace"]');
        const camposTipo = document.querySelectorAll('.campos-tipo');

        function mostrarCampos() {
            const tipoSeleccionado = document.querySelector('input[name="tipo_enlace"]:checked').value;

            camposTipo.forEach(campo => campo.style.display = 'none');

            if (tipoSeleccionado === 'proveedor') {
                document.getElementById('campos-proveedor').style.display = 'block';
            } else if (tipoSeleccionado === 'categoria') {
                document.getElementById('campos-categoria').style.display = 'block';
            } else if (tipoSeleccionado === 'especificacion') {
                document.getElementById('campos-especificacion').style.display = 'block';
            }
        }

        tipoRadios.forEach(radio => {
            radio.addEventListener('change', mostrarCampos);
        });

        mostrarCampos();

        // Sincronizar categoria_id con enlace_id
        const categoriaSelect = document.getElementById('categoria_id');
        const categoriaEnlaceId = document.getElementById('categoria_enlace_id');

        categoriaSelect.addEventListener('change', function() {
            categoriaEnlaceId.value = this.value;
        });

        // Sincronizar especificacion_valor con enlace_valor
        const especificacionInput = document.getElementById('especificacion_valor');
        const especificacionEnlaceValor = document.getElementById('especificacion_enlace_valor');

        especificacionInput.addEventListener('input', function() {
            especificacionEnlaceValor.value = this.value;
        });

        // Autocompletado para valores de categoría
        let debounceTimer;
        const categoriaValorInput = document.getElementById('categoria_valor');
        const suggestionsDiv = categoriaValorInput.parentElement.querySelector('.autocomplete-suggestions');

        categoriaValorInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const valor = this.value;
            const categoriaId = categoriaSelect.value;

            if (!categoriaId || valor.length < 2) {
                suggestionsDiv.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`/admin/menus/categoria/${categoriaId}/valores?q=${encodeURIComponent(valor)}`)
                    .then(response => response.json())
                    .then(data => {
                        suggestionsDiv.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement('a');
                                div.href = '#';
                                div.className = 'list-group-item list-group-item-action';
                                div.textContent = item;
                                div.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    categoriaValorInput.value = item;
                                    suggestionsDiv.style.display = 'none';
                                });
                                suggestionsDiv.appendChild(div);
                            });
                            suggestionsDiv.style.display = 'block';
                        } else {
                            suggestionsDiv.style.display = 'none';
                        }
                    });
            }, 300);
        });

        categoriaValorInput.addEventListener('blur', function() {
            setTimeout(() => {
                suggestionsDiv.style.display = 'none';
            }, 200);
        });
    });
</script>
@endpush
