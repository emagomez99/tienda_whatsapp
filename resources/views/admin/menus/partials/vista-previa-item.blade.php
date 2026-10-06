{{--
    Una opción de la vista previa de escritorio, con sus submenús. Sigue las mismas
    reglas que components/menu-item de la tienda: si tiene submenús se despliega, y
    si además muestra productos, el desplegable empieza con "Ver todos".
--}}
@php
    $hijos    = $activos($menu->children);
    $cantidad = $productosPorMenu[$menu->id] ?? null;
@endphp
<div class="vp-item">
    <a class="vp-link" href="#menu-{{ $menu->id }}" title="Ir a «{{ $menu->nombre }}» en la lista">
        <span>{{ $menu->nombre }}</span>
        @if($hijos->isEmpty() && $cantidad !== null)
            <span class="vp-cuenta {{ $cantidad === 0 ? 'vacio' : '' }}">
                @if($cantidad === 0)<i class="bi bi-exclamation-triangle"></i>@endif {{ $cantidad }}
            </span>
        @endif
        @if($hijos->isNotEmpty())
            <i class="bi {{ $nivel === 0 ? 'bi-caret-down-fill' : 'bi-chevron-right' }}" style="font-size:.65rem; opacity:.6;"></i>
        @endif
    </a>

    @if($hijos->isNotEmpty())
        <div class="vp-panel">
            @unless($menu->esContenedor())
                <a class="vp-link vp-todos" href="#menu-{{ $menu->id }}">
                    <span>Ver todos</span>
                    @if($cantidad !== null)<span class="vp-cuenta">{{ $cantidad }}</span>@endif
                </a>
                <div class="vp-separador"></div>
            @endunless
            @foreach($hijos as $hijo)
                @include('admin.menus.partials.vista-previa-item', ['menu' => $hijo, 'nivel' => $nivel + 1])
            @endforeach
        </div>
    @endif
</div>
