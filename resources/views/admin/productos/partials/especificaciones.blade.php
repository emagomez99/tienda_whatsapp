{{--
    Especificaciones del producto (clave → valor libres), compartido por alta y edición.

    Recibe $especificacionesIniciales: [{clave, valor}]. Envía especificaciones[i][clave]
    y especificaciones[i][valor], igual que antes.

    Arranca sin filas si el producto no tiene ninguno: una fila vacía que nadie pidió
    parecía un dato obligatorio. Sin filas no viaja nada y el servidor lo toma como
    lista vacía, que es justo lo que hay (DatosDeProducto::conEspecificaciones ya
    descartaba las filas vacías, así que el resultado es el mismo que antes).

    Avisa cuando una clave repite una etiqueta del producto ("Marca" como especificación
    cuando Marca ya es etiqueta): el dato queda dos veces en la ficha y, como
    especificación, no sirve para filtrar. Sólo detecta nombres iguales o casi iguales;
    sinónimos como "Estado" y "Condición" no los puede adivinar.
--}}
@php
    $especificacionesIniciales = array_values(array_filter($especificacionesIniciales, function ($e) {
        return is_array($e) && (trim($e['clave'] ?? '') !== '' || trim($e['valor'] ?? '') !== '');
    }));
@endphp
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Otros datos de este producto
            @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.especificaciones'), 'lugar' => 'right', 'grande' => true])
        </h5>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2 {{ empty($especificacionesIniciales) ? '' : 'd-none' }}" id="especificaciones-vacio">
            No hace falta: sólo si querés anotar algo más, como el envase (Retornable) o una edición especial (Navidad).
        </p>
        <div id="especificaciones-container">
            @foreach($especificacionesIniciales as $i => $espec)
                @include('admin.productos.partials.especificacion-fila', ['i' => $i, 'clave' => $espec['clave'] ?? '', 'valor' => $espec['valor'] ?? ''])
            @endforeach
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="agregar-especificacion">
            <i class="bi bi-plus-lg"></i> Agregar un dato
        </button>
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
    var vacio      = document.getElementById('especificaciones-vacio');

    function mostrarVacio() {
        vacio.classList.toggle('d-none', contenedor.querySelector('.especificacion-row') !== null);
    }

    function agregar() {
        var html = document.getElementById('plantilla-especificacion').innerHTML.replace(/__I__/g, siguiente++);
        var envoltorio = document.createElement('div');
        envoltorio.innerHTML = html.trim();
        var fila = envoltorio.firstElementChild;
        contenedor.appendChild(fila);
        mostrarVacio();
        fila.querySelector('.especificacion-clave').focus();
    }

    document.getElementById('agregar-especificacion').addEventListener('click', agregar);

    // Quitar: la fila desaparece aunque sea la última (quedarse sin ninguna es
    // válido; "Agregar un dato" sigue a mano).
    contenedor.addEventListener('click', function (e) {
        var boton = e.target.closest('.btn-eliminar-especificacion');
        if (!boton) return;
        boton.closest('.especificacion-row').remove();
        mostrarVacio();
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
        aviso.appendChild(document.createTextNode('«' + etiqueta.dataset.nombre + '» ya es una característica de este producto: cargala ahí, así sirve para filtrar y no se repite en la ficha. '));
        var ir = document.createElement('a');
        ir.setAttribute('role', 'button');
        ir.className = 'fw-semibold';
        ir.textContent = 'Ir a la característica';
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
