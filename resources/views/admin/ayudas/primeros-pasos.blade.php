{{-- Ayuda: Primeros pasos. Los 5 pasos para armar la tienda, con el botón para ir a cada uno (si la persona puede). --}}
@php
    $puede = function ($permiso) { return auth()->check() && auth()->user()->puede($permiso); };
    $pasos = [
        ['bi-sliders', 'Poné tus datos', 'El nombre de tu tienda, tu logo, tu color y el WhatsApp donde te llegan los pedidos.', 'admin.configuraciones.index', 'Ir a Ajustes', 'configuraciones.ver'],
        ['bi-diagram-3', 'Armá tus estantes', 'Las categorías: Bebidas, Almacén, Limpieza. Son los botones que tus clientes van a tocar.', 'admin.categorias.index', 'Ir a Categorías', 'categorias.ver'],
        ['bi-box-seam', 'Cargá tus productos', 'De a uno, o muchos juntos con Excel. Cada uno en su estante, con su precio y su foto.', 'admin.productos.create', 'Cargar un producto', 'productos.crear'],
        ['bi-shop', 'Mirá tu tienda', 'Abrila como la ven tus clientes y probá comprar algo.', 'tienda.index', 'Ver mi tienda', null],
        ['bi-bag-check', 'Atendé los pedidos', 'Cuando alguien compra te escribe por WhatsApp y el pedido queda en Pedidos. Confirmalo y se descuenta el stock.', 'admin.pedidos.index', 'Ir a Pedidos', 'pedidos.ver'],
    ];
@endphp
<h6 class="ayuda-titulo">Tu tienda en 5 pasos</h6>
@foreach($pasos as $i => [$icono, $titulo, $texto, $ruta, $boton, $permiso])
    <div class="ayuda-item">
        <span class="ayuda-icono">{{ $i + 1 }}</span>
        <div>
            <strong>{{ $titulo }}.</strong><br>{{ $texto }}
            @if($permiso === null || $puede($permiso))
                <div class="mt-1">
                    <a href="{{ route($ruta) }}" class="small" @if($ruta === 'tienda.index') target="_blank" @endif>
                        <i class="bi {{ $icono }}"></i> {{ $boton }}
                    </a>
                </div>
            @endif
        </div>
    </div>
@endforeach

<div class="ayuda-ejemplo">
    <div class="fw-semibold mb-1"><i class="bi bi-lightbulb text-warning"></i> En cada pantalla</div>
    Arriba a la derecha está el botón <em>Cómo funciona</em>: abre la ayuda de esa pantalla al costado, para leerla mientras hacés las cosas.
</div>
