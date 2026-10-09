@extends('layouts.admin')

@section('title', 'Productos')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-box-seam"></i> Productos</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-productos" titulo="Cómo cargar productos">
            @include('admin.ayudas.productos')
        </x-ayuda>
        @if(auth()->user()->puede('productos.importar'))
            {{-- Excel: importar, o exportar lo que muestra el listado (con sus filtros). --}}
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('admin.importaciones.index') }}"><i class="bi bi-upload me-2"></i>Importar desde Excel</a></li>
                    <li><a class="dropdown-item" href="{{ route('admin.importaciones.exportar', $filtro->valores()) }}"><i class="bi bi-download me-2"></i>{{ $filtro->hayActivos() ? 'Exportar los de este filtro' : 'Exportar todos los productos' }}</a></li>
                </ul>
            </div>
        @endif
        <a href="{{ route('admin.productos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Crear producto
        </a>
    </div>
</div>

@php
    $filtrosActivos = $filtro->hayActivos();
    $puedeEditar = auth()->user()->puede('productos.editar');
@endphp
@include('admin.productos.partials.filtros')

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
                <i class="bi bi-funnel text-muted fs-2"></i>
                <p class="fw-semibold mt-2 mb-1">Ningún producto coincide con el filtro</p>
                <a href="{{ route('admin.productos.index') }}">Quitar los filtros y ver todos</a>
            </div>
        @elseif($productos->isEmpty())
            {{-- La tienda todavía no tiene ningún producto: el primer paso, a mano. --}}
            <div class="text-center py-5">
                <i class="bi bi-box-seam text-muted display-6"></i>
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
                                    <div class="panel-miniatura">
                                        <img src="{{ $producto->imagen_url ?? '/img/no-image.svg' }}"
                                             alt=""
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
                                <td>
                                    {{ $producto->stock }}
                                    @if($producto->variantes_count)
                                        <small class="d-block text-muted text-nowrap" title="El stock es la suma de sus variantes">{{ $producto->variantes_count }} {{ $producto->variantes_count === 1 ? 'variante' : 'variantes' }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="d-flex flex-column gap-1">
                                    @if($producto->disponible)
                                        <span class="badge rounded-pill text-bg-success">Disponible</span>
                                    @else
                                        <span class="badge rounded-pill text-bg-secondary">No disponible</span>
                                    @endif
                                    @if($producto->por_encargue)
                                        <span class="badge rounded-pill text-bg-info">
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
                                        <form action="{{ route('admin.productos.destroy', $producto) }}" method="POST" data-confirmar="¿Eliminar «{{ $producto->descripcion }}»?" data-confirmar-detalle="No se puede deshacer: se borra con su historial de stock. Los pedidos donde está lo siguen mostrando por su nombre." data-confirmar-boton="Sí, eliminar">
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
