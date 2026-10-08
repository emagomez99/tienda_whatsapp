@extends('layouts.admin')

@section('title', 'Menú de la tienda')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-list-nested"></i> Menú de la tienda</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-menus" titulo="Cómo funciona el menú">
            @include('admin.ayudas.menus')
        </x-ayuda>
        <a href="{{ route('admin.menus.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Crear menú
        </a>
    </div>
</div>

@php $puedeGestionar = auth()->user()->puede('menus.gestionar'); @endphp
<div class="card mb-4" id="origen-menu">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="flex-grow-1" style="min-width: 240px;">
            <strong>Qué menú ve el cliente</strong>
            <div class="small text-muted">
                @if($automatico)
                    Se arma solo con tus categorías que tienen productos, en el orden de
                    <a href="{{ route('admin.categorias.index') }}">Categorías</a>{{ $categoriasEnMenu ? ' (' . $categoriasEnMenu . ($categoriasEnMenu === 1 ? ' opción' : ' opciones') . ')' : '' }}.
                    @if($categoriasEnMenu === 0)
                        <span class="text-warning-emphasis">Todavía no hay categorías con productos: la tienda no muestra menú.</span>
                    @endif
                @else
                    El que armás acá abajo. También podés usar uno automático con tus categorías: estos menús quedan guardados por si querés volver.
                @endif
            </div>
        </div>
        @if($puedeGestionar)
            <form action="{{ route('admin.menus.origen') }}" method="POST" id="form-origen-menu">
                @csrf
                @method('PATCH')
                <div class="btn-group" role="group" aria-label="Origen del menú">
                    <input type="radio" class="btn-check" name="origen" id="origen-categorias" value="{{ App\Services\MenuDeLaTienda::ORIGEN_CATEGORIAS }}" {{ $automatico ? 'checked' : '' }}>
                    <label class="btn btn-outline-primary btn-sm" for="origen-categorias"><i class="bi bi-diagram-3"></i> Con las categorías</label>
                    <input type="radio" class="btn-check" name="origen" id="origen-propio" value="{{ App\Services\MenuDeLaTienda::ORIGEN_PROPIO }}" {{ $automatico ? '' : 'checked' }}>
                    <label class="btn btn-outline-primary btn-sm" for="origen-propio"><i class="bi bi-list-nested"></i> Armado a mano</label>
                </div>
            </form>
            @push('scripts')
            <script>
                document.querySelectorAll('#form-origen-menu input[name="origen"]').forEach(function (radio) {
                    radio.addEventListener('change', function () { radio.form.submit(); });
                });
            </script>
            @endpush
        @endif
    </div>
</div>

@if($automatico)
    {{-- Cómo queda el menú automático: el de las categorías visibles. --}}
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-eye"></i> Así se ve en la tienda</div>
        <div class="card-body">
            @forelse(App\Services\MenuDeLaTienda::opciones() as $opcion)
                <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
                    <strong>{{ $opcion->nombre() }}</strong>
                    @if($opcion->tieneHijos())
                        <i class="bi bi-chevron-down small text-muted"></i>
                        <span class="text-muted small">{{ $opcion->hijos()->map(function ($h) { return $h->nombre(); })->implode(' · ') }}</span>
                    @endif
                </div>
            @empty
                <div class="text-muted">
                    Todavía no hay categorías con productos, así que la tienda no muestra menú.
                    <a href="{{ route('admin.categorias.index') }}">Crear categorías</a>
                </div>
            @endforelse
            <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Para cambiar el orden o los nombres, andá a <a href="{{ route('admin.categorias.index') }}">Categorías</a>.</div>
        </div>
    </div>
@endif

@if($menus->count() > 0 && !$automatico)
    @include('admin.menus.partials.vista-previa')
@endif

<div class="card">
    @if($automatico)
        {{-- Con el automático, lo armado a mano no se usa: queda plegado para no confundir. --}}
        <div class="card-header" role="button" data-bs-toggle="collapse" data-bs-target="#estructura-menu" aria-expanded="false" aria-controls="estructura-menu">
            <i class="bi bi-archive"></i> Menú armado a mano <small class="text-muted">(guardado, no se muestra mientras uses el de categorías)</small>
            <i class="bi bi-chevron-down float-end"></i>
        </div>
    @else
        <div class="card-header">Estructura del menú <small class="text-muted">· arrastrá <i class="bi bi-grip-vertical"></i> para ordenar</small></div>
    @endif
    <div class="card-body p-0 {{ $automatico ? 'collapse' : '' }}" id="estructura-menu">
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
