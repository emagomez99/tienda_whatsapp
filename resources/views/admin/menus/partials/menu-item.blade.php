<div class="list-group-item menu-item nivel-{{ $nivel }} {{ !$menu->activo ? 'inactivo' : '' }}" data-id="{{ $menu->id }}">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            @if($menu->children->count() > 0)
                <i class="bi bi-folder-fill text-warning me-2"></i>
            @else
                <i class="bi bi-file-text me-2"></i>
            @endif
            <div>
                <strong>{{ $menu->nombre }}</strong>
                @if(!$menu->activo)
                    <span class="badge bg-danger ms-2">Inactivo</span>
                @endif
                <br>
                <small class="text-muted">
                    @switch($menu->tipo_enlace)
                        @case('proveedor')
                            <span class="badge badge-tipo bg-primary">Proveedor</span>
                            {{ $menu->nombre_enlace }}
                            @break
                        @case('categoria')
                            <span class="badge badge-tipo bg-success">Categoría</span>
                            {{ $menu->nombre_enlace }}
                            @break
                        @case('especificacion')
                            <span class="badge badge-tipo bg-info">Especificación</span>
                            {{ $menu->enlace_valor }}
                            @break
                        @default
                            <span class="badge badge-tipo bg-secondary">Contenedor</span>
                    @endswitch
                    @if($menu->children->count() > 0)
                        <span class="ms-2">{{ $menu->children->count() }} submenú(s)</span>
                    @endif
                </small>
            </div>
        </div>
        <div class="btn-group">
            <a href="{{ route('admin.menus.edit', $menu) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                <i class="bi bi-pencil"></i>
            </a>
            <form action="{{ route('admin.menus.destroy', $menu) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este menú y todos sus submenús?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </div>
    </div>
</div>

@if($menu->children->count() > 0)
    @foreach($menu->children as $child)
        @include('admin.menus.partials.menu-item', ['menu' => $child, 'nivel' => min($nivel + 1, 3)])
    @endforeach
@endif
