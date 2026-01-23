@extends('layouts.app')

@section('title', 'Tienda')

@php $menuEnSidebar = App\Models\Configuracion::menuEnSidebar(); @endphp

@section('content')
@if(!$menuEnSidebar)
<div class="container py-4">
@endif
    <div class="row mb-4">
        <div class="col-12">
            <form action="{{ route('tienda.index') }}" method="GET" class="row g-3">
                {{-- Mantener filtros del menú activo --}}
                @if(request('proveedor'))
                    <input type="hidden" name="proveedor" value="{{ request('proveedor') }}">
                @endif
                @if(request('etiqueta'))
                    <input type="hidden" name="etiqueta" value="{{ request('etiqueta') }}">
                @endif
                @if(request('etiqueta_valor'))
                    <input type="hidden" name="etiqueta_valor" value="{{ request('etiqueta_valor') }}">
                @endif
                @if(request('especificacion'))
                    <input type="hidden" name="especificacion" value="{{ request('especificacion') }}">
                @endif

                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar productos..." value="{{ request('buscar') }}">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                @if(!$menuEnSidebar)
                <div class="col-md-2">
                    @if(!request('proveedor') && !request('etiqueta_valor') && !request('especificacion'))
                        <select name="etiqueta" class="form-select" onchange="this.form.submit()">
                            <option value="">Filtrar por etiqueta</option>
                            @foreach($etiquetas as $etiqueta)
                                <option value="{{ $etiqueta->id }}" {{ request('etiqueta') == $etiqueta->id ? 'selected' : '' }}>
                                    {{ $etiqueta->nombre }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        @php
                            $filtroActivo = '';
                            if (request('proveedor')) {
                                $prov = App\Models\Proveedor::find(request('proveedor'));
                                $filtroActivo = $prov ? $prov->nombre : 'Proveedor';
                            } elseif (request('etiqueta')) {
                                $etiq = App\Models\Etiqueta::find(request('etiqueta'));
                                $filtroActivo = $etiq ? $etiq->nombre : 'Etiqueta';
                                if (request('etiqueta_valor')) {
                                    $filtroActivo .= ': ' . request('etiqueta_valor');
                                }
                            } elseif (request('especificacion')) {
                                $filtroActivo = request('especificacion');
                            }
                        @endphp
                        <span class="badge bg-primary d-flex align-items-center justify-content-center h-100" style="font-size: 0.9rem;">
                            <i class="bi bi-funnel-fill me-1"></i> {{ $filtroActivo }}
                        </span>
                    @endif
                </div>
                @endif
                <div class="{{ $menuEnSidebar ? 'col-md-4' : 'col-md-2' }}">
                    @if(request('buscar') || request('etiqueta') || request('proveedor') || request('especificacion'))
                        <a href="{{ route('tienda.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if($productos->isEmpty())
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No se encontraron productos.
        </div>
    @else
        <div class="row row-cols-1 row-cols-md-2 {{ $menuEnSidebar ? 'row-cols-lg-2 row-cols-xl-3' : 'row-cols-lg-3 row-cols-xl-4' }} g-4">
            @foreach($productos as $producto)
                <div class="col">
                    <div class="card h-100 producto-card shadow-sm">
                        @if($producto->url_imagen)
                            <img src="{{ $producto->imagen_url }}" class="card-img-top" alt="{{ $producto->descripcion }}" style="height: 250px; width: 100%; object-fit: contain; background-color: #f8f9fa;">
                        @else
                            <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center text-white" style="height: 250px;">
                                <i class="bi bi-image" style="font-size: 3rem;"></i>
                            </div>
                        @endif
                        <div class="card-body">
                            <h6 class="card-title">{{ $producto->descripcion }}</h6>
                            @if($producto->id_proveedor)
                                <small class="text-muted">Cod: {{ $producto->id_proveedor }}</small>
                                <br>
                            @endif
                            @if($producto->stock > 0)
                                <small class="text-success"><i class="bi bi-check-circle"></i> En stock ({{ $producto->stock }})</small>
                            @elseif($producto->por_encargue)
                                <small class="text-warning"><i class="bi bi-clock"></i> Por encargue</small>
                            @else
                                <small class="text-danger"><i class="bi bi-x-circle"></i> Sin stock</small>
                            @endif

                            @if($mostrarPrecios)
                                <p class="card-text h5 text-primary mt-2">
                                    {{ $producto->precio_con_moneda }}
                                </p>
                            @endif

                            @if($producto->etiquetas->where('visible_usuarios', true)->count() > 0)
                                <div class="mb-2">
                                    @foreach($producto->etiquetas->where('visible_usuarios', true) as $etiqueta)
                                        <span class="badge bg-info badge-etiqueta">{{ $etiqueta->nombre }}: {{ $etiqueta->pivot->valor }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if($producto->especificaciones->count() > 0)
                                <div class="especificaciones-list text-muted">
                                    @foreach($producto->especificaciones as $espec)
                                        <small>{{ $espec->clave }}: {{ $espec->valor }}</small><br>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="card-footer bg-transparent">
                            <form action="{{ route('carrito.agregar', $producto) }}" method="POST" class="d-flex gap-2">
                                @csrf
                                <input type="number" name="cantidad" value="1" min="1" class="form-control form-control-sm" style="width: 70px;">
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                    <i class="bi bi-cart-plus"></i> Agregar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-center mt-4">
            {{ $productos->withQueryString()->links() }}
        </div>
    @endif
@if(!$menuEnSidebar)
</div>
@endif
@endsection
