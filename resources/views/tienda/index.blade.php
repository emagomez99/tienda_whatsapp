@extends('layouts.app')

@section('title', 'Tienda')

@php $menuEnSidebar = App\Models\Configuracion::menuEnSidebar(); @endphp

@section('content')
@if(!$menuEnSidebar)
<div class="container py-4">
@endif
    <div class="row mb-4">
        <div class="col-12">
            <div class="row g-3">
                <div class="{{ $menuEnSidebar ? 'col-12' : 'col-md-10' }}">
                    <div class="input-group">
                        <input type="text"
                               id="buscar-productos"
                               class="form-control"
                               placeholder="Buscar en productos filtrados..."
                               value="{{ request('buscar') }}"
                               autocomplete="off">
                        <button class="btn btn-primary" type="button" id="btn-buscar">
                            <i class="bi bi-search"></i>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" id="btn-limpiar-busqueda" style="display: none;">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <small class="text-muted" id="busqueda-contexto" style="display: none;">
                        <i class="bi bi-info-circle"></i> Buscando dentro de los productos filtrados
                    </small>
                </div>
                @if(!$menuEnSidebar)
                <div class="col-md-2">
                    @if(!request('proveedor') && !request('etiqueta_valor') && !request('especificacion') && !($menuActual && $menuActual->tieneFiltros()))
                        <select name="etiqueta" id="filtro-etiqueta-simple" class="form-select">
                            <option value="">Filtrar por etiqueta</option>
                            @foreach($etiquetas as $etiqueta)
                                <option value="{{ $etiqueta->id }}" {{ request('etiqueta') == $etiqueta->id ? 'selected' : '' }}>
                                    {{ $etiqueta->nombre }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Filtros en cascada si el menú los tiene configurados --}}
    @include('components.filtros-cascada', ['menuActual' => $menuActual ?? null, 'filtrosAplicados' => $filtrosAplicados ?? []])

    {{-- Contenedor de productos (actualizable via AJAX) --}}
    <div id="productos-container">
        @if(isset($filtrosIncompletos) && $filtrosIncompletos)
            <div class="alert alert-info" id="mensaje-filtros-pendientes">
                <i class="bi bi-hand-index"></i> <strong>Selecciona los filtros</strong> para ver los productos disponibles.
            </div>
        @else
            @include('tienda.partials.productos-grid', ['productos' => $productos, 'mostrarPrecios' => $mostrarPrecios, 'menuEnSidebar' => $menuEnSidebar])
        @endif
    </div>
@if(!$menuEnSidebar)
</div>
@endif

@if(!$menuActual || !$menuActual->tieneFiltros())
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputBuscar = document.getElementById('buscar-productos');
        const btnBuscar = document.getElementById('btn-buscar');
        const btnLimpiarBusqueda = document.getElementById('btn-limpiar-busqueda');
        const productosContainer = document.getElementById('productos-container');

        if (!inputBuscar) return;

        let debounceTimer = null;
        let cargando = false;

        function obtenerParametrosURL() {
            const url = new URL(window.location);
            return {
                menu: url.searchParams.get('menu'),
                proveedor: url.searchParams.get('proveedor'),
                etiqueta: url.searchParams.get('etiqueta'),
                etiqueta_valor: url.searchParams.get('etiqueta_valor'),
                especificacion: url.searchParams.get('especificacion')
            };
        }

        async function buscarProductos() {
            if (cargando) return;
            cargando = true;

            const busqueda = inputBuscar.value.trim();
            const params = new URLSearchParams();
            const urlParams = obtenerParametrosURL();

            // Agregar parámetros existentes
            if (urlParams.menu) params.append('menu_id', urlParams.menu);
            if (urlParams.proveedor) params.append('proveedor', urlParams.proveedor);
            if (urlParams.etiqueta) params.append('etiqueta', urlParams.etiqueta);
            if (urlParams.etiqueta_valor) params.append('etiqueta_valor', urlParams.etiqueta_valor);
            if (urlParams.especificacion) params.append('especificacion', urlParams.especificacion);
            if (busqueda) params.append('buscar', busqueda);

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
                const response = await fetch(`/productos/ajax?${params.toString()}`);
                const data = await response.json();
                productosContainer.innerHTML = data.html;

                // Actualizar URL
                const url = new URL(window.location);
                url.searchParams.delete('buscar');
                if (busqueda) {
                    url.searchParams.set('buscar', busqueda);
                }
                window.history.replaceState({}, '', url);

                // Mostrar/ocultar botón limpiar
                if (btnLimpiarBusqueda) {
                    btnLimpiarBusqueda.style.display = busqueda ? 'block' : 'none';
                }

            } catch (error) {
                console.error('Error buscando productos:', error);
                productosContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> Error al buscar productos.
                    </div>
                `;
            } finally {
                cargando = false;
            }
        }

        // Debounce al escribir
        inputBuscar.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(buscarProductos, 400);
        });

        // Enter para buscar
        inputBuscar.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimer);
                buscarProductos();
            }
        });

        // Botón buscar
        if (btnBuscar) {
            btnBuscar.addEventListener('click', function() {
                clearTimeout(debounceTimer);
                buscarProductos();
            });
        }

        // Botón limpiar
        if (btnLimpiarBusqueda) {
            btnLimpiarBusqueda.addEventListener('click', function() {
                inputBuscar.value = '';
                btnLimpiarBusqueda.style.display = 'none';
                buscarProductos();
            });

            // Mostrar si ya hay búsqueda
            if (inputBuscar.value.trim()) {
                btnLimpiarBusqueda.style.display = 'block';
            }
        }

        // Interceptar paginación
        productosContainer.addEventListener('click', function(e) {
            const link = e.target.closest('.pagination a.page-link');
            if (link) {
                e.preventDefault();
                const url = new URL(link.href);
                const page = url.searchParams.get('page') || 1;

                const params = new URLSearchParams();
                const urlParams = obtenerParametrosURL();
                const busqueda = inputBuscar.value.trim();

                if (urlParams.menu) params.append('menu_id', urlParams.menu);
                if (urlParams.proveedor) params.append('proveedor', urlParams.proveedor);
                if (urlParams.etiqueta) params.append('etiqueta', urlParams.etiqueta);
                if (urlParams.etiqueta_valor) params.append('etiqueta_valor', urlParams.etiqueta_valor);
                if (urlParams.especificacion) params.append('especificacion', urlParams.especificacion);
                if (busqueda) params.append('buscar', busqueda);
                params.append('page', page);

                productosContainer.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                `;

                fetch(`/productos/ajax?${params.toString()}`)
                    .then(r => r.json())
                    .then(data => {
                        productosContainer.innerHTML = data.html;
                        productosContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
            }
        });
    });
</script>
@endpush
@endif
@endsection
