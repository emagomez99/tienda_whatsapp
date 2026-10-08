{{--
    Una categoría del árbol (un <li>) y, adentro, sus subcategorías. Mismo marcado
    que el árbol de menús (admin/partials/arbol-ordenable).

    Recibe: $categoria, $nivel, $esPrimero/$esUltimo y $productosPorCategoria.
--}}
@php
    $cantidad    = $productosPorCategoria[$categoria->id] ?? 0;
    $hijas       = $categoria->children;
    $puedeEditar = auth()->user()->puede('categorias.gestionar');
    $admiteHijas = $nivel <= App\Models\Categoria::NIVEL_MAXIMO_PADRE;
    $ocultaPor   = $categoria->ocultaPor();
@endphp
<li class="arbol-nodo {{ $ocultaPor ? 'inactivo' : '' }} {{ session('categoria_movida') === $categoria->id ? 'recien-movido' : '' }}"
    id="categoria-{{ $categoria->id }}" data-id="{{ $categoria->id }}" data-nombre="{{ $categoria->nombre }}">
    <div class="arbol-fila">
        @if($puedeEditar)
            <span class="arbol-arrastrar" title="Arrastrá para ordenar o para meterla dentro de otra categoría" aria-hidden="true">
                <i class="bi bi-grip-vertical"></i>
            </span>
        @endif

        @if($hijas->count() > 0)
            <button type="button" class="arbol-plegar" aria-expanded="true" title="Plegar o desplegar sus subcategorías">
                <i class="bi bi-chevron-down"></i>
            </button>
        @else
            <span class="arbol-plegar-vacio"></span>
        @endif

        <div class="arbol-texto">
            @if($puedeEditar)
                <a href="{{ route('admin.categorias.edit', $categoria) }}" class="arbol-nombre">{{ $categoria->nombre }}</a>
            @else
                <span class="arbol-nombre">{{ $categoria->nombre }}</span>
            @endif
            @if($ocultaPor)
                <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">
                    <i class="bi bi-eye-slash"></i>
                    {{ $ocultaPor->id === $categoria->id ? 'Oculta' : 'Oculta por «' . $ocultaPor->nombre . '»' }}
                </span>
            @endif
            @php
                // Las propias; las de la madre no se repiten: se ven en su fila.
                $pide = $categoria->caracteristicas->map(function ($c) { return $c->nombre . ($c->pivot->obligatoria ? ' (obligatoria)' : ''); })->implode(', ');
                $detalle = array_filter([
                    $pide !== '' ? 'Pide: ' . $pide : null,
                    $hijas->count() ? $hijas->count() . ($hijas->count() === 1 ? ' subcategoría' : ' subcategorías') : null,
                ]);
            @endphp
            @if($detalle)
                <div class="arbol-detalle">{{ implode(' · ', $detalle) }}</div>
            @endif
        </div>

        <span class="arbol-cuenta {{ $cantidad === 0 ? 'vacio' : '' }}" title="Productos en esta categoría y sus subcategorías">
            {{ number_format($cantidad, 0, ',', '.') }} {{ $cantidad === 1 ? 'producto' : 'productos' }}
        </span>

        @if($puedeEditar)
            <div class="arbol-acciones">
                @if($admiteHijas)
                    <a href="{{ route('admin.categorias.create', ['parent_id' => $categoria->id]) }}" class="btn btn-sm btn-light"
                       title="Crear una subcategoría dentro de «{{ $categoria->nombre }}»">
                        <i class="bi bi-plus-lg"></i>
                    </a>
                @endif
                <div class="dropdown">
                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false" title="Más acciones">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="{{ route('admin.categorias.edit', $categoria) }}"><i class="bi bi-pencil me-2"></i>Editar</a></li>
                        @if($admiteHijas)
                            <li><a class="dropdown-item" href="{{ route('admin.categorias.create', ['parent_id' => $categoria->id]) }}"><i class="bi bi-node-plus me-2"></i>Crear subcategoría</a></li>
                        @endif
                        <li>
                            <form action="{{ route('admin.categorias.visibilidad', $categoria) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="dropdown-item">
                                    @if($categoria->marcadaVisible())
                                        <i class="bi bi-eye-slash me-2"></i>Ocultar en la tienda
                                    @else
                                        <i class="bi bi-eye me-2"></i>Mostrar en la tienda
                                    @endif
                                </button>
                            </form>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('admin.categorias.mover', $categoria) }}" method="POST">
                                @csrf
                                <input type="hidden" name="direccion" value="arriba">
                                <button type="submit" class="dropdown-item" {{ $esPrimero ? 'disabled' : '' }}><i class="bi bi-arrow-up me-2"></i>Subir</button>
                            </form>
                        </li>
                        <li>
                            <form action="{{ route('admin.categorias.mover', $categoria) }}" method="POST">
                                @csrf
                                <input type="hidden" name="direccion" value="abajo">
                                <button type="submit" class="dropdown-item" {{ $esUltimo ? 'disabled' : '' }}><i class="bi bi-arrow-down me-2"></i>Bajar</button>
                            </form>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            @if($hijas->count())
                                <button type="button" class="dropdown-item text-muted" disabled title="Primero mové o eliminá sus subcategorías">
                                    <i class="bi bi-trash me-2"></i>Eliminar (tiene subcategorías)
                                </button>
                            @else
                                <form action="{{ route('admin.categorias.destroy', $categoria) }}" method="POST"
                                      data-confirmar="¿Eliminar la categoría «{{ $categoria->nombre }}»?"
                                      data-confirmar-detalle="{{ $cantidad ? 'Sus ' . $cantidad . ($cantidad === 1 ? ' producto no se borra: deja' : ' productos no se borran: dejan') . ' de estar en esta categoría.' : 'No tiene productos.' }}"
                                      data-confirmar-boton="Sí, eliminar">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Eliminar</button>
                                </form>
                            @endif
                        </li>
                    </ul>
                </div>
            </div>
        @endif
    </div>

    @if($admiteHijas)
        <ul class="arbol-lista" data-nivel="{{ $nivel + 1 }}">
            @foreach($hijas as $hija)
                @include('admin.categorias.partials.categoria-item', [
                    'categoria' => $hija,
                    'nivel'     => $nivel + 1,
                    'esPrimero' => $loop->first,
                    'esUltimo'  => $loop->last,
                ])
            @endforeach
        </ul>
    @endif
</li>
