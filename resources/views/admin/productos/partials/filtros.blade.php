{{--
    Filtros del listado de productos, con el componente de todos los listados
    (components/filtros). Los entiende App\Support\FiltroDeProductos, que usan
    también Excel y "Agregar categorías" a todos los del filtro. La característica
    con su valor (Marca: Coca-Cola) va aparte, en el slot: el valor sugiere los de
    la característica elegida.
--}}
@php
    $opcionesCategoria = [App\Support\FiltroDeProductos::SIN_CATEGORIA => 'Sin categoría'];
    $sangria = [];
    foreach ($categorias as $categoria) {
        $opcionesCategoria[$categoria->id] = $categoria->nombre;
        $sangria[$categoria->id] = str_repeat('&nbsp;&nbsp;&nbsp;', $categoria->nivel());
    }

    $campos = [
        ['nombre' => 'categoria', 'titulo' => 'Categoría', 'todos' => 'Todas', 'lista' => true, 'opciones' => $opcionesCategoria, 'sangria' => $sangria],
        ['nombre' => 'proveedor', 'titulo' => 'Proveedor', 'lista' => true, 'opciones' => $proveedores->mapWithKeys(function ($p) { return [$p->id => $p->nombre . ($p->activo ? '' : ' (inactivo)')]; })->all()],
        ['nombre' => 'disponible', 'titulo' => 'En la tienda', 'opciones' => ['1' => 'Se muestran', '0' => 'No se muestran']],
        ['nombre' => 'stock', 'titulo' => 'Stock', 'opciones' => ['con' => 'Con stock', 'poco' => 'Poco (1 a ' . App\Support\FiltroDeProductos::POCO_STOCK . ')', 'sin' => 'Sin stock']],
        ['nombre' => 'variantes', 'titulo' => 'Talles, colores…', 'opciones' => ['con' => 'Con variantes', 'sin' => 'Sin variantes']],
        ['nombre' => 'foto', 'titulo' => 'Foto', 'opciones' => ['con' => 'Con foto', 'sin' => 'Sin foto']],
        ['nombre' => 'encargue', 'titulo' => 'Sin stock, ¿se puede pedir?', 'ficha' => 'Se puede pedir sin stock', 'opciones' => ['1' => 'Sí', '0' => 'No']],
        ['nombre' => 'precio', 'titulo' => 'Precio', 'tipo' => 'rango'],
    ];

    // La característica (y su valor) es un solo filtro, con su propia fichita.
    $fichasExtra = [];
    if ($filtro->valor('caracteristica')) {
        $nombre = optional($caracteristicas->firstWhere('id', (int) $filtro->valor('caracteristica')))->nombre ?: '?';
        $resto = array_diff_key($filtro->valores(), ['caracteristica' => true, 'valor' => true]);
        $fichasExtra[] = [
            $nombre . ($filtro->valor('valor') ? ': ' . $filtro->valor('valor') : ' (cargada)'),
            route('admin.productos.index', $resto),
        ];
    }
@endphp

<x-filtros :url="route('admin.productos.index')" buscar="Buscar por nombre o código" titulo="Filtrar productos"
           :campos="$campos" :fichas-extra="$fichasExtra" :cantidad-extra="count($fichasExtra)">
    <div>
        <label for="filtro-caracteristica" class="form-label small fw-semibold mb-1">Característica</label>
        <select name="caracteristica" id="filtro-caracteristica" class="form-select mb-2">
            <option value="">Cualquiera</option>
            @foreach($caracteristicas as $c)
                <option value="{{ $c->id }}" data-url="{{ route('admin.etiquetas.valores', $c) }}" {{ (string) $filtro->valor('caracteristica') === (string) $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
            @endforeach
        </select>
        <input type="text" name="valor" id="filtro-valor" class="form-control" placeholder="Valor, ej.: Coca-Cola (vacío: cualquiera)"
               value="{{ $filtro->valor('valor') }}" autocomplete="off" aria-label="Valor de la característica"
               data-combo="{{ $caracteristicas->isNotEmpty() ? route('admin.etiquetas.valores', (int) ($filtro->valor('caracteristica') ?: $caracteristicas->first()->id)) : '' }}" data-combo-sin-nuevo>
    </div>
</x-filtros>

@include('admin.productos.partials.combo-sugerencias')
<script>
(function () {
    // El valor sugiere los de la característica elegida; sin característica no se usa.
    var caracteristica = document.getElementById('filtro-caracteristica');
    var valor = document.getElementById('filtro-valor');
    function actualizar() {
        var opcion = caracteristica.options[caracteristica.selectedIndex];
        valor.disabled = !caracteristica.value;
        if (opcion && opcion.dataset.url) valor.setAttribute('data-combo', opcion.dataset.url);
    }
    caracteristica.addEventListener('change', function () { valor.value = ''; actualizar(); });
    actualizar();
})();
</script>
