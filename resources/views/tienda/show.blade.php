@extends('layouts.app')

{{-- El nombre del producto y la tienda, como cualquier ficha de comercio. --}}
@section('title', $producto->meta_title . ' | ' . App\Models\Configuracion::nombreTienda())
@section('meta_description', $producto->meta_description)
@section('meta_image', $producto->imagen_url ?: App\Services\SeoService::imagenPorDefecto())
@section('meta_type', 'product')
{{-- Canónica explícita: se puede llegar acá con cualquier slug (resuelve por el id). --}}
@section('meta_canonical', $producto->url())

@php $maxStock = $producto->estaDisponible() ? $producto->stockMaximo() : null; @endphp

@push('schema')
<script type="application/ld+json">{!! App\Services\SeoService::jsonLd(App\Services\SeoService::productSchema($producto)) !!}</script>
<script type="application/ld+json">{!! App\Services\SeoService::jsonLd(App\Services\SeoService::breadcrumbSchema(array_merge(
    [['name' => 'Inicio', 'url' => route('tienda.index')]],
    array_map(function ($c) { return ['name' => $c->nombre, 'url' => $c->url()]; }, $migas),
    [['name' => $producto->descripcion, 'url' => $producto->url()]]
))) !!}</script>
@endpush

@section('content')
    @include('tienda.partials.producto-show-desktop')
    @include('tienda.partials.producto-show-mobile')
@endsection

{{-- Lugar para la barra de compra fija del celular, que sólo existe si se puede comprar. --}}
@if($producto->estaDisponible())
@push('styles')
<style>
@media (max-width: 767.98px) {
    body { padding-bottom: 72px; }
}
</style>
@endpush
@endif

@php
    $datosSelector = $selector
        ? ['ejes' => $selector->ejes, 'variantes' => $selector->variantes, 'queElegir' => $selector->queElegir()]
        : null;
@endphp

