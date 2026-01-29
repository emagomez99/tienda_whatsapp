@if($menuActual && $menuActual->tieneFiltros())
    @php
        $etiquetasFiltro = $menuActual->getEtiquetasFiltro();
        $filtrosRequeridos = $menuActual->filtros_requeridos;
    @endphp

    <div class="card mb-4" id="filtros-cascada">
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
            <span><i class="bi bi-funnel"></i> Filtrar productos</span>
            <div>
                <span class="badge bg-secondary" id="contador-resultados" style="display: none;">
                    <i class="bi bi-box"></i> <span id="total-productos">0</span> productos
                </span>
                <button type="button" class="btn btn-outline-secondary btn-sm ms-2" id="btn-limpiar" style="display: none;">
                    <i class="bi bi-x-circle"></i> Limpiar
                </button>
            </div>
        </div>
        <div class="card-body py-3">
            <div class="row g-3 align-items-end">
                @foreach($etiquetasFiltro as $index => $etiqueta)
                    <div class="col-md-4">
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
                            <option value="">{{ $filtrosRequeridos ? 'Seleccionar ' . $etiqueta->nombre . '...' : 'Todos' }}</option>
                        </select>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuId = {{ $menuActual->id }};
            const filtrosAplicados = @json($filtrosAplicados ?? []);
            const selectores = document.querySelectorAll('.filtro-select');
            const ordenEtiquetas = @json($etiquetasFiltro->pluck('id')->toArray());
            const filtrosRequeridos = {{ $filtrosRequeridos ? 'true' : 'false' }};
            const productosContainer = document.getElementById('productos-container');
            const contadorResultados = document.getElementById('contador-resultados');
            const totalProductos = document.getElementById('total-productos');
            const btnLimpiar = document.getElementById('btn-limpiar');
            const textoOpcionVacia = filtrosRequeridos ? 'Seleccionar...' : 'Todos';

            let cargaInicial = true;
            const cacheValores = {};
            let cargandoProductos = false;

            // Actualizar estado habilitado/deshabilitado de filtros (modo compuesto)
            function actualizarEstadoFiltros() {
                if (!filtrosRequeridos) return;

                let habilitarSiguiente = true;
                selectores.forEach((select, index) => {
                    if (index === 0) {
                        select.disabled = false;
                    } else {
                        select.disabled = !habilitarSiguiente;
                    }
                    if (!select.value) {
                        habilitarSiguiente = false;
                    }
                });
            }

            // Verificar si todos los filtros están completos
            function todosLosFiltrosCompletos() {
                for (const select of selectores) {
                    if (!select.value) return false;
                }
                return true;
            }

            // Verificar si hay algún filtro seleccionado
            function hayFiltrosSeleccionados() {
                for (const select of selectores) {
                    if (select.value) return true;
                }
                return false;
            }

            // Obtener filtros actuales como objeto
            function obtenerFiltrosActuales() {
                const filtros = {};
                selectores.forEach(select => {
                    if (select.value) {
                        filtros[select.dataset.etiquetaId] = select.value;
                    }
                });
                return filtros;
            }

            // Cargar productos via AJAX
            async function cargarProductos(pagina = 1) {
                if (cargandoProductos) return;

                // En modo compuesto, solo cargar si todos están completos
                if (filtrosRequeridos && !todosLosFiltrosCompletos()) {
                    productosContainer.innerHTML = `
                        <div class="alert alert-info">
                            <i class="bi bi-hand-index"></i> <strong>Selecciona los filtros</strong> para ver los productos disponibles.
                        </div>
                    `;
                    contadorResultados.style.display = 'none';
                    btnLimpiar.style.display = hayFiltrosSeleccionados() ? 'inline-block' : 'none';
                    return;
                }

                cargandoProductos = true;

                // Mostrar loading
                productosContainer.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2 text-muted">Buscando productos...</p>
                    </div>
                `;

                try {
                    const params = new URLSearchParams({ menu_id: menuId, page: pagina });
                    const filtros = obtenerFiltrosActuales();

                    Object.entries(filtros).forEach(([key, value]) => {
                        params.append(`filtros[${key}]`, value);
                    });

                    const response = await fetch(`/productos/ajax?${params.toString()}`);
                    const data = await response.json();

                    productosContainer.innerHTML = data.html;
                    totalProductos.textContent = data.total;
                    contadorResultados.style.display = 'inline-block';
                    btnLimpiar.style.display = 'inline-block';

                    // Actualizar URL sin recargar (para que el usuario pueda compartir/refrescar)
                    actualizarURL(filtros, pagina);

                    // Scroll al inicio del contenedor de productos
                    if (pagina > 1) {
                        productosContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }

                } catch (error) {
                    console.error('Error cargando productos:', error);
                    productosContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> Error al cargar productos. Intenta nuevamente.
                        </div>
                    `;
                } finally {
                    cargandoProductos = false;
                }
            }

            // Interceptar clics en paginación (event delegation)
            productosContainer.addEventListener('click', function(e) {
                const link = e.target.closest('.pagination a.page-link');
                if (link) {
                    e.preventDefault();
                    const url = new URL(link.href);
                    const pagina = url.searchParams.get('page') || 1;
                    cargarProductos(parseInt(pagina));
                }
            });

            // Actualizar URL con los filtros actuales
            function actualizarURL(filtros, pagina = 1) {
                const url = new URL(window.location);
                url.searchParams.set('menu', menuId);

                // Limpiar filtros anteriores y página
                ordenEtiquetas.forEach(id => {
                    url.searchParams.delete('f' + id);
                });
                url.searchParams.delete('page');

                // Agregar filtros actuales
                Object.entries(filtros).forEach(([key, value]) => {
                    url.searchParams.set('f' + key, value);
                });

                // Agregar página si no es la primera
                if (pagina > 1) {
                    url.searchParams.set('page', pagina);
                }

                window.history.replaceState({}, '', url);
            }

            // Generar clave de caché
            function generarClaveCache(etiquetaId, filtros) {
                return etiquetaId + ':' + JSON.stringify(filtros);
            }

            // Obtener filtros anteriores (para carga inicial)
            function obtenerFiltrosAnterioresInicial(etiquetaIdActual) {
                const filtros = {};
                const indexActual = ordenEtiquetas.indexOf(parseInt(etiquetaIdActual));
                ordenEtiquetas.forEach((etiquetaId, orden) => {
                    if (orden < indexActual && filtrosAplicados[etiquetaId]) {
                        filtros[etiquetaId] = filtrosAplicados[etiquetaId];
                    }
                });
                return filtros;
            }

            // Obtener filtros anteriores (para cambios del usuario)
            function obtenerFiltrosAnteriores(etiquetaIdActual) {
                const filtros = {};
                const indexActual = ordenEtiquetas.indexOf(parseInt(etiquetaIdActual));
                selectores.forEach(select => {
                    const orden = parseInt(select.dataset.orden);
                    if (orden < indexActual && select.value) {
                        filtros[select.dataset.etiquetaId] = select.value;
                    }
                });
                return filtros;
            }

            // Poblar select con valores
            function poblarSelect(selectElement, valores, valorActual) {
                const nombre = selectElement.dataset.nombre;
                const placeholder = filtrosRequeridos ? `Seleccionar ${nombre}...` : 'Todos';
                selectElement.innerHTML = `<option value="">${placeholder}</option>`;
                valores.forEach(valor => {
                    const option = document.createElement('option');
                    option.value = valor;
                    option.textContent = valor;
                    if (valor === valorActual) option.selected = true;
                    selectElement.appendChild(option);
                });
            }

            // Cargar valores de un filtro
            async function cargarValoresFiltro(etiquetaId, selectElement, soloSiCambia = false, forzarRecarga = false) {
                const filtros = cargaInicial ? obtenerFiltrosAnterioresInicial(etiquetaId) : obtenerFiltrosAnteriores(etiquetaId);
                const valorAplicado = filtrosAplicados[etiquetaId] || '';
                const claveCache = generarClaveCache(etiquetaId, filtros);

                // Mostrar valor aplicado inmediatamente
                if (cargaInicial && valorAplicado) {
                    poblarSelect(selectElement, [valorAplicado], valorAplicado);
                    selectElement.dataset.cargado = 'parcial';
                    selectElement.dataset.claveCache = claveCache;
                }

                if (soloSiCambia && cargaInicial && valorAplicado) return;

                // Usar caché si existe
                if (!forzarRecarga && cacheValores[claveCache]) {
                    poblarSelect(selectElement, cacheValores[claveCache], valorAplicado || selectElement.value || '');
                    selectElement.dataset.cargado = 'completo';
                    selectElement.dataset.claveCache = claveCache;
                    return;
                }

                try {
                    const params = new URLSearchParams({ menu_id: menuId, etiqueta_id: etiquetaId });
                    Object.entries(filtros).forEach(([key, value]) => {
                        params.append(`filtros[${key}]`, value);
                    });

                    const response = await fetch(`/filtros/valores?${params.toString()}`);
                    const valores = await response.json();

                    cacheValores[claveCache] = valores;
                    poblarSelect(selectElement, valores, valorAplicado || selectElement.value || '');
                    selectElement.dataset.cargado = 'completo';
                    selectElement.dataset.claveCache = claveCache;

                    if (filtrosRequeridos) {
                        actualizarEstadoFiltros();
                    }
                } catch (error) {
                    console.error('Error cargando filtros:', error);
                }
            }

            // Manejar cambio de filtro
            async function onFiltroChange(e) {
                const selectCambiado = e.target;
                const ordenCambiado = parseInt(selectCambiado.dataset.orden);

                if (filtrosRequeridos) {
                    // Modo compuesto: limpiar filtros siguientes y solo cargar el inmediato siguiente
                    let siguienteCargado = false;

                    for (const select of selectores) {
                        const orden = parseInt(select.dataset.orden);
                        if (orden > ordenCambiado) {
                            select.value = '';
                            select.dataset.cargado = '';

                            if (!selectCambiado.value) {
                                // Si se limpió el filtro, deshabilitar los siguientes
                                select.disabled = true;
                            } else if (!siguienteCargado) {
                                // Solo cargar el siguiente filtro inmediato
                                select.disabled = false;
                                await cargarValoresFiltro(select.dataset.etiquetaId, select, false, true);
                                siguienteCargado = true;
                            } else {
                                // Los demás quedan deshabilitados hasta que se seleccione el anterior
                                select.disabled = true;
                            }
                        }
                    }

                    actualizarEstadoFiltros();
                } else {
                    // Modo individual: cargar todos los filtros siguientes
                    for (const select of selectores) {
                        const orden = parseInt(select.dataset.orden);
                        if (orden > ordenCambiado) {
                            select.value = '';
                            select.dataset.cargado = '';
                            await cargarValoresFiltro(select.dataset.etiquetaId, select, false, true);
                        }
                    }
                }

                // Cargar productos automáticamente
                cargarProductos();
            }

            // Cargar opciones al hacer foco
            async function onFiltroFocus(e) {
                const select = e.target;
                if (select.dataset.cargado !== 'completo' && !select.disabled) {
                    await cargarValoresFiltro(select.dataset.etiquetaId, select, false, false);
                }
            }

            // Agregar listeners
            selectores.forEach(select => {
                select.addEventListener('change', onFiltroChange);
                select.addEventListener('focus', onFiltroFocus);
                select.addEventListener('mousedown', onFiltroFocus);
            });

            // Manejar clic en botón Limpiar
            btnLimpiar.addEventListener('click', async function() {
                // Limpiar todos los selects
                selectores.forEach((select, index) => {
                    select.value = '';
                    select.dataset.cargado = '';

                    if (filtrosRequeridos) {
                        // Modo compuesto: deshabilitar todos excepto el primero
                        select.disabled = index > 0;
                    }
                });

                // Limpiar URL
                const url = new URL(window.location);
                ordenEtiquetas.forEach(id => {
                    url.searchParams.delete('f' + id);
                });
                window.history.replaceState({}, '', url);

                // Ocultar contador y botón limpiar
                contadorResultados.style.display = 'none';
                btnLimpiar.style.display = 'none';

                if (filtrosRequeridos) {
                    // Modo compuesto: mostrar mensaje y recargar primer filtro
                    productosContainer.innerHTML = `
                        <div class="alert alert-info">
                            <i class="bi bi-hand-index"></i> <strong>Selecciona los filtros</strong> para ver los productos disponibles.
                        </div>
                    `;
                    const primerSelect = selectores[0];
                    if (primerSelect) {
                        await cargarValoresFiltro(primerSelect.dataset.etiquetaId, primerSelect, false, true);
                    }
                } else {
                    // Modo individual: recargar todos los filtros y productos sin filtros
                    for (const select of selectores) {
                        await cargarValoresFiltro(select.dataset.etiquetaId, select, false, true);
                    }
                    cargarProductos();
                }
            });

            // Inicialización
            async function inicializar() {
                const hayFiltrosURL = Object.keys(filtrosAplicados).length > 0;

                if (filtrosRequeridos) {
                    // Modo compuesto: deshabilitar todos excepto el primero
                    selectores.forEach((select, index) => {
                        if (index > 0) select.disabled = true;
                    });

                    if (hayFiltrosURL) {
                        // Si hay filtros en URL, cargar solo los valores aplicados (sin AJAX extra)
                        for (const select of selectores) {
                            const etiquetaId = select.dataset.etiquetaId;
                            if (filtrosAplicados[etiquetaId]) {
                                poblarSelect(select, [filtrosAplicados[etiquetaId]], filtrosAplicados[etiquetaId]);
                                select.dataset.cargado = 'parcial';
                            }
                        }
                        btnLimpiar.style.display = 'inline-block';
                    } else {
                        // Sin filtros: solo cargar el primer filtro
                        const primerSelect = selectores[0];
                        if (primerSelect) {
                            await cargarValoresFiltro(primerSelect.dataset.etiquetaId, primerSelect, false, false);
                        }
                    }

                    actualizarEstadoFiltros();
                } else {
                    // Modo individual: cargar todos los filtros
                    for (const select of selectores) {
                        await cargarValoresFiltro(select.dataset.etiquetaId, select, hayFiltrosURL);
                    }

                    if (hayFiltrosURL) {
                        btnLimpiar.style.display = 'inline-block';
                    }
                }

                cargaInicial = false;
            }

            inicializar();
        });
    </script>
    @endpush
@endif
