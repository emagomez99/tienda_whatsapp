{{--
    Vista previa en vivo de cómo queda el producto en el listado de la tienda.
    Compartida por el alta y la edición; $producto viene sólo en edición.

    Replica la card de tienda/partials/productos-grid.blade.php y sus reglas: qué
    badge lleva según stock y encargue, si se muestra el precio, y si el producto
    aparece o no en la tienda (Producto::scopeVisiblesEnTienda). Es sólo una ayuda
    visual: lo que vale es lo que guarda el servidor.

    Lee los campos del formulario por id y nombre, así que si se renombran en las
    vistas hay que actualizar el script de abajo.
--}}
@php
    $productoPrevio = isset($producto) ? $producto : null;
    $colorTienda    = App\Models\Configuracion::colorPrimario();
@endphp
<div class="vista-previa-producto" id="vista-previa-producto"
     style="--preview-primario: {{ $colorTienda->hex() }}; --preview-primario-texto: {{ $colorTienda->textoLegible()->hex() }};">
    <div class="small text-muted mb-2"><i class="bi bi-eye"></i> Así se ve en la tienda</div>

    <div id="vp-aviso" class="alert alert-warning small py-2 px-3 mb-2 d-none">
        <i class="bi bi-eye-slash me-1"></i><span id="vp-aviso-texto"></span>
    </div>

    <div class="card vp-card" id="vp-card">
        <div class="position-relative" style="height: 170px;">
            <img id="vp-imagen" src="/img/no-image.svg" alt=""
                 style="width: 100%; height: 100%; object-fit: contain; background: #fff;"
                 onerror="this.onerror=null;this.src='/img/no-image.svg';">
            <span id="vp-encargue" class="badge bg-warning text-dark position-absolute d-none" style="top: 9px; left: 9px; font-size: .72rem;">
                <i class="bi bi-clock"></i> Por encargue
            </span>
            <div id="vp-agotado" class="vp-agotado d-none"><span>Sin stock</span></div>
        </div>
        <div class="card-body d-flex flex-column px-3 pt-3 pb-2">
            <h6 class="vp-nombre mb-2" id="vp-nombre"></h6>
            <div class="d-flex flex-wrap gap-1 mb-2" id="vp-etiquetas"></div>
            <div class="text-muted mb-2" id="vp-especificaciones" style="font-size: .78rem; line-height: 1.6;"></div>
            @if(App\Models\Configuracion::mostrarPrecios())
                <div class="mt-auto pt-1">
                    <span class="fs-5 fw-bold vp-precio" id="vp-precio"></span>
                </div>
            @else
                <div class="mt-auto pt-1 small text-muted fst-italic">Los precios están ocultos en la tienda.</div>
            @endif
        </div>
        <div class="card-footer border-0 bg-transparent px-3 pb-3 pt-1">
            <span id="vp-boton-agregar" class="btn btn-sm w-100 vp-boton"><i class="bi bi-cart-plus"></i> Agregar</span>
            <span id="vp-boton-agotado" class="btn btn-light btn-sm w-100 text-muted d-none" style="border: 1px solid #e9ecef; pointer-events: none;">
                <i class="bi bi-x-circle me-1"></i> Sin stock
            </span>
        </div>
    </div>
</div>

