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

        'proveedor' => 'Quién te provee este producto. Determina qué etiquetas son obligatorias al cargarlo.',

        'moneda' => 'Moneda en la que se expresa el precio. Si el precio no aplica, dejá sin seleccionar.',

        'precio' => "Precio de venta al público. Solo se muestra en la tienda si la configuración 'Mostrar precios' está activa.",

        // El alta permite escribir el stock; la edición no (se ajusta con movimientos).
        'stock_inicial' => 'Cantidad con la que arranca el producto. Después no se edita directamente: se modifica con ajustes, para que cada entrada y salida quede registrada en el historial.',

        'stock_edicion' => "El stock no se edita directamente. Usá 'Ajustar' para registrar entradas o salidas con trazabilidad completa en el historial.",

        'disponible' => 'Si está desactivado, el producto no aparece en la tienda aunque tenga stock.',

        'por_encargue' => "Permite que el cliente lo solicite aunque no haya stock. Aparece con la etiqueta 'Disponible por encargue' en la tienda.",

        'seo' => 'Si dejás estos campos vacíos, se usa el nombre del producto como título y un resumen de la descripción detallada como meta description.',

        'imagen_principal' => 'Imagen que aparece en el listado de productos y en la vista de detalle. Se recomienda fondo blanco o transparente.',

        'imagenes_adicionales' => 'Se muestran en el carrusel de la vista detallada del producto. Podés agregar varias antes de guardar. Con ★ podés promover cualquiera como imagen principal.',

        'etiquetas' => 'Categorizan el producto y habilitan filtros en la tienda. Cada etiqueta tiene un nombre y un valor específico para este producto. Ej: Marca → Toyota, Aplicación → Filtro de aceite.',

        'especificaciones' => 'Tabla de características técnicas. Aparece en el detalle del producto. Ej: Peso → 1.75 kg, Material → Acero inoxidable.',

    ],

];
