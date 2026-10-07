{{--
    Categorías del producto, como en Tiendanube: chips con las elegidas y un panel
    lateral con búsqueda y casillas para elegir, y "Crear categoría" ahí mismo.

    Al elegir una subcategoría, el producto aparece también en las de arriba (se
    calcula al mostrar la tienda): no hace falta marcarlas, y el panel lo indica.

    Recibe $categorias (Categoria::enOrdenDeArbol()) y $seleccionadas (ids).
    Envía categorias[] con los ids elegidos.
--}}
<div class="card mb-4" id="card-categorias">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Categorías
            @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.categorias')])
        </h5>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2" id="categorias-chips"></div>
        <div id="categorias-ocultas"></div>
        @error('categorias')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        @error('categorias.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>
</div>

@include('admin.categorias.partials.panel-elegir', ['panelId' => 'panel-categorias', 'titulo' => 'Categorías'])

@push('scripts')
<script>
(function () {
    var elegidas = @json(array_values(array_map('intval', $seleccionadas)));
    var chips    = document.getElementById('categorias-chips');
    var ocultas  = document.getElementById('categorias-ocultas');

    var panel = new PanelDeCategorias(document.getElementById('panel-categorias'), elegidas, function (nuevas) {
        elegidas = nuevas;
        pintarChips();
        chips.dispatchEvent(new Event('change', { bubbles: true }));   // aviso de cambios sin guardar
    });

    function pintarChips() {
        chips.innerHTML = '';
        ocultas.innerHTML = '';

        elegidas.forEach(function (id) {
            var c = panel.porId(id);
            if (!c) return;

            var chip = document.createElement('span');
            chip.className = 'categoria-chip';
            chip.appendChild(document.createTextNode(c.camino.replace(/ > /g, ' › ')));
            var quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.title = 'Quitar';
            quitar.setAttribute('aria-label', 'Quitar ' + c.nombre);
            quitar.innerHTML = '<i class="bi bi-x-lg"></i>';
            quitar.addEventListener('click', function () {
                elegidas = elegidas.filter(function (e) { return e !== id; });
                panel.fijar(elegidas);
                pintarChips();
                chips.dispatchEvent(new Event('change', { bubbles: true }));
            });
            chip.appendChild(quitar);
            chips.appendChild(chip);

            var campo = document.createElement('input');
            campo.type = 'hidden';
            campo.name = 'categorias[]';
            campo.value = id;
            ocultas.appendChild(campo);
        });

        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn btn-sm btn-outline-primary';
        boton.setAttribute('data-bs-toggle', 'offcanvas');
        boton.setAttribute('data-bs-target', '#panel-categorias');
        boton.innerHTML = (elegidas.length ? '<i class="bi bi-pencil"></i>' : '<i class="bi bi-plus-lg"></i>') + ' Elegir categorías';
        chips.appendChild(boton);

        if (!elegidas.length) {
            var vacio = document.createElement('span');
            vacio.className = 'text-muted small';
            vacio.textContent = panel.hay()
                ? 'Todavía no está en ninguna categoría.'
                : 'Todavía no hay categorías: creá la primera desde el panel.';
            chips.insertBefore(vacio, boton);
        }
    }

    pintarChips();
})();
</script>
@endpush
