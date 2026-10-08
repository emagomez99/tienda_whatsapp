{{--
    "En qué orden se ven los productos" de una categoría (ver App\Support\OrdenDeProductos).
    Con "Elijo cuáles van primero": una lista que se arma buscando productos de la
    categoría y se ordena arrastrando. Los que no están en la lista van después, por nombre.
    Recibe $categoria y, al editar, $destacados.
--}}
@php
    $ordenActual = old('orden_productos', $categoria->orden_productos ?: App\Support\OrdenDeProductos::NOMBRE);
    $destacados  = $destacados ?? collect();
@endphp

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-sort-down"></i> Orden de los productos en la tienda</div>
    <div class="card-body">
        <label for="orden_productos" class="form-label">¿Cuáles se ven primero?</label>
        <select class="form-select @error('orden_productos') is-invalid @enderror" id="orden_productos" name="orden_productos" style="max-width: 360px;">
            @foreach(App\Support\OrdenDeProductos::OPCIONES as $valor => $texto)
                <option value="{{ $valor }}" {{ $ordenActual === $valor ? 'selected' : '' }}>{{ $texto }}</option>
            @endforeach
        </select>
        @error('orden_productos')<div class="invalid-feedback">{{ $message }}</div>@enderror

        <div id="orden-manual" class="mt-3 {{ $ordenActual === App\Support\OrdenDeProductos::MANUAL ? '' : 'd-none' }}">
            @if($categoria->exists)
                <input type="hidden" name="destacados_enviados" value="1">
                <p class="small text-muted mb-2">
                    Buscá los productos que querés mostrar primero (por ejemplo, las ofertas) y ordenalos arrastrando
                    <i class="bi bi-grip-vertical"></i>. El resto aparece después, por nombre.
                </p>

                <div class="position-relative mb-2" style="max-width: 480px;">
                    <input type="search" class="form-control" id="buscar-destacado" placeholder="Buscar un producto de esta categoría…" autocomplete="off" aria-label="Buscar un producto para destacar">
                    <div class="list-group position-absolute w-100 shadow-sm d-none" id="resultados-destacado" style="z-index: 20; max-height: 280px; overflow-y: auto;"></div>
                </div>

                <ol class="list-group list-group-numbered" id="lista-destacados" style="max-width: 480px;">
                    @foreach($destacados as $producto)
                        <li class="list-group-item d-flex align-items-center gap-2" data-id="{{ $producto->id }}">
                            <i class="bi bi-grip-vertical text-muted destacado-agarre" style="cursor: grab;" title="Arrastrar"></i>
                            <span class="flex-grow-1">{{ $producto->descripcion }} @if($producto->id_proveedor)<small class="text-muted">({{ $producto->id_proveedor }})</small>@endif</span>
                            <input type="hidden" name="destacados[]" value="{{ $producto->id }}">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 quitar-destacado" title="Quitar de la lista" aria-label="Quitar de la lista"><i class="bi bi-x-lg"></i></button>
                        </li>
                    @endforeach
                </ol>
                <p class="small text-muted mt-2 mb-0 {{ $destacados->isEmpty() ? '' : 'd-none' }}" id="destacados-vacio">Todavía no elegiste ninguno: mientras tanto se ven por nombre.</p>
            @else
                <p class="small text-muted mb-0">Después de crear la categoría vas a poder elegir qué productos van primero.</p>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var select = document.getElementById('orden_productos');
    var manual = document.getElementById('orden-manual');
    select.addEventListener('change', function () {
        manual.classList.toggle('d-none', select.value !== @json(App\Support\OrdenDeProductos::MANUAL));
    });

    var lista = document.getElementById('lista-destacados');
    if (!lista) return;

    var buscador   = document.getElementById('buscar-destacado');
    var resultados = document.getElementById('resultados-destacado');
    var vacio      = document.getElementById('destacados-vacio');
    var url        = @json($categoria->exists ? route('admin.categorias.productos-para-destacar', $categoria) : '');
    var espera     = null;

    Sortable.create(lista, { handle: '.destacado-agarre', animation: 150 });

    function actualizarVacio() {
        vacio.classList.toggle('d-none', lista.children.length > 0);
    }

    function yaEsta(id) {
        return !!lista.querySelector('[data-id="' + id + '"]');
    }

    function agregar(producto) {
        if (yaEsta(producto.id)) return;
        var li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center gap-2';
        li.dataset.id = producto.id;
        li.innerHTML = '<i class="bi bi-grip-vertical text-muted destacado-agarre" style="cursor: grab;" title="Arrastrar"></i>'
            + '<span class="flex-grow-1"></span>'
            + '<input type="hidden" name="destacados[]">'
            + '<button type="button" class="btn btn-sm btn-link text-danger p-0 quitar-destacado" title="Quitar de la lista" aria-label="Quitar de la lista"><i class="bi bi-x-lg"></i></button>';
        li.querySelector('span').textContent = producto.descripcion + (producto.codigo ? ' (' + producto.codigo + ')' : '');
        li.querySelector('input').value = producto.id;
        lista.appendChild(li);
        actualizarVacio();
    }

    lista.addEventListener('click', function (e) {
        var boton = e.target.closest('.quitar-destacado');
        if (!boton) return;
        boton.closest('li').remove();
        actualizarVacio();
    });

    function cerrarResultados() {
        resultados.classList.add('d-none');
        resultados.innerHTML = '';
    }

    buscador.addEventListener('input', function () {
        clearTimeout(espera);
        var texto = buscador.value.trim();
        if (texto.length < 2) { cerrarResultados(); return; }
        espera = setTimeout(function () {
            fetch(url + '?q=' + encodeURIComponent(texto), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (productos) {
                    resultados.innerHTML = '';
                    if (!productos.length) {
                        var nada = document.createElement('div');
                        nada.className = 'list-group-item small text-muted';
                        nada.textContent = 'No hay productos de esta categoría con ese nombre.';
                        resultados.appendChild(nada);
                    }
                    productos.forEach(function (p) {
                        var item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action';
                        item.textContent = p.descripcion + (p.codigo ? ' (' + p.codigo + ')' : '') + (yaEsta(p.id) ? ' — ya está' : '');
                        item.disabled = yaEsta(p.id);
                        item.addEventListener('click', function () {
                            agregar(p);
                            buscador.value = '';
                            cerrarResultados();
                            buscador.focus();
                        });
                        resultados.appendChild(item);
                    });
                    resultados.classList.remove('d-none');
                });
        }, 250);
    });

    // Enter en el buscador no manda el formulario.
    buscador.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); }
        if (e.key === 'Escape') { cerrarResultados(); }
    });
    document.addEventListener('click', function (e) {
        if (!resultados.contains(e.target) && e.target !== buscador) cerrarResultados();
    });
})();
</script>
@endpush
