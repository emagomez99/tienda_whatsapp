<?php

/**
 * Textos de ayuda de los formularios de producto (alta y edición).
 *
 * Viven acá y no en las vistas porque los dos formularios tienen los mismos campos:
 * cuando el texto estaba escrito en cada blade, la ayuda existía sólo en edición y
 * faltaba justo en el alta, que es donde más se necesita.
 *
 * Se usan con el partial admin.productos.partials.ayuda:
 *     @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.precio')])
 */

return [

    'ayuda' => [

        'id_proveedor' => 'Código interno del proveedor. Aparece en el detalle del producto y se usa en búsquedas. El botón ↺ genera uno automático con el prefijo del proveedor.',

        'proveedor' => 'Quién te provee este producto. Sirve para el código automático y para filtrar el listado.',

        'moneda' => 'Moneda en la que se le cobra al cliente. Es la que se muestra en la tienda junto al precio.',

        'precio' => "Precio de venta al público. Podés fijarlo vos o dejar que salga del costo de compra más un margen. Solo se muestra en la tienda si la configuración 'Mostrar precios' está activa.",

        'compra' => 'Cuánto te cuesta el producto y en qué moneda te lo cobra el proveedor. Es opcional: sirve para calcular el precio de venta por margen y para que el sistema lo actualice solo cuando cambia la cotización.',

        'venta' => 'Cuánto le cobrás al cliente. Con "Precio fijo" el valor lo ponés vos y no se mueve. Con "Por margen" lo calcula el sistema a partir del costo de compra, convertido a la moneda de venta.',

        'margen_ganancia' => 'Cuánto le sumás al costo, en porcentaje. Con un costo de 100 y un margen de 40, el precio de venta es 140. El sistema lo recalcula solo cada vez que actualizás la cotización de las monedas involucradas.',

        // El alta permite escribir el stock; la edición no (se ajusta con movimientos).
        'stock_inicial' => 'Cantidad con la que arranca el producto. Después no se edita directamente: se modifica con ajustes, para que cada entrada y salida quede registrada en el historial.',

        'stock_edicion' => "El stock no se edita directamente. Usá 'Ajustar' para registrar entradas o salidas con trazabilidad completa en el historial.",

        'disponible' => 'Si está desactivado, el producto no aparece en la tienda aunque tenga stock.',

        'por_encargue' => "Permite que el cliente lo solicite aunque no haya stock. Aparece con el aviso 'Disponible por encargue' en la tienda.",

        'seo' => 'Si dejás estos campos vacíos, se usa el nombre del producto como título y un resumen de la descripción detallada como meta description.',
        'galeria' => 'La primera imagen es la principal: la que se ve en el listado y en Google. Las demás aparecen en el carrusel de la ficha. Arrastrá para cambiar el orden, o usá ★ para pasar una al primer lugar. Se recomienda fondo blanco o transparente.',

        'categorias' => 'El estante donde va el producto: Remeras, Zapatillas, Mochilas. Puede estar en más de uno. Si elegís uno de adentro (Ropa › Remeras), aparece también en el de afuera (Ropa) sin marcarlo.',
        'etiquetas' => 'Los datos del producto que piden sus categorías (Talle, Color, Marca…) y que la tienda usa en los filtros. Cuáles se piden se define en cada categoría; las marcadas con * son obligatorias. Al escribir el valor se sugieren los que ya existen, con cuántos productos los usan: elegir uno de esos evita duplicados como "Asus" y "Asuz".',

        'especificaciones' => 'Datos sueltos que sólo tiene este producto y no hace falta filtrar: el nombre lo escribís vos. Se muestran en la ficha. Ej: Peso → 1,75 kg, Incluye → Cargador. Si es un dato que tienen todos los de la categoría (Marca, Talle), va en Características.',

    ],

    // Textos del bloque de precio (admin.productos.partials.card-precio).
    'precio' => [

        'compra_opcional' => 'Si no llevás el costo, dejalo vacío y cargá directamente el precio de venta.',

        'calculado_en_servidor' => 'Se recalcula al guardar y cada vez que cambie la cotización.',

        'faltan_datos' => 'Completá la moneda y el precio de compra, la moneda de venta y el margen para ver el precio.',

    ],

];
