{{--
    Una fila del árbol de menús, y debajo sus submenús.

    Recibe: $menu, $nivel, $esPrimero/$esUltimo (entre sus hermanos, para ↑ ↓) y
    $productosPorMenu (id → cantidad; los que sólo agrupan no están).
--}}
@php
    $cantidad      = $productosPorMenu[$menu->id] ?? null;
    $descendientes = $menu->cantidadDescendientes();
    $hijos         = $menu->children;
@endphp
<div class="list-group-item menu-item nivel-{{ $nivel }} {{ !$menu->activo ? 'inactivo' : '' }} {{ session('menu_movido') === $menu->id ? 'recien-movido' : '' }}"
     data-id="{{ $menu->id }}" id="menu-{{ $menu->id }}">
    <div class="d-flex justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center min-w-0">
            @if($hijos->count() > 0)
                <i class="bi bi-folder-fill text-warning me-2"></i>
            @else
                <i class="bi bi-file-text me-2"></i>
            @endif
            <div class="min-w-0">
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
                        @case('etiqueta')
                            <span class="badge badge-tipo bg-success">Etiqueta</span>
                            {{ $menu->nombre_enlace }}
                            @break
                        @case('especificacion')
                            <span class="badge badge-tipo bg-info">Especificación</span>
                            {{ $menu->enlace_valor }}
                            @break
                        @default
                            <span class="badge badge-tipo bg-secondary">Agrupa</span>
                    @endswitch
                    @if($hijos->count() > 0)
                        <span class="ms-2">{{ $hijos->count() }} {{ $hijos->count() === 1 ? 'submenú' : 'submenús' }}</span>
                    @endif
                </small>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            @if($cantidad !== null)
                @if($cantidad === 0)
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"
                          title="El cliente entra y no encuentra nada: revisá el filtro o el de los menús de arriba.">
                        <i class="bi bi-exclamation-triangle"></i> 0 productos
                    </span>
                @else
                    <span class="badge bg-light text-secondary border" title="Productos que ve el cliente al entrar">
                        {{ number_format($cantidad, 0, ',', '.') }} {{ $cantidad === 1 ? 'producto' : 'productos' }}
                    </span>
                @endif
            @endif

            @if(auth()->user()->puede('menus.gestionar'))
                <div class="btn-group">
                    <form action="{{ route('admin.menus.mover', $menu) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="direccion" value="arriba">
                        <button type="submit" class="btn btn-sm btn-outline-secondary rounded-end-0" title="Subir" {{ $esPrimero ? 'disabled' : '' }}>
                            <i class="bi bi-arrow-up"></i>
                        </button>
                    </form>
                    <form action="{{ route('admin.menus.mover', $menu) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="direccion" value="abajo">
                        <button type="submit" class="btn btn-sm btn-outline-secondary rounded-0 border-start-0" title="Bajar" {{ $esUltimo ? 'disabled' : '' }}>
                            <i class="bi bi-arrow-down"></i>
                        </button>
                    </form>
                </div>
            @endif

            <div class="btn-group">
                {{-- Submenú nuevo acá adentro: el alta llega con "Dentro de" ya elegido. --}}
                @if($nivel <= App\Models\Menu::NIVEL_MAXIMO_PADRE && auth()->user()->puede('menus.gestionar'))
                    <a href="{{ route('admin.menus.create', ['parent_id' => $menu->id]) }}" class="btn btn-sm btn-outline-success"
                       title="Agregar un submenú dentro de «{{ $menu->nombre }}»">
                        <i class="bi bi-plus-lg"></i>
                    </a>
                @endif
                @unless($menu->esContenedor())
                    <a href="{{ $menu->url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Ver en la tienda">
                        <i class="bi bi-eye"></i>
                    </a>
                @endunless
                <a href="{{ route('admin.menus.edit', $menu) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                    <i class="bi bi-pencil"></i>
                </a>
                <form action="{{ route('admin.menus.destroy', $menu) }}" method="POST" class="d-inline"
                      data-confirmar="¿Eliminar el menú «{{ $menu->nombre }}»?"
                      @if($descendientes > 0)
                          data-confirmar-detalle="{{ $descendientes === 1 ? 'Se elimina también su submenú.' : 'Se eliminan también sus ' . $descendientes . ' submenús.' }}"
                      @endif
                      data-confirmar-boton="Sí, eliminar">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@foreach($hijos as $hijo)
    @include('admin.menus.partials.menu-item', [
        'menu'      => $hijo,
        'nivel'     => min($nivel + 1, 3),
        'esPrimero' => $loop->first,
        'esUltimo'  => $loop->last,
    ])
@endforeach
