@extends('layouts.app')

{{-- El (string) no es decorativo: con null, @section no lo toma como valor sino como
     el comienzo de una sección que nunca se cierra, y deja un buffer de salida abierto
     en cada página de un menú sin meta description. --}}
@php $categoriaActual = $categoriaActual ?? null; @endphp
@if($categoriaActual)
    @section('title', $categoriaActual->tituloParaBuscadores())
    @section('meta_description', $categoriaActual->descripcionParaBuscadores())
    @section('meta_canonical', $categoriaActual->url())
@else
    @section('title', (string) (isset($menuActual) && $menuActual ? $menuActual->meta_title : ''))
    @section('meta_description', (string) (isset($menuActual) && $menuActual ? $menuActual->meta_description : ''))
@endif

@php $menuEnSidebar = App\Models\Configuracion::menuEnSidebar(); @endphp

@if(isset($menuActual) && $menuActual)
@push('schema')
<script type="application/ld+json">{!! App\Services\SeoService::jsonLd(App\Services\SeoService::breadcrumbSchema([
    ['name' => 'Inicio', 'url' => route('tienda.index')],
    ['name' => $menuActual->nombre, 'url' => url()->current()],
])) !!}</script>
@endpush
@endif

@if($categoriaActual)
@php
    $migas = array_map(function ($c) { return ['name' => $c->nombre, 'url' => $c->url()]; }, array_reverse($categoriaActual->linaje()));
@endphp
@push('schema')
<script type="application/ld+json">{!! App\Services\SeoService::jsonLd(App\Services\SeoService::breadcrumbSchema(
    array_merge([['name' => 'Inicio', 'url' => route('tienda.index')]], $migas)
)) !!}</script>
@endpush
@endif

@section('content')
@if(!$menuEnSidebar)
<div class="container py-4">
@endif
    @if($categoriaActual)
        <nav aria-label="Ruta" class="migas mb-1">
            <a href="{{ route('tienda.index') }}">Inicio</a>
            @foreach($migas as $miga)
                <span class="migas-separador">›</span>
                @if($loop->last)
                    <span aria-current="page">{{ $miga['name'] }}</span>
                @else
                    <a href="{{ $miga['url'] }}">{{ $miga['name'] }}</a>
                @endif
            @endforeach
        </nav>
        <h1 class="h3 tienda-titulo mb-3">{{ $categoriaActual->nombre }}</h1>
        @if($subcategorias->isNotEmpty())
            <div class="subcategorias d-flex flex-wrap gap-2 mb-3">
                @foreach($subcategorias as $sub)
                    <a href="{{ $sub->url() }}" class="btn btn-sm btn-outline-secondary rounded-pill">{{ $sub->nombre }}</a>
                @endforeach
            </div>
        @endif
    @endif

    {{-- Buscador, Ordenar por y Filtrar (opcionales, precio, stock), en una fila: en todas las páginas del catálogo. --}}
    @include('tienda.partials.barra-refinar')

    {{-- Filtros en cascada si el menú los tiene configurados (los obligatorios, a la vista) --}}
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

@push('scripts')
<script>
// +/- del grid (delegado para funcionar con carga dinámica)
document.addEventListener('click', function (e) {
    var btnDec = e.target.closest('.btn-dec');
    var btnInc = e.target.closest('.btn-inc');
    var btn = btnDec || btnInc;
    if (!btn) return;

    var form = btn.closest('.form-agregar');
    var input = form.querySelector('.qty-grid');
    var maxStock = input.max !== '' ? parseInt(input.max) : null;
    var val = parseInt(input.value) || 1;

    if (btnDec) val = Math.max(1, val - 1);
    if (btnInc) val = maxStock !== null ? Math.min(maxStock, val + 1) : val + 1;

    input.value = val;
    form.querySelector('.btn-dec').disabled = val <= 1;
    form.querySelector('.btn-inc').disabled = maxStock !== null && val >= maxStock;
});

document.addEventListener('submit', function (e) {
    var form = e.target.closest('.form-agregar');
    if (!form) return;
    e.preventDefault();
    var input = form.querySelector('input[name="cantidad"]');
    var maxStock = input.max !== '' ? parseInt(input.max) : null;
    var cantidad = parseInt(input.value) || 1;
    if (maxStock !== null && cantidad > maxStock) {
        showToast('Stock máximo: ' + maxStock, 'warning');
        input.value = maxStock;
        return;
    }
    var formData = new FormData(form);
    fetch(form.dataset.url, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        showToast(data.message, data.success ? (data.warning ? 'warning' : 'success') : 'danger');
        updateCartBadge(data.cantidad_carrito);
    })
    .catch(function () {
        showToast('Error al agregar el producto', 'danger');
    });
});
</script>
@endpush

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

        // El menú llega de dos formas: en el path (/catalogo/{slug}) o como ?menu=.
        // Se toma del servidor, que ya lo resolvió, en vez de leerlo de la URL: en las
        // URLs con slug no existe ?menu=, así que al paginar o buscar no se mandaba
        // menu_id y el AJAX devolvía el catálogo entero en vez del rubro elegido.
        const MENU_ID = @json($menuActual->id ?? null);
        const CATEGORIA_ID = @json($categoriaActual->id ?? null);

        function obtenerParametrosURL() {
            const url = new URL(window.location);
            return {
                menu: MENU_ID || url.searchParams.get('menu'),
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
            if (CATEGORIA_ID) params.append('categoria_id', CATEGORIA_ID);
            if (urlParams.proveedor) params.append('proveedor', urlParams.proveedor);
            if (urlParams.etiqueta) params.append('etiqueta', urlParams.etiqueta);
            if (urlParams.etiqueta_valor) params.append('etiqueta_valor', urlParams.etiqueta_valor);
            if (urlParams.especificacion) params.append('especificacion', urlParams.especificacion);
            if (busqueda) params.append('buscar', busqueda);
            // Lo de la barra "Filtrar / Ordenar por" (está en la dirección).
            new URLSearchParams(window.location.search).forEach(function (valor, clave) {
                if (['orden', 'precio_desde', 'precio_hasta', 'con_stock'].indexOf(clave) !== -1 && valor) params.append(clave, valor);
            });

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
            if (CATEGORIA_ID) params.append('categoria_id', CATEGORIA_ID);
                if (urlParams.proveedor) params.append('proveedor', urlParams.proveedor);
                if (urlParams.etiqueta) params.append('etiqueta', urlParams.etiqueta);
                if (urlParams.etiqueta_valor) params.append('etiqueta_valor', urlParams.etiqueta_valor);
                if (urlParams.especificacion) params.append('especificacion', urlParams.especificacion);
                if (busqueda) params.append('buscar', busqueda);
                // Lo de la barra "Filtrar / Ordenar por" (está en la dirección).
                new URLSearchParams(window.location.search).forEach(function (valor, clave) {
                    if (['orden', 'precio_desde', 'precio_hasta', 'con_stock'].indexOf(clave) !== -1 && valor) params.append(clave, valor);
                });
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
