{{-- Ayuda: Preguntas frecuentes. Cada respuesta corta, con el ejemplo del supermercado. Se despliegan de a una. --}}
@php
    $preguntas = [
        ['¿Categoría o característica?',
         'La <strong>categoría</strong> es el estante donde va el producto (Gaseosas). La <strong>característica</strong> es un dato del producto (Marca: Coca-Cola). Si el cliente entra por ahí desde el menú, es categoría; si sirve para elegir dentro del estante, es característica. La marca la tiene casi cualquier producto: por eso es característica.',
         'categorias'],
        ['¿Cómo cargo un producto que viene en talles o colores?',
         'Es un solo producto con variantes. En el producto, en <em>Variantes</em>, tocá <em>Agregar</em>, elegí Talle o Color, tildá los que tenés y poné cuántas hay de cada uno.',
         'variantes'],
        ['¿Por qué un producto no aparece en la tienda?',
         'Revisá tres cosas: que tenga prendido <em>Mostrar en tienda</em>; que tenga stock (o <em>Se puede pedir igual</em>), salvo que en Ajustes muestres los productos sin stock; y que esté en un estante visible. Sin categoría no aparece al navegar por estantes, pero sí en el buscador.',
         'productos'],
        ['¿Cómo cambio el precio de muchos productos juntos?',
         'Con Excel: <em>Exportá</em> tus productos, cambiá la columna Precio y subí la planilla. Antes de cambiar nada te muestra qué va a pasar.',
         'importar'],
        ['¿Cómo me llega un pedido?',
         'El cliente arma su carrito y te lo manda por WhatsApp. El pedido queda en <em>Pedidos</em> como pendiente. Al confirmarlo se descuenta el stock; si lo cancelás, vuelve.',
         'pedidos'],
        ['¿Qué productos se ven primero en un estante?',
         'Lo elegís en cada categoría: por nombre, los más nuevos, el más barato, el más caro, o los que vos quieras primero (por ejemplo, las ofertas).',
         'categorias'],
        ['¿Cómo agrego a alguien de mi equipo?',
         'En <em>Usuarios</em>, creá su usuario y elegí su perfil: el perfil dice qué puede hacer (por ejemplo, cargar productos pero no ver los ajustes).',
         'usuarios'],
    ];
@endphp
<div class="d-flex flex-column gap-2">
    @foreach($preguntas as $i => [$pregunta, $respuesta, $tema])
        <details class="ayuda-pregunta" data-buscar="{{ mb_strtolower(strip_tags($pregunta . ' ' . $respuesta), 'UTF-8') }}">
            <summary>{{ $pregunta }}</summary>
            <div class="mt-2">{!! $respuesta !!}</div>
            @if(auth()->check() && \App\Support\TemasDeAyuda::delUsuario($tema, auth()->user()))
                <a href="{{ route('admin.ayuda.show', $tema) }}" class="small d-inline-block mt-1">Más sobre esto <i class="bi bi-arrow-right"></i></a>
            @endif
        </details>
    @endforeach
</div>
