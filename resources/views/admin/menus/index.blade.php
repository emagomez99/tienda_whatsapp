@extends('layouts.admin')

@section('title', 'Gestión de Menú')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-list-nested"></i> Menú de la Tienda</h3>
    <a href="{{ route('admin.menus.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo
    </a>
</div>

@php $puedeGestionar = auth()->user()->puede('menus.gestionar'); @endphp
<div class="card mb-4" id="origen-menu">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="flex-grow-1" style="min-width: 240px;">
            <strong>Qué menú ve el cliente</strong>
            <div class="small text-muted">
                @if($automatico)
                    Se arma solo con tus categorías visibles, en el orden de
                    <a href="{{ route('admin.categorias.index') }}">Categorías</a>{{ $categoriasEnMenu ? ' (' . $categoriasEnMenu . ($categoriasEnMenu === 1 ? ' opción' : ' opciones') . ')' : '' }}.
                    @if($categoriasEnMenu === 0)
                        <span class="text-warning-emphasis">Todavía no hay categorías visibles: la tienda no muestra menú.</span>
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

@if($automatico && $menus->count() > 0)
    <div class="alert alert-light border small">
        <i class="bi bi-info-circle"></i> Los menús de abajo no se muestran en la tienda mientras uses el de categorías. Sus páginas siguen funcionando para quien llegue desde Google o un enlace viejo.
    </div>
@endif

@if($menus->count() > 0 && !$automatico)
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
