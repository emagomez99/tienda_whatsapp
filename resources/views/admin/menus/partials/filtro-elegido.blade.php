{{--
    Un filtro elegido en el formulario de menú. Se usa para los que vienen guardados
    y, con $etiqueta = null, como plantilla de los que se agregan en el navegador.
--}}
<li class="list-group-item py-2 px-2" data-id="{{ optional($etiqueta)->id }}">
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-secondary badge-num flex-shrink-0">1</span>
        <span class="flex-grow-1 small fw-semibold text-truncate filtro-nombre">{{ optional($etiqueta)->nombre }}</span>
        {{-- Una etiqueta oculta no se muestra como filtro en la tienda (Menu::getEtiquetasFiltro). --}}
        <span class="badge bg-secondary-subtle text-secondary-emphasis flex-shrink-0 filtro-oculta {{ $etiqueta && !$etiqueta->visible_usuarios ? '' : 'd-none' }}"
              title="La etiqueta está oculta: el cliente no ve este filtro. Se muestra desde Etiquetas, con el ojo.">
            <i class="bi bi-eye-slash"></i> oculta
        </span>
        <div class="form-check form-switch mb-0 flex-shrink-0" title="Si está prendido, el desplegable ofrece «Todos» además de cada valor.">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   name="filtros_todos[{{ optional($etiqueta)->id }}]" id="todos_{{ optional($etiqueta)->id }}"
                   {{ $permitirTodos ? 'checked' : '' }}>
            <label class="form-check-label small text-muted" for="todos_{{ optional($etiqueta)->id }}">Ofrecer «Todos»</label>
        </div>
        <div class="btn-group btn-group-sm flex-shrink-0">
            <button type="button" class="btn btn-outline-secondary" data-accion="subir" title="Subir"><i class="bi bi-arrow-up"></i></button>
            <button type="button" class="btn btn-outline-secondary" data-accion="bajar" title="Bajar"><i class="bi bi-arrow-down"></i></button>
            <button type="button" class="btn btn-outline-danger" data-accion="quitar" title="Quitar"><i class="bi bi-x"></i></button>
        </div>
    </div>
    <input type="hidden" name="filtros_etiquetas[]" value="{{ optional($etiqueta)->id }}">
</li>
