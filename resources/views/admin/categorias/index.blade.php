@extends('layouts.admin')

@section('title', 'Categorías')

@section('content')
@php $puedeEditar = auth()->user()->puede('categorias.gestionar'); @endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h3 class="mb-0"><i class="bi bi-diagram-3"></i> Categorías</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-categorias" titulo="Cómo funcionan las categorías">
            @include('admin.ayudas.categorias')
        </x-ayuda>
        @if($puedeEditar)
            <a href="{{ route('admin.categorias.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Crear categoría
            </a>
        @endif
    </div>
</div>
<p class="text-muted mb-4">Son los estantes de tu negocio, como las góndolas de un supermercado: Bebidas, Almacén, Limpieza. Tus clientes eligen uno y ven sólo esos productos.</p>

@if($sinCategoria > 0 && $categorias->count() > 0)
    <div class="alert alert-warning d-flex align-items-center gap-2 py-2">
        <i class="bi bi-exclamation-triangle"></i>
        <span>{{ number_format($sinCategoria, 0, ',', '.') }} {{ $sinCategoria === 1 ? 'producto no tiene' : 'productos no tienen' }} categoría todavía.</span>
        @if(auth()->user()->puede('productos.ver'))
            <a href="{{ route('admin.productos.index', ['categoria' => App\Support\FiltroDeProductos::SIN_CATEGORIA]) }}" class="alert-link ms-auto">Ver cuáles y asignarles una</a>
        @endif
    </div>
@endif

@if($categorias->count() > 0)
    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Árbol de categorías</span>
            <small class="text-muted">Arrastrá <i class="bi bi-grip-vertical"></i> para ordenar, o soltala sobre otra para meterla adentro</small>
        </div>
        <div class="card-body p-0">
            <ul class="arbol-lista" id="categoria-tree" data-nivel="0">
                @foreach($categorias as $categoria)
                    @include('admin.categorias.partials.categoria-item', [
                        'categoria' => $categoria,
                        'nivel'     => 0,
                        'esPrimero' => $loop->first,
                        'esUltimo'  => $loop->last,
                    ])
                @endforeach
            </ul>
        </div>
    </div>
@else
    {{-- Todavía sin categorías: explicar para qué sirven antes que mostrar una lista vacía. --}}
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-diagram-3 display-4 text-muted"></i>
            <h4 class="mt-3">Armá los estantes de tu tienda</h4>
            <p class="text-muted mx-auto" style="max-width: 32rem;">
                Ordenalos en estantes, como las góndolas de un supermercado: Bebidas, Almacén, Limpieza. Un estante puede tener otros adentro: dentro de Bebidas, Gaseosas y Aguas.
                Así tus clientes encuentran más rápido lo que buscan.
            </p>
            @if($puedeEditar)
                <a href="{{ route('admin.categorias.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Creá tu primera categoría
                </a>
            @endif
        </div>
    </div>
@endif
@endsection

{{-- Siempre: trae también los estilos. Sin permiso no hay ⋮⋮, así que no se arrastra. --}}
@include('admin.partials.arbol-ordenable', [
    'arbolId'      => 'categoria-tree',
    'prefijoNodo'  => 'categoria-',
    'urlOrdenar'   => route('admin.categorias.reordenar'),
    'nivelMaximo'  => App\Services\ArbolDeCategorias::NIVEL_MAXIMO,
    'refrescarIds' => ['categoria-tree'],
    'movido'       => session('categoria_movida'),
])
