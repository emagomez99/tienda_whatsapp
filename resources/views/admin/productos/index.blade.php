@extends('layouts.admin')

@section('title', 'Productos')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-box-seam"></i> Productos</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-productos" titulo="Cómo cargar productos">
            @include('admin.ayudas.productos')
        </x-ayuda>
        <a href="{{ route('admin.productos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo
        </a>
    </div>
</div>

@php
    $filtrosActivos = $filtro->hayActivos();
    $puedeEditar = auth()->user()->puede('productos.editar');
@endphp
<div class="card mb-4">
    <div class="card-header d-flex d-md-none justify-content-between align-items-center py-2 px-3"
         style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#filtros-productos"
         aria-expanded="{{ $filtrosActivos ? 'true' : 'false' }}">
        <span class="small fw-semibold text-muted"><i class="bi bi-funnel me-1"></i> Filtros{!! $filtrosActivos ? ' <span class="badge bg-primary ms-1" style="font-size:.6rem;">activo</span>' : '' !!}</span>
        <i class="bi bi-chevron-down filtros-chevron" style="transition:transform .2s;{{ $filtrosActivos ? 'transform:rotate(180deg);' : '' }}"></i>
    </div>
    <div class="collapse d-md-block{{ $filtrosActivos ? ' show' : '' }}" id="filtros-productos">
    <div class="card-body">
        {{-- Los desplegables filtran apenas se elige una opción (script de abajo):
             tener que tocar además la lupa era un paso que muchos no descubrían.
             El texto sí espera al botón o a Enter, para no recargar con cada letra. --}}
        <form action="{{ route('admin.productos.index') }}" method="GET" class="row g-3" id="form-filtros-productos">
            <div class="col-md-12 col-lg-4">
                <label for="filtro-buscar" class="form-label text-muted mb-1" style="font-size:.75rem;">Nombre o código</label>
                <div class="input-group">
                    <input type="text" name="buscar" id="filtro-buscar" class="form-control" placeholder="Ej: gaseosa" value="{{ request('buscar') }}">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label text-muted mb-1" style="font-size:.75rem;">Proveedor</label>
                <select name="proveedor" class="form-select">
                    <option value="">Todos</option>
                    @foreach($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}" {{ request('proveedor') == $proveedor->id ? 'selected' : '' }}>
                            {{ $proveedor->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label text-muted mb-1" style="font-size:.75rem;">Categoría</label>
                <select name="categoria" class="form-select">
                    <option value="">Todas</option>
                    <option value="{{ App\Support\FiltroDeProductos::SIN_CATEGORIA }}" {{ request('categoria') === App\Support\FiltroDeProductos::SIN_CATEGORIA ? 'selected' : '' }}>Sin categoría</option>
                    @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id }}" {{ (string) request('categoria') === (string) $categoria->id ? 'selected' : '' }}>
                            {!! str_repeat('&nbsp;&nbsp;&nbsp;', $categoria->nivel()) !!}{{ $categoria->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label text-muted mb-1" style="font-size:.75rem;">Estado</label>
                <select name="disponible" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" {{ request('disponible') === '1' ? 'selected' : '' }}>Disponibles</option>
                    <option value="0" {{ request('disponible') === '0' ? 'selected' : '' }}>No disponibles</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label text-muted mb-1" style="font-size:.75rem;">Stock</label>
                <select name="stock" class="form-select">
                    <option value="">Todos</option>
                    <option value="con" {{ request('stock') === 'con' ? 'selected' : '' }}>Con stock</option>
                    <option value="sin" {{ request('stock') === 'sin' ? 'selected' : '' }}>Sin stock</option>
                </select>
            </div>
            @if($filtrosActivos)
                <div class="col-12 small">
                    <a href="{{ route('admin.productos.index') }}" class="text-decoration-none"><i class="bi bi-x-circle"></i> Quitar los filtros</a>
                </div>
            @endif
        </form>
        <script>
            document.querySelectorAll('#form-filtros-productos select').forEach(function (select) {
                select.addEventListener('change', function () { select.form.submit(); });
            });
        </script>
    </div>
    </div>
</div>

@if($sinCategoria > 0 && request('categoria') !== App\Support\FiltroDeProductos::SIN_CATEGORIA)
    <div class="alert alert-warning d-flex align-items-center flex-wrap gap-2 py-2">
        <i class="bi bi-exclamation-triangle"></i>
        <span>{{ number_format($sinCategoria, 0, ',', '.') }} {{ $sinCategoria === 1 ? 'producto no tiene' : 'productos no tienen' }} categoría: no {{ $sinCategoria === 1 ? 'aparece' : 'aparecen' }} al navegar la tienda por categorías.</span>
        <a href="{{ route('admin.productos.index', ['categoria' => App\Support\FiltroDeProductos::SIN_CATEGORIA]) }}" class="alert-link ms-auto">Ver cuáles</a>
    </div>
@endif

<div class="card">
    <div class="card-body">
        @if($productos->isEmpty() && $filtrosActivos)
            {{-- Vacío por el filtro: que se note que hay productos, sólo que no éstos. --}}
            <div class="text-center py-4">
                <i class="bi bi-funnel text-muted" style="font-size:2rem;"></i>
                <p class="fw-semibold mt-2 mb-1">Ningún producto coincide con el filtro</p>
                <a href="{{ route('admin.productos.index') }}">Quitar los filtros y ver todos</a>
            </div>
        @elseif($productos->isEmpty())
            {{-- La tienda todavía no tiene ningún producto: el primer paso, a mano. --}}
            <div class="text-center py-5">
                <i class="bi bi-box-seam text-muted" style="font-size:2.5rem;"></i>
                <p class="fw-semibold mt-2 mb-1">Todavía no cargaste ningún producto</p>
                <p class="text-muted small mb-3">Por ejemplo, una «Gaseosa Coca-Cola 1,5 L»: su nombre, su precio y en qué estante va.</p>
                <a href="{{ route('admin.productos.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Cargar el primer producto
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            @if($puedeEditar)
                                <th style="width:1%;">
                                    <input type="checkbox" class="form-check-input" id="masa-todos" title="Marcar los de esta página" aria-label="Marcar los de esta página">
                                </th>
                            @endif
                            <th>Imagen</th>
                            <th>Descripción</th>
                            <th>Código</th>
                            <th>Proveedor</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($productos as $producto)
                            <tr>
                                @if($puedeEditar)
                                    <td>
                                        <input type="checkbox" class="form-check-input masa-producto" value="{{ $producto->id }}" aria-label="Marcar {{ $producto->descripcion }}">
                                    </td>
                                @endif
                                <td style="width: 60px;">
                                    <div class="rounded overflow-hidden bg-light" style="width: 50px; height: 50px; flex-shrink: 0;">
                                        <img src="{{ $producto->imagen_url ?? '/img/no-image.svg' }}"
                                             alt=""
                                             style="width: 100%; height: 100%; object-fit: cover; display: block;"
                                             onerror="this.onerror=null;this.src='/img/no-image.svg';">
                                    </div>
                                </td>
                                <td>
                                    <strong>{{ $producto->descripcion }}</strong>
                                    @if($categorias->isNotEmpty())
                                        <div class="small mt-1">
                                            @forelse($producto->categorias as $suya)
                                                <span class="text-muted text-nowrap">{{ str_replace(' > ', ' › ', $caminos[$suya->id] ?? '') }}</span>@if(!$loop->last)<span class="text-muted"> · </span>@endif
                                            @empty
                                                <span class="text-warning-emphasis"><i class="bi bi-exclamation-circle"></i> Sin categoría</span>
                                            @endforelse
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $producto->id_proveedor ?? '-' }}</td>
                                <td>{{ $producto->proveedor->nombre ?? '-' }}</td>
                                <td>{{ $producto->precio_con_moneda }}</td>
                                <td>{{ $producto->stock }}</td>
                                <td>
                                    <span class="d-flex flex-column gap-1">
                                    @if($producto->disponible)
                                        <span class="badge rounded-pill text-bg-success" style="font-size:.68rem;font-weight:500;">Disponible</span>
                                    @else
                                        <span class="badge rounded-pill text-bg-secondary" style="font-size:.68rem;font-weight:500;">No disponible</span>
                                    @endif
                                    @if($producto->por_encargue)
                                        <span class="badge rounded-pill text-bg-info" style="font-size:.68rem;font-weight:500;">
                                            <i class="bi bi-clock"></i> Se puede pedir sin stock
                                        </span>
                                    @endif
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ $producto->url() }}" class="btn btn-sm btn-outline-secondary" title="Ver en tienda" target="_blank">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.productos.historial', $producto) }}" class="btn btn-sm btn-outline-secondary" title="Historial de movimientos">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="{{ route('admin.productos.edit', $producto) . (request()->getQueryString() ? '?_back=' . urlencode(request()->getQueryString()) : '') }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.productos.destroy', $producto) }}" method="POST" data-confirmar="¿Eliminar este producto?" data-confirmar-boton="Sí, eliminar">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $productos->withQueryString()->links('vendor.pagination.tienda') }}
            </div>
        @endif
    </div>
</div>

@if($puedeEditar && $productos->isNotEmpty())
    @include('admin.productos.partials.categorias-en-masa')
@endif
@endsection
