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
                @if(request('menu'))
                    <input type="hidden" name="menu" value="{{ request('menu') }}">
                @endif
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
@endsection
