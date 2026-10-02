@extends('layouts.app')

@section('title', $producto->meta_title)
@section('meta_description', $producto->meta_description)
@section('meta_image', $producto->imagen_url ?: App\Services\SeoService::imagenPorDefecto())
@section('meta_type', 'product')
{{-- Canónica explícita: se puede llegar acá con cualquier slug (resuelve por el id). --}}
@section('meta_canonical', $producto->url())

@php
    $maxStock = $producto->estaDisponible() ? $producto->stockMaximo() : null;
    // Compartidos por la ficha desktop y la mobile, y por sus partials.
    $galeria = $producto->galeria();
    $etiquetasVisibles = $producto->etiquetas->where('visible_usuarios', true);
    $mostrarProveedor = $producto->proveedor && App\Models\Configuracion::mostrarProveedor();
@endphp

@push('schema')
<script type="application/ld+json">{!! App\Services\SeoService::jsonLd(App\Services\SeoService::productSchema($producto)) !!}</script>
<script type="application/ld+json">{!! App\Services\SeoService::jsonLd(App\Services\SeoService::breadcrumbSchema([
    ['name' => 'Inicio', 'url' => route('tienda.index')],
    ['name' => $producto->descripcion, 'url' => $producto->url()],
])) !!}</script>
@endpush

@section('content')
    @include('tienda.partials.producto-show-desktop')
    @include('tienda.partials.producto-show-mobile')
@endsection

@if($producto->estaDisponible())
@push('styles')
<style>
/* Deja lugar para la barra fija de compra de la ficha mobile. */
@media (max-width: 767.98px) {
    body { padding-bottom: calc(72px + env(safe-area-inset-bottom)); }
}
</style>
@endpush
@endif

@push('scripts')
<script>
(function () {
    var maxStock = {{ $maxStock ?? 'null' }};
    var urlAgregar = '{{ route('carrito.agregar', $producto) }}';
    var descripcion = @json($producto->descripcion);

    // Inicializar cada formulario de agregar (desktop y mobile)
    document.querySelectorAll('.form-agregar').forEach(function (form) {
        var input  = form.querySelector('.input-cantidad');
        var btnDec = form.querySelector('.btn-decrement');
        var btnInc = form.querySelector('.btn-increment');

        function actualizarBotones(val) {
            btnDec.disabled = val <= 1;
            btnInc.disabled = maxStock !== null && val >= maxStock;
        }

        btnDec.addEventListener('click', function () {
            var val = parseInt(input.value) || 1;
            if (val > 1) { input.value = val - 1; actualizarBotones(val - 1); }
        });

        btnInc.addEventListener('click', function () {
            var val = parseInt(input.value) || 1;
            if (maxStock === null || val < maxStock) { input.value = val + 1; actualizarBotones(val + 1); }
        });

        input.addEventListener('input', function () {
            var val = parseInt(this.value) || 1;
            if (maxStock !== null && val > maxStock) { val = maxStock; this.value = val; }
            if (val < 1) { val = 1; this.value = val; }
            actualizarBotones(val);
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var cantidad = parseInt(input.value) || 1;
            if (maxStock !== null && cantidad > maxStock) {
                showToast('Stock máximo: ' + maxStock, 'warning');
                input.value = maxStock;
                actualizarBotones(maxStock);
                return;
            }
            fetch(urlAgregar, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                showToast(data.message, data.success ? (data.warning ? 'warning' : 'success') : 'danger');
                updateCartBadge(data.cantidad_carrito);
            })
            .catch(function () {
                showToast('Error al agregar el producto', 'danger');
            });
        });
    });

    // Inicializar carousels explícitamente para que el touch/swipe funcione desde el primer momento
    var carouselMobileEl = document.getElementById('carousel-producto-mobile');
    if (carouselMobileEl) {
        new bootstrap.Carousel(carouselMobileEl, { touch: true, ride: false });
    }
    var carouselDesktopEl = document.getElementById('carousel-producto-desktop');
    if (carouselDesktopEl) {
        new bootstrap.Carousel(carouselDesktopEl, { touch: true, ride: false });

        // Las miniaturas están fuera del carousel: se marca a mano la activa.
        var miniaturas = document.querySelectorAll('#thumbs-producto-desktop .pdp-thumb');
        carouselDesktopEl.addEventListener('slid.bs.carousel', function (e) {
            miniaturas.forEach(function (miniatura, idx) {
                var activa = idx === e.to;
                miniatura.classList.toggle('activa', activa);
                miniatura.setAttribute('aria-current', activa ? 'true' : 'false');
            });
        });
    }

    // Volver: al listado de donde vino, o a la tienda si se entró directo a la ficha
    document.querySelectorAll('.btn-volver').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (history.length > 1) {
                history.back();
            } else {
                window.location = btn.dataset.volver;
            }
        });
    });

    // Compartir
    document.querySelectorAll('.btn-compartir').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = window.location.href;
            if (navigator.share) {
                navigator.share({ title: descripcion, url: url });
            } else if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url)
                    .then(function () { showToast('Link copiado al portapapeles', 'success'); })
                    .catch(function () { copiarFallback(url); });
            } else {
                copiarFallback(url);
            }
        });
    });

    function copiarFallback(url) {
        var el = document.createElement('textarea');
        el.value = url;
        el.style.cssText = 'position:fixed;opacity:0;pointer-events:none;';
        document.body.appendChild(el);
        el.select();
        try {
            document.execCommand('copy');
            showToast('Link copiado al portapapeles', 'success');
        } catch (e) {
            showToast('No se pudo copiar el link', 'danger');
        }
        document.body.removeChild(el);
    }
})();
</script>
@endpush
