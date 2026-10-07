@extends('layouts.admin')

@section('title', 'Gestión de Menú')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-list-nested"></i> Menú de la Tienda</h3>
    <a href="{{ route('admin.menus.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo
    </a>
</div>

@if($menus->count() > 0)
    @include('admin.menus.partials.vista-previa')
@endif

<div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Estructura del menú</span>
        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="boton-ayuda-menus"
                data-bs-toggle="collapse" data-bs-target="#ayuda-menus" aria-expanded="false" aria-controls="ayuda-menus">
            <i class="bi bi-question-circle"></i> Cómo funciona
        </button>
    </div>
    @include('admin.menus.partials.ayuda')
    <div class="card-body p-0">
        @if($menus->count() > 0)
            <ul class="arbol-lista" id="menu-tree" data-nivel="0">
                @foreach($menus as $menu)
                    @include('admin.menus.partials.menu-item', [
                        'menu'      => $menu,
                        'nivel'     => 0,
                        'esPrimero' => $loop->first,
                        'esUltimo'  => $loop->last,
                    ])
                @endforeach
            </ul>
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
@endsection

@include('admin.partials.arbol-ordenable', [
    'arbolId'            => 'menu-tree',
    'prefijoNodo'        => 'menu-',
    'urlOrdenar'         => route('admin.menus.reordenar'),
    'nivelMaximo'        => App\Services\ArbolDeMenus::NIVEL_MAXIMO,
    'refrescarIds'       => ['menu-tree', 'vista-previa-menu'],
    'avisarSinProductos' => true,
    'movido'             => session('menu_movido'),
])
