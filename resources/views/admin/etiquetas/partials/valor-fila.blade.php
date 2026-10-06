{{--
    Un valor de etiqueta con sus acciones. Se usa en el árbol (index) y en la página
    de la etiqueta (show).

    Recibe: $valor (EtiquetaValor con asignaciones_count), $puedeEditar (bool).

    El renombre es un formulario en línea que aparece con el lápiz: sin modales ni
    prompt(), y si el nombre nuevo ya existe el servidor vuelve proponiendo unirlos.
--}}
<div class="valor-fila d-flex align-items-center gap-2 py-1 px-2 rounded">
    @if($puedeEditar)
        <form action="{{ route('admin.etiqueta-valores.visibilidad', $valor) }}" method="POST" class="m-0">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-sm btn-link p-0 {{ $valor->visible ? 'text-success' : 'text-muted' }}"
                    title="{{ $valor->visible ? 'Se muestra en la tienda. Click para ocultarlo.' : 'Oculto en la tienda. Click para mostrarlo.' }}">
                <i class="bi {{ $valor->visible ? 'bi-eye' : 'bi-eye-slash' }}"></i>
            </button>
        </form>
    @else
        <i class="bi {{ $valor->visible ? 'bi-eye text-success' : 'bi-eye-slash text-muted' }}"></i>
    @endif

    <div class="valor-vista flex-grow-1 d-flex align-items-center gap-2 min-w-0">
        <span class="text-truncate {{ $valor->visible ? '' : 'text-muted text-decoration-line-through' }}">{{ $valor->valor }}</span>
        <span class="badge rounded-pill {{ $valor->asignaciones_count ? 'bg-light text-secondary border' : 'bg-warning-subtle text-warning-emphasis' }}">
            {{ $valor->asignaciones_count }} {{ $valor->asignaciones_count === 1 ? 'producto' : 'productos' }}
        </span>
    </div>

    @if($puedeEditar)
        <form action="{{ route('admin.etiqueta-valores.update', $valor) }}" method="POST"
              class="valor-renombrar flex-grow-1 d-none m-0">
            @csrf
            @method('PUT')
            <div class="input-group input-group-sm">
                <input type="text" name="valor" class="form-control" value="{{ $valor->valor }}" maxlength="255" required>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-valor-cancelar><i class="bi bi-x-lg"></i></button>
            </div>
        </form>

        <div class="valor-acciones d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-primary" data-valor-editar title="Renombrar">
                <i class="bi bi-pencil"></i>
            </button>
            @if($valor->asignaciones_count === 0)
                <form action="{{ route('admin.etiqueta-valores.destroy', $valor) }}" method="POST" class="m-0"
                      data-confirmar="¿Borrar «{{ $valor->valor }}»?"
                      data-confirmar-detalle="Ningún producto lo usa."
                      data-confirmar-boton="Sí, borrar">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Borrar: ningún producto lo usa">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            @endif
        </div>
    @endif
</div>
