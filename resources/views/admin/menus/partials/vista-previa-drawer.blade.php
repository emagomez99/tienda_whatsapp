{{-- Una opción de la vista previa del menú lateral (celular), con sus submenús. --}}
@php
    $hijos    = $activos($menu->children);
    $cantidad = $productosPorMenu[$menu->id] ?? null;
@endphp
@if($hijos->isNotEmpty())
    <details>
        <summary>
            <span>{{ $menu->nombre }}</span>
            <i class="bi bi-chevron-down"></i>
        </summary>
        <div class="vp-sub">
            @unless($menu->esContenedor())
                <a class="vp-drawer-item" href="#menu-{{ $menu->id }}">
                    <span><i class="bi bi-grid me-1 opacity-50"></i> Ver todos</span>
                    @if($cantidad !== null)<span class="vp-cuenta">{{ $cantidad }}</span>@endif
                </a>
            @endunless
            @foreach($hijos as $hijo)
                @include('admin.menus.partials.vista-previa-drawer', ['menu' => $hijo, 'nivel' => $nivel + 1])
            @endforeach
        </div>
    </details>
@else
    <a class="vp-drawer-item" href="#menu-{{ $menu->id }}">
        <span>{{ $menu->nombre }}</span>
        @if($cantidad !== null)
            <span class="vp-cuenta">@if($cantidad === 0)<i class="bi bi-exclamation-triangle"></i>@endif {{ $cantidad }}</span>
        @endif
    </a>
@endif
