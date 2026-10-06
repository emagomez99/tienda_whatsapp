{{--
    Especificaciones del producto (clave → valor libres), compartido por alta y edición.

    Recibe $especificacionesIniciales: [{clave, valor}]. Envía especificaciones[i][clave]
    y especificaciones[i][valor], igual que antes.

    Avisa cuando una clave repite una etiqueta del producto ("Marca" como especificación
    cuando Marca ya es etiqueta): el dato queda dos veces en la ficha y, como
    especificación, no sirve para filtrar. Sólo detecta nombres iguales o casi iguales;
    sinónimos como "Estado" y "Condición" no los puede adivinar.
--}}
@php
    $especificacionesIniciales = array_values(array_filter($especificacionesIniciales, function ($e) {
        return is_array($e) && (trim($e['clave'] ?? '') !== '' || trim($e['valor'] ?? '') !== '');
    }));
    if (empty($especificacionesIniciales)) {
        $especificacionesIniciales = [['clave' => '', 'valor' => '']];
    }
@endphp
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Especificaciones
            @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.especificaciones'), 'lugar' => 'right', 'grande' => true])
        </h5>
        <button type="button" class="btn btn-sm btn-outline-primary" id="agregar-especificacion">
            <i class="bi bi-plus"></i> Agregar
        </button>
    </div>
    <div class="card-body">
        <div id="especificaciones-container">
            @foreach($especificacionesIniciales as $i => $espec)
                @include('admin.productos.partials.especificacion-fila', ['i' => $i, 'clave' => $espec['clave'] ?? '', 'valor' => $espec['valor'] ?? ''])
            @endforeach
        </div>
    </div>
</div>

<template id="plantilla-especificacion">
    @include('admin.productos.partials.especificacion-fila', ['i' => '__I__', 'clave' => '', 'valor' => ''])
</template>

@push('scripts')
<script>
(function () {
    var contenedor = document.getElementById('especificaciones-container');
    var siguiente  = {{ count($especificacionesIniciales) }};

    function agregar() {
        var html = document.getElementById('plantilla-especificacion').innerHTML.replace(/__I__/g, siguiente++);
        var envoltorio = document.createElement('div');
        envoltorio.innerHTML = html.trim();
        var fila = envoltorio.firstElementChild;
        contenedor.appendChild(fila);
        fila.querySelector('.especificacion-clave').focus();
    }

    document.getElementById('agregar-especificacion').addEventListener('click', agregar);

    // Quitar: si es la única fila se vacía en vez de desaparecer, así siempre queda
    // dónde escribir.
    contenedor.addEventListener('click', function (e) {
        var boton = e.target.closest('.btn-eliminar-especificacion');
        if (!boton) return;
        var fila = boton.closest('.especificacion-row');
        if (contenedor.querySelectorAll('.especificacion-row').length > 1) {
            fila.remove();
        } else {
            fila.querySelectorAll('input').forEach(function (campo) { campo.value = ''; });
            fila.querySelector('.especificacion-aviso').innerHTML = '';
        }
        contenedor.dispatchEvent(new Event('input', { bubbles: true }));   // vista previa
    });

    // ── Clave que repite una etiqueta ───────────────────────────────────────

    function normalizar(texto) {
        return (texto || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
    }

    function distancia(a, b) {
        var fila = [];
        for (var j = 0; j <= b.length; j++) fila[j] = j;
        for (var i = 1; i <= a.length; i++) {
            var previo = fila[0];
            fila[0] = i;
            for (var k = 1; k <= b.length; k++) {
                var tmp = fila[k];
                fila[k] = Math.min(fila[k] + 1, fila[k - 1] + 1, previo + (a[i - 1] === b[k - 1] ? 0 : 1));
                previo = tmp;
            }
        }
        return fila[b.length];
    }

    // Las etiquetas del producto son las filas que arma partials/etiquetas.
    function etiquetaParecida(clave) {
        var buscada = normalizar(clave);
        if (buscada.length < 3) return null;
        var tolerancia = buscada.length <= 5 ? 1 : 2;
        var encontrada = null;
        document.querySelectorAll('.etiqueta-row').forEach(function (fila) {
            if (!encontrada && distancia(buscada, normalizar(fila.dataset.nombre)) <= tolerancia) {
                encontrada = fila;
            }
        });
        return encontrada;
    }

    function revisar(fila) {
        var aviso = fila.querySelector('.especificacion-aviso');
        var etiqueta = etiquetaParecida(fila.querySelector('.especificacion-clave').value);
        aviso.innerHTML = '';
        if (!etiqueta) return;

        aviso.appendChild(document.createElement('i')).className = 'bi bi-exclamation-triangle me-1';
        aviso.appendChild(document.createTextNode('«' + etiqueta.dataset.nombre + '» ya es una etiqueta de este producto: cargala ahí, así sirve para filtrar y no se repite en la ficha. '));
        var ir = document.createElement('a');
        ir.setAttribute('role', 'button');
        ir.className = 'fw-semibold';
        ir.textContent = 'Ir a la etiqueta';
        ir.addEventListener('click', function () {
            var campo = etiqueta.querySelector('.etiqueta-valor');
            campo.scrollIntoView({ block: 'center', behavior: 'smooth' });
            campo.focus();
        });
        aviso.appendChild(ir);
    }

    contenedor.addEventListener('input', function (e) {
        if (e.target.classList.contains('especificacion-clave')) revisar(e.target.closest('.especificacion-row'));
    });
    contenedor.addEventListener('change', function (e) {
        if (e.target.classList.contains('especificacion-clave')) revisar(e.target.closest('.especificacion-row'));
    });

    // Al cargar, y cuando cambian las etiquetas (otro proveedor), se revisan todas.
    function revisarTodas() {
        contenedor.querySelectorAll('.especificacion-row').forEach(revisar);
    }
    var proveedor = document.getElementById('proveedor_id');
    if (proveedor) proveedor.addEventListener('change', function () { setTimeout(revisarTodas, 0); });
    revisarTodas();
})();
</script>
@endpush
