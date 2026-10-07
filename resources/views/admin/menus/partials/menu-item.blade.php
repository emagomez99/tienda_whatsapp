{{--
    Un menú del árbol (un <li>) y, adentro, la lista de sus submenús.

    La fila muestra sólo lo que se lee de un vistazo: nombre (abre la edición), qué
    filtra y cuántos productos muestra. Las acciones aparecen al pasar el mouse: +
    (submenú adentro) y ⋯ con el resto. El orden se cambia arrastrando desde ⋮⋮.

    Recibe: $menu, $nivel, $esPrimero/$esUltimo y $productosPorMenu.
--}}
@php
    $cantidad      = $productosPorMenu[$menu->id] ?? null;
    $descendientes = $menu->cantidadDescendientes();
    $hijos         = $menu->children;
    $puedeEditar   = auth()->user()->puede('menus.gestionar');
    $admiteHijos   = $nivel <= App\Models\Menu::NIVEL_MAXIMO_PADRE;
@endphp
<li class="menu-nodo {{ $menu->activo ? '' : 'inactivo' }} {{ session('menu_movido') === $menu->id ? 'recien-movido' : '' }}"
    id="menu-{{ $menu->id }}" data-id="{{ $menu->id }}" data-nombre="{{ $menu->nombre }}">
    <div class="menu-fila">
        @if($puedeEditar)
            <span class="menu-arrastrar" title="Arrastrá para ordenar o para meterlo dentro de otro menú" aria-hidden="true">
                <i class="bi bi-grip-vertical"></i>
            </span>
        @endif

        @if($hijos->count() > 0)
            <button type="button" class="menu-plegar" aria-expanded="true" title="Plegar o desplegar sus submenús">
                <i class="bi bi-chevron-down"></i>
            </button>
        @else
            <span class="menu-plegar-vacio"></span>
        @endif

        <div class="menu-texto">
            <a href="{{ route('admin.menus.edit', $menu) }}" class="menu-nombre">{{ $menu->nombre }}</a>
            @unless($menu->activo)
                <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">Inactivo</span>
            @endunless
            <div class="menu-filtro">
                @if($menu->esContenedor())
                    <i class="bi bi-folder2"></i> Agrupa{{ $hijos->count() ? ' · ' . $hijos->count() . ($hijos->count() === 1 ? ' submenú' : ' submenús') : '' }}
                @elseif($menu->tipo_enlace === 'proveedor')
                    <i class="bi bi-truck"></i> {{ $menu->nombre_enlace }}
                @elseif($menu->tipo_enlace === 'especificacion')
                    <i class="bi bi-list-ul"></i> {{ $menu->enlace_valor }}
                @else
                    <i class="bi bi-tag"></i> {{ $menu->nombre_enlace }}
                @endif
            </div>
        </div>

        @if($cantidad !== null)
            @if($cantidad === 0)
                <span class="menu-cuenta vacio" title="El cliente entra y no encuentra nada: revisá el filtro o el de los menús de arriba.">
                    <i class="bi bi-exclamation-triangle"></i> 0 productos
                </span>
            @else
                <span class="menu-cuenta" title="Productos que ve el cliente al entrar">
                    {{ number_format($cantidad, 0, ',', '.') }} {{ $cantidad === 1 ? 'producto' : 'productos' }}
                </span>
            @endif
        @endif

        <div class="menu-acciones">
            @if($puedeEditar && $admiteHijos)
                <a href="{{ route('admin.menus.create', ['parent_id' => $menu->id]) }}" class="btn btn-sm btn-light"
                   title="Agregar un submenú dentro de «{{ $menu->nombre }}»">
                    <i class="bi bi-plus-lg"></i>
                </a>
            @endif
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false" title="Más acciones">
                    <i class="bi bi-three-dots"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item" href="{{ route('admin.menus.edit', $menu) }}"><i class="bi bi-pencil me-2"></i>Editar</a></li>
                    @unless($menu->esContenedor())
                        <li><a class="dropdown-item" href="{{ $menu->url }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-2"></i>Ver en la tienda</a></li>
                    @endunless
                    @if($puedeEditar)
                        {{-- Sin arrastrar (con teclado, o si cuesta en el celular): subir y bajar. --}}
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('admin.menus.mover', $menu) }}" method="POST">
                                @csrf
                                <input type="hidden" name="direccion" value="arriba">
                                <button type="submit" class="dropdown-item" {{ $esPrimero ? 'disabled' : '' }}><i class="bi bi-arrow-up me-2"></i>Subir</button>
                            </form>
                        </li>
                        <li>
                            <form action="{{ route('admin.menus.mover', $menu) }}" method="POST">
                                @csrf
                                <input type="hidden" name="direccion" value="abajo">
                                <button type="submit" class="dropdown-item" {{ $esUltimo ? 'disabled' : '' }}><i class="bi bi-arrow-down me-2"></i>Bajar</button>
                            </form>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('admin.menus.destroy', $menu) }}" method="POST"
                                  data-confirmar="¿Eliminar el menú «{{ $menu->nombre }}»?"
                                  @if($descendientes > 0)
                                      data-confirmar-detalle="{{ $descendientes === 1 ? 'Se elimina también su submenú.' : 'Se eliminan también sus ' . $descendientes . ' submenús.' }}"
                                  @endif
                                  data-confirmar-boton="Sí, eliminar">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Eliminar</button>
                            </form>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

    {{-- Siempre está, aunque no tenga submenús: es donde se suelta para meter otro adentro. --}}
    @if($admiteHijos)
        <ul class="menu-arbol" data-nivel="{{ $nivel + 1 }}">
            @foreach($hijos as $hijo)
                @include('admin.menus.partials.menu-item', [
                    'menu'      => $hijo,
                    'nivel'     => $nivel + 1,
                    'esPrimero' => $loop->first,
                    'esUltimo'  => $loop->last,
                ])
            @endforeach
        </ul>
    @endif
</li>
