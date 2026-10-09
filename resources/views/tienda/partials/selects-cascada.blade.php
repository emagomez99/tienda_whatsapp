{{--
    Los selects de los filtros del menú (Año, Forma, Marca…). Los obligatorios van a la
    vista (components/filtros-cascada); los opcionales, en el panel "Filtrar"
    (tienda/partials/barra-refinar). El script de components/filtros-cascada los
    maneja igual en los dos lugares: los busca por .filtro-select.
--}}
            <div class="row g-2 align-items-end">
                @foreach($etiquetasFiltro as $index => $etiqueta)
                    <div class="{{ ($enPanel ?? false) ? 'col-12' : 'col-6 col-md-4' }}">
                        <label for="filtro_{{ $etiqueta->id }}" class="form-label small fw-bold mb-1">
                            {{ $etiqueta->nombre }}
                            @if($filtrosRequeridos)
                                <span class="text-danger">*</span>
                            @endif
                        </label>
                        <select class="form-select form-select-sm filtro-select"
                                id="filtro_{{ $etiqueta->id }}"
                                data-etiqueta-id="{{ $etiqueta->id }}"
                                data-orden="{{ $index }}"
                                data-nombre="{{ $etiqueta->nombre }}">
                            @php
                                $mostrarTodosEst = $filtrosConfig[(string)$etiqueta->id] ?? true;
                                $opcionTexto = $mostrarTodosEst ? 'Todos' : 'Seleccionar ' . $etiqueta->nombre . '...';
                                $opcionDisabled = !$mostrarTodosEst && !$filtrosRequeridos;
                            @endphp
                            <option value="" {{ $opcionDisabled ? 'disabled' : '' }}>{{ $opcionTexto }}</option>
                        </select>
                    </div>
                @endforeach
            </div>