@push('scripts')
<script>
(function () {
    var maxStock = {{ $maxStock ?? 'null' }};
    var urlAgregar = @json(route('carrito.agregar', $producto));
    var descripcion = @json($producto->descripcion);
    var SELECTOR = @json($datosSelector);
    var elegida = null;          // la variante elegida (Talle M), si el producto tiene
    var refrescos = [];          // cada formulario se vuelve a dibujar al cambiar el máximo

    // ── Variantes: botones de Talle, Color… (escritorio y celular, sincronizados) ──
    if (SELECTOR) {
        var elegido = {};   // eje => valor

        function coincide(variante, ejeLibre, valorLibre) {
            return SELECTOR.ejes.every(function (eje) {
                var buscado = eje.id === ejeLibre ? valorLibre : elegido[eje.id];
                return buscado === undefined || variante.valores[eje.id] === buscado;
            });
        }

        function escapar(texto) {
            var div = document.createElement('div');
            div.textContent = texto;
            return div.innerHTML;
        }

        function badge(clase, icono, texto) {
            return '<span class="badge ' + clase + '"><i class="bi ' + icono + '"></i> ' + texto + '</span>';
        }

        function pintarSelector() {
            document.querySelectorAll('.opcion-variante').forEach(function (boton) {
                var eje = parseInt(boton.dataset.eje, 10), valor = parseInt(boton.dataset.valor, 10);
                var activo = elegido[eje] === valor;
                // Se puede elegir si con lo ya elegido en los demás hay alguna que se pida.
                var posible = SELECTOR.variantes.some(function (v) { return v.sePide && coincide(v, eje, valor); });
                boton.classList.toggle('btn-primary', activo);
                boton.classList.toggle('btn-outline-secondary', !activo);
                boton.classList.toggle('text-decoration-line-through', !posible);
                boton.setAttribute('aria-pressed', activo ? 'true' : 'false');
                boton.title = posible ? '' : 'Sin stock';
            });

            SELECTOR.ejes.forEach(function (eje) {
                var opcion = eje.opciones.filter(function (o) { return o.id === elegido[eje.id]; })[0];
                document.querySelectorAll('.variante-elegida[data-eje="' + eje.id + '"]').forEach(function (el) {
                    el.textContent = opcion ? opcion.valor : 'elegí una opción';
                });
            });

            var completa = SELECTOR.ejes.every(function (eje) { return elegido[eje.id] !== undefined; });
            elegida = completa ? (SELECTOR.variantes.filter(function (v) { return coincide(v); })[0] || null) : null;

            document.querySelectorAll('.input-variante').forEach(function (campo) { campo.value = elegida ? elegida.id : ''; });

            if (elegida) {
                maxStock = elegida.sePide ? elegida.stock : 0;
                document.querySelectorAll('.ficha-precio, .ficha-precio-movil').forEach(function (el) { el.textContent = elegida.precio; });
                var html = elegida.hayStock
                    ? badge('bg-success-subtle text-success-emphasis', 'bi-check-circle', 'En stock (' + elegida.cantidad + ' disponibles)')
                    : (elegida.sePide
                        ? badge('bg-warning-subtle text-warning-emphasis', 'bi-clock', 'Sin stock ahora: lo encargamos para vos')
                        : badge('bg-danger-subtle text-danger-emphasis', 'bi-x-circle', 'Sin stock en ' + escapar(elegida.nombre)));

                document.querySelectorAll('.ficha-stock').forEach(function (el) { el.innerHTML = html; });
            }
            refrescos.forEach(function (f) { f(); });

            var agotada = !!(elegida && !elegida.sePide);
            document.querySelectorAll('.form-agregar button[type="submit"]').forEach(function (boton) {
                boton.disabled = agotada;
                boton.title = agotada ? 'No queda stock de ' + elegida.nombre : '';
            });
        }

        document.querySelectorAll('.opcion-variante').forEach(function (boton) {
            boton.addEventListener('click', function () {
                elegido[parseInt(boton.dataset.eje, 10)] = parseInt(boton.dataset.valor, 10);
                pintarSelector();
            });
        });

        // Una sola opción en cada característica: ya viene elegida.
        SELECTOR.ejes.forEach(function (eje) {
            if (eje.opciones.length === 1) elegido[eje.id] = eje.opciones[0].id;
        });
        setTimeout(pintarSelector, 0);
    }

    // Inicializar cada formulario de agregar (desktop y mobile)
    document.querySelectorAll('.form-agregar').forEach(function (form) {
        var input  = form.querySelector('.input-cantidad');
        var btnDec = form.querySelector('.btn-decrement');
        var btnInc = form.querySelector('.btn-increment');

        function actualizarBotones(val) {
            btnDec.disabled = val <= 1;
            btnInc.disabled = maxStock !== null && val >= maxStock;
        }

        refrescos.push(function () {
            var val = parseInt(input.value) || 1;
            if (maxStock !== null && maxStock > 0 && val > maxStock) { val = maxStock; input.value = val; }
            if (maxStock !== null) { input.max = Math.max(maxStock, 1); } else { input.removeAttribute('max'); }
            actualizarBotones(val);
        });

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
            if (SELECTOR && !elegida) {
                showToast('Elegí ' + SELECTOR.queElegir + ' antes de agregarlo.', 'warning');
                return;
            }
            if (elegida && !elegida.sePide) {
                showToast('No queda stock de ' + elegida.nombre + '.', 'warning');
                return;
            }
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

    // Sincronizar puntos indicadores del carousel desktop (el aspecto, en tienda.css)
    var carouselDesktop = document.getElementById('carousel-producto-desktop');
    if (carouselDesktop) {
        var dotsWrap = carouselDesktop.nextElementSibling;
        carouselDesktop.addEventListener('slid.bs.carousel', function (e) {
            if (!dotsWrap) return;
            dotsWrap.querySelectorAll('button[data-bs-slide-to]').forEach(function (dot) {
                var idx = parseInt(dot.getAttribute('data-bs-slide-to'));
                dot.classList.toggle('activo', idx === e.to);
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
