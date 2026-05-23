@extends('layouts.app')

@section('title', $producto->descripcion)

@php $maxStock = $producto->estaDisponible() ? $producto->stockMaximo() : null; @endphp

@push('meta')
@php
    $ogUrl   = url()->current();
    $ogTitle = $producto->descripcion;
    $ogDesc  = $producto->id_proveedor ? 'Código: ' . $producto->id_proveedor : $producto->descripcion;
    $ogImage = $producto->imagen_url ?? url('/img/no-image.svg');
@endphp
<meta property="og:type"        content="product">
<meta property="og:url"         content="{{ $ogUrl }}">
<meta property="og:title"       content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDesc }}">
<meta property="og:image"       content="{{ $ogImage }}">
<meta property="og:image:width" content="600">
<meta property="og:image:height" content="600">
<meta name="twitter:card"       content="summary_large_image">
<meta name="twitter:title"      content="{{ $ogTitle }}">
<meta name="twitter:image"      content="{{ $ogImage }}">
@endpush

@section('content')
    @include('tienda.partials.producto-show-desktop')
    @include('tienda.partials.producto-show-mobile')
@endsection

@if($producto->estaDisponible())
@push('styles')
<style>
@media (max-width: 767.98px) {
    body { padding-bottom: 68px; }
}
</style>
@endpush
@endif

@push('scripts')
<script>
(function () {
    var maxStock = {{ $maxStock ?? 'null' }};
    var urlAgregar = '{{ route('carrito.agregar', $producto) }}';
    var descripcion = '{{ addslashes($producto->descripcion) }}';

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
    }

    // Sincronizar puntos indicadores del carousel desktop
    var carouselDesktop = document.getElementById('carousel-producto-desktop');
    if (carouselDesktop) {
        var dotsWrap = carouselDesktop.nextElementSibling;
        carouselDesktop.addEventListener('slid.bs.carousel', function (e) {
            if (!dotsWrap) return;
            dotsWrap.querySelectorAll('button[data-bs-slide-to]').forEach(function (dot) {
                var idx = parseInt(dot.getAttribute('data-bs-slide-to'));
                dot.style.backgroundColor = idx === e.to ? '#555' : '#ccc';
            });
        });
    }

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
