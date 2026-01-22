@extends('layouts.admin')

@section('title', 'Gestión de Menú')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-list-nested"></i> Menú de la Tienda</h2>
    <a href="{{ route('admin.menus.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo Ítem
    </a>
</div>

<div class="card">
    <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
            <span>Estructura del Menú</span>
            <small class="text-muted">Arrastra los elementos para reordenar</small>
        </div>
    </div>
    <div class="card-body p-0">
        @if($menus->count() > 0)
            <div class="list-group list-group-flush" id="menu-tree">
                @foreach($menus as $menu)
                    @include('admin.menus.partials.menu-item', ['menu' => $menu, 'nivel' => 0])
                @endforeach
            </div>
        @else
            <div class="text-center py-5 text-muted">
                <i class="bi bi-list-nested display-4"></i>
                <p class="mt-3">No hay elementos en el menú</p>
                <a href="{{ route('admin.menus.create') }}" class="btn btn-outline-primary">
                    <i class="bi bi-plus"></i> Crear primer ítem
                </a>
            </div>
        @endif
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-light">
        <i class="bi bi-info-circle"></i> Guía de Tipos
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <h6><span class="badge bg-secondary">Contenedor</span></h6>
                <small class="text-muted">Solo agrupa submenús, no filtra productos</small>
            </div>
            <div class="col-md-3">
                <h6><span class="badge bg-primary">Proveedor</span></h6>
                <small class="text-muted">Filtra productos por proveedor</small>
            </div>
            <div class="col-md-3">
                <h6><span class="badge bg-success">Categoría</span></h6>
                <small class="text-muted">Filtra por categoría y opcionalmente por valor</small>
            </div>
            <div class="col-md-3">
                <h6><span class="badge bg-info">Especificación</span></h6>
                <small class="text-muted">Filtra por valor de especificación</small>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .menu-item {
        border-left: 3px solid transparent;
        transition: all 0.2s;
    }
    .menu-item:hover {
        background-color: #f8f9fa;
    }
    .menu-item.nivel-0 { border-left-color: #0d6efd; }
    .menu-item.nivel-1 { border-left-color: #198754; padding-left: 2rem !important; }
    .menu-item.nivel-2 { border-left-color: #ffc107; padding-left: 4rem !important; }
    .menu-item.nivel-3 { border-left-color: #dc3545; padding-left: 6rem !important; }
    .menu-item.inactivo {
        opacity: 0.5;
    }
    .badge-tipo {
        font-size: 0.7rem;
    }
</style>
@endpush