@push('styles')
<style>
    .vista-previa-producto { position: sticky; top: 76px; }
    .vp-card { border: none; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 6px rgba(0,0,0,.07); }
    .vp-card.vp-oculto { opacity: .45; }
    .vp-nombre { font-weight: 600; line-height: 1.35; min-height: 1.35em; word-break: break-word; }
    .vp-precio { color: var(--preview-primario); }
    .vp-boton { background: var(--preview-primario); color: var(--preview-primario-texto); pointer-events: none; }
    .vp-agotado {
        position: absolute; inset: 0; background: rgba(255,255,255,.6);
        display: flex; align-items: center; justify-content: center;
    }
    .vp-agotado span { background: rgba(0,0,0,.48); color: #fff; padding: .25rem .9rem; border-radius: 4px; }
    .vp-etiqueta { background: rgba(13,202,240,.12); color: #0a6a77; font-size: .7rem; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var form = document.getElementById('form-producto');
    if (!form) return;

    // Datos del servidor que el formulario no tiene.
    var DATOS = {
        etiquetasVisibles: @json($etiquetas->where('visible_usuarios', true)->pluck('id')->map(function ($id) { return (string) $id; })->values()),
        // Valores ocultos desde el panel, por etiqueta, en su forma normalizada
        // (EtiquetaValor::normalizar): tampoco se muestran en la tienda.
        valoresOcultos: @json(App\Models\EtiquetaValor::where('visible', false)->get(['etiqueta_id', 'normalizado'])->groupBy('etiqueta_id')->map(function ($valores) { return $valores->pluck('normalizado'); })),
        simbolos: @json($monedas->mapWithKeys(function ($m) { return [$m->id => $m->simbolo]; })),
        mostrarSinStock: @json(App\Models\Configuracion::mostrarProductosSinStock()),
        // En edición el stock no es un campo: se ajusta con movimientos.
        stockGuardado: @json($productoPrevio ? (int) $productoPrevio->stock : null)
    };

    var el = function (id) { return document.getElementById(id); };

    function texto(valor) {
        var span = document.createElement('span');
        span.textContent = valor;
        return span.innerHTML;
    }

    // Mismo formato que Producto::getPrecioConMonedaAttribute (number_format con 2 decimales).
    function formatearPrecio(numero, simbolo) {
        return simbolo + numero.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function precio() {
        var margen = el('modo-margen');
        if (margen && margen.checked) {
            var calculado = el('precio-calculado');
            return calculado ? calculado.textContent.trim() : '—';
        }
        var moneda  = el('moneda_id');
        var simbolo = moneda && moneda.value && DATOS.simbolos[moneda.value] ? DATOS.simbolos[moneda.value] : '$';
        var valor   = parseFloat((el('precio') || {}).value);
        return formatearPrecio(isNaN(valor) ? 0 : valor, simbolo);
    }

    // La principal es la primera de la galería (partials/galeria).
    function imagen() {
        var primera = form.querySelector('#galeria-items .galeria-item img');
        return primera ? primera.getAttribute('src') : '/img/no-image.svg';
    }

    function stock() {
        if (DATOS.stockGuardado !== null) return DATOS.stockGuardado;
        var valor = parseInt((el('stock') || {}).value, 10);
        return isNaN(valor) ? 0 : valor;
    }

    function etiquetas() {
        var html = '';
        // Cada fila es una etiqueta (ver partials/etiquetas): su id y nombre van en la fila.
        form.querySelectorAll('.etiqueta-row').forEach(function (fila) {
            var id    = fila.dataset.etiquetaId;
            var valor = fila.querySelector('.etiqueta-valor');
            if (!id || !valor || !valor.value.trim()) return;
            if (DATOS.etiquetasVisibles.indexOf(id) === -1) return;
            var ocultos = DATOS.valoresOcultos[id] || [];
            if (ocultos.indexOf(valor.value.trim().replace(/\s+/g, ' ').toLowerCase()) !== -1) return;
            html += '<span class="badge fw-normal vp-etiqueta">' + texto(fila.dataset.nombre) + ': ' + texto(valor.value.trim()) + '</span>';
        });
        return html;
    }

    function especificaciones() {
        var html = '';
        form.querySelectorAll('.especificacion-row').forEach(function (fila) {
            var clave = fila.querySelector('.especificacion-clave');
            var valor = fila.querySelector('.especificacion-valor');
            if (!clave || !valor || !clave.value.trim() || !valor.value.trim()) return;
            html += texto(clave.value.trim()) + ': <strong>' + texto(valor.value.trim()) + '</strong><br>';
        });
        return html;
    }

    function actualizar() {
        var nombre      = (el('descripcion') || {}).value || '';
        var disponible  = el('disponible') ? el('disponible').checked : true;
        var porEncargue = el('por_encargue') ? el('por_encargue').checked : false;
        var conStock    = stock() > 0;
        var sePuedePedir = disponible && (conStock || porEncargue);   // Producto::estaDisponible

        el('vp-nombre').innerHTML = nombre.trim() ? texto(nombre) : '<span class="text-muted fw-normal fst-italic">Nombre del producto</span>';
        el('vp-imagen').src = imagen();
        el('vp-etiquetas').innerHTML = etiquetas();
        el('vp-especificaciones').innerHTML = especificaciones();
        if (el('vp-precio')) el('vp-precio').textContent = precio();

        el('vp-encargue').classList.toggle('d-none', conStock || !porEncargue);
        el('vp-agotado').classList.toggle('d-none', conStock || porEncargue);
        el('vp-boton-agregar').classList.toggle('d-none', !sePuedePedir);
        el('vp-boton-agotado').classList.toggle('d-none', sePuedePedir);

        // Producto::scopeVisiblesEnTienda
        var motivo = null;
        if (!disponible) {
            motivo = 'No se muestra en la tienda: está marcado como no disponible.';
        } else if (!conStock && !porEncargue && !DATOS.mostrarSinStock) {
            motivo = 'No se muestra en la tienda: no tiene stock y la tienda oculta los productos sin stock.';
        }
        el('vp-aviso').classList.toggle('d-none', !motivo);
        el('vp-aviso-texto').textContent = motivo || '';
        el('vp-card').classList.toggle('vp-oculto', !!motivo);
    }

    // Un solo listener para todo el formulario: también alcanza a las filas de
    // etiquetas y especificaciones que se agregan después. El "click" cubre los
    // botones que cambian campos ocultos sin disparar eventos.
    var pendiente = false;
    function programar() {
        if (pendiente) return;
        pendiente = true;
        setTimeout(function () { pendiente = false; actualizar(); }, 30);
    }
    ['input', 'change', 'click'].forEach(function (evento) { form.addEventListener(evento, programar); });

    actualizar();
})();
</script>
@endpush
