@extends('layouts.admin')

@section('title', 'Editar Ítem de Menú')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-pencil"></i> Editar Ítem de Menú</h2>
    <a href="{{ route('admin.menus.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form action="{{ route('admin.menus.update', $menu) }}" method="POST" id="menu-form">
    @csrf
    @method('PUT')
    <input type="hidden" name="enlace_id" id="enlace_id" value="{{ old('enlace_id', $menu->enlace_id) }}">
    <input type="hidden" name="enlace_valor" id="enlace_valor" value="{{ old('enlace_valor', $menu->enlace_valor) }}">

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
                            <input type="text" class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre" value="{{ old('nombre', $menu->nombre) }}" required>
                            @error('nombre')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Texto que se mostrará en el menú</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="orden" class="form-label">Orden</label>
                            <input type="number" class="form-control @error('orden') is-invalid @enderror" id="orden" name="orden" value="{{ old('orden', $menu->orden) }}" min="0">
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
                                <option value="{{ $menuParent->id }}" {{ old('parent_id', $menu->parent_id) == $menuParent->id ? 'selected' : '' }}>
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
                                    <input class="form-check-input" type="radio" name="tipo_enlace" id="tipo_ninguno" value="ninguno" {{ old('tipo_enlace', $menu->tipo_enlace) == 'ninguno' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="tipo_ninguno">
                                        <span class="badge bg-secondary">Contenedor</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_enlace" id="tipo_proveedor" value="proveedor" {{ old('tipo_enlace', $menu->tipo_enlace) == 'proveedor' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="tipo_proveedor">
                                        <span class="badge bg-primary">Proveedor</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_enlace" id="tipo_etiqueta" value="etiqueta" {{ old('tipo_enlace', $menu->tipo_enlace) == 'etiqueta' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="tipo_etiqueta">
                                        <span class="badge bg-success">Etiqueta</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_enlace" id="tipo_especificacion" value="especificacion" {{ old('tipo_enlace', $menu->tipo_enlace) == 'especificacion' ? 'checked' : '' }}>
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
                            <label for="proveedor_select" class="form-label">Seleccionar Proveedor</label>
                            <select class="form-select" id="proveedor_select">
                                <option value="">Seleccionar...</option>
                                @foreach($proveedores as $proveedor)
                                    <option value="{{ $proveedor->id }}" {{ old('enlace_id', $menu->tipo_enlace == 'proveedor' ? $menu->enlace_id : '') == $proveedor->id ? 'selected' : '' }}>
                                        {{ $proveedor->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div id="campos-etiqueta" class="campos-tipo" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="etiqueta_select" class="form-label">Seleccionar Etiqueta</label>
                                <select class="form-select" id="etiqueta_select">
                                    <option value="">Seleccionar...</option>
                                    @foreach($etiquetas as $etiqueta)
                                        <option value="{{ $etiqueta->id }}" {{ old('enlace_id', $menu->tipo_enlace == 'etiqueta' ? $menu->enlace_id : '') == $etiqueta->id ? 'selected' : '' }}>
                                            {{ $etiqueta->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3 position-relative">
                                <label for="etiqueta_valor_input" class="form-label">Valor (opcional)</label>
                                <input type="text" class="form-control" id="etiqueta_valor_input" value="{{ old('enlace_valor', $menu->tipo_enlace == 'etiqueta' ? $menu->enlace_valor : '') }}" placeholder="Ej: Gomitas, Arcor..." autocomplete="off">
                                <div class="autocomplete-suggestions list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                                <small class="text-muted">Filtra por un valor específico de la etiqueta</small>
                            </div>
                        </div>
                    </div>

                    <div id="campos-especificacion" class="campos-tipo" style="display: none;">
                        <div class="mb-3">
                            <label for="especificacion_valor_input" class="form-label">Valor de Especificación</label>
                            <input type="text" class="form-control" id="especificacion_valor_input" value="{{ old('enlace_valor', $menu->tipo_enlace == 'especificacion' ? $menu->enlace_valor : '') }}" placeholder="Ej: 1kg, Rojo, Grande...">
                            <small class="text-muted">Filtra productos que tengan este valor en cualquier especificación</small>
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" {{ old('activo', $menu->activo) ? 'checked' : '' }}>
                        <label class="form-check-label" for="activo">Menú activo</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-circle"></i> Actualizar Menú
                    </button>
                    <a href="{{ route('admin.menus.index') }}" class="btn btn-outline-secondary w-100">
                        Cancelar
                    </a>
                </div>
            </div>

            @if($menu->children->count() > 0)
            <div class="card mt-3">
                <div class="card-header bg-light">
                    <i class="bi bi-diagram-3"></i> Submenús ({{ $menu->children->count() }})
                </div>
                <ul class="list-group list-group-flush">
                    @foreach($menu->children as $child)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ $child->nombre }}
                            <a href="{{ route('admin.menus.edit', $child) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="card mt-3">
                <div class="card-header bg-light">
                    <i class="bi bi-lightbulb"></i> Ayuda
                </div>
                <div class="card-body small">
                    <p><strong>Contenedor:</strong> Agrupa otros menús, no filtra productos.</p>
                    <p><strong>Proveedor:</strong> Muestra productos de un proveedor específico.</p>
                    <p><strong>Etiqueta:</strong> Filtra por etiqueta. Opcionalmente especifica un valor.</p>
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
        const enlaceIdHidden = document.getElementById('enlace_id');
        const enlaceValorHidden = document.getElementById('enlace_valor');

        // Selectores de cada tipo
        const proveedorSelect = document.getElementById('proveedor_select');
        const etiquetaSelect = document.getElementById('etiqueta_select');
        const etiquetaValorInput = document.getElementById('etiqueta_valor_input');
        const especificacionValorInput = document.getElementById('especificacion_valor_input');

        function actualizarCamposOcultos() {
            const tipoSeleccionado = document.querySelector('input[name="tipo_enlace"]:checked').value;

            // Limpiar valores
            enlaceIdHidden.value = '';
            enlaceValorHidden.value = '';

            if (tipoSeleccionado === 'proveedor') {
                enlaceIdHidden.value = proveedorSelect.value;
            } else if (tipoSeleccionado === 'etiqueta') {
                enlaceIdHidden.value = etiquetaSelect.value;
                enlaceValorHidden.value = etiquetaValorInput.value;
            } else if (tipoSeleccionado === 'especificacion') {
                enlaceValorHidden.value = especificacionValorInput.value;
            }
        }

        function mostrarCampos() {
            const tipoSeleccionado = document.querySelector('input[name="tipo_enlace"]:checked').value;

            camposTipo.forEach(campo => campo.style.display = 'none');

            if (tipoSeleccionado === 'proveedor') {
                document.getElementById('campos-proveedor').style.display = 'block';
            } else if (tipoSeleccionado === 'etiqueta') {
                document.getElementById('campos-etiqueta').style.display = 'block';
            } else if (tipoSeleccionado === 'especificacion') {
                document.getElementById('campos-especificacion').style.display = 'block';
            }

            actualizarCamposOcultos();
        }

        tipoRadios.forEach(radio => {
            radio.addEventListener('change', mostrarCampos);
        });

        // Eventos para actualizar campos ocultos
        proveedorSelect.addEventListener('change', actualizarCamposOcultos);
        etiquetaSelect.addEventListener('change', actualizarCamposOcultos);
        etiquetaValorInput.addEventListener('input', actualizarCamposOcultos);
        especificacionValorInput.addEventListener('input', actualizarCamposOcultos);

        // Actualizar antes de enviar el formulario
        document.getElementById('menu-form').addEventListener('submit', function() {
            actualizarCamposOcultos();
        });

        mostrarCampos();

        // Autocompletado para valores de etiqueta
        let debounceTimer;
        const suggestionsDiv = etiquetaValorInput.parentElement.querySelector('.autocomplete-suggestions');

        etiquetaValorInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const valor = this.value;
            const etiquetaId = etiquetaSelect.value;

            if (!etiquetaId || valor.length < 2) {
                suggestionsDiv.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`/admin/menus/etiqueta/${etiquetaId}/valores?q=${encodeURIComponent(valor)}`)
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
                                    etiquetaValorInput.value = item;
                                    actualizarCamposOcultos();
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

        etiquetaValorInput.addEventListener('blur', function() {
            setTimeout(() => {
                suggestionsDiv.style.display = 'none';
            }, 200);
        });
    });
</script>
@endpush
