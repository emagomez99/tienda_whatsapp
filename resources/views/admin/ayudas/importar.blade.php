{{-- Ayuda de Importar productos con Excel. Ver components/ayuda. Lenguaje simple y ejemplo de supermercado: ver memoria feedback-ux-simple. --}}
<h6 class="ayuda-titulo">Para qué sirve</h6>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-file-earmark-spreadsheet"></i></span>
    <div><strong>Cargar o cambiar muchos productos de una vez.</strong><br>Por ejemplo, las 200 gaseosas del catálogo de tu proveedor, o los precios nuevos de todo el almacén.</div>
</div>

<h6 class="ayuda-titulo">Paso a paso</h6>
<div class="ayuda-item">
    <span class="ayuda-icono">1</span>
    <div><strong>Bajá la plantilla.</strong><br>Elegí la categoría, por ejemplo «Gaseosas»: la planilla trae una columna por cada dato que pide (Sabor, Contenido…). Los que tienen * son obligatorios.</div>
</div>
<div class="ayuda-item">
    <span class="ayuda-icono">2</span>
    <div><strong>Completala en Excel.</strong><br>Un producto por fila. La segunda hoja, «Cómo completarla», explica cada columna con un ejemplo.</div>
</div>
<div class="ayuda-item">
    <span class="ayuda-icono">3</span>
    <div><strong>Subila y mirá qué va a pasar.</strong><br>Antes de cambiar nada te mostramos cuántos productos se crean, y en los que ya tenés, qué cambia: «Precio: de 1.000 a 1.250». Los que quedan igual no se tocan.</div>
</div>
<div class="ayuda-item">
    <span class="ayuda-icono">4</span>
    <div><strong>Aplicala.</strong><br>Se carga por partes, con una barra de avance. Si se corta, tocá «Seguir» y retoma donde quedó.</div>
</div>

<div class="ayuda-ejemplo">
    <div class="fw-semibold mb-1">El código es la clave</div>
    Si una fila tiene el código de un producto que ya tenés (por ejemplo COCA-15), ese producto se <strong>actualiza</strong>. Si la fila no tiene código, lo buscamos por el <strong>nombre exacto</strong>. Si no lo tenés, se <strong>crea</strong>.
    <div class="mt-1">¿Querés cambiarle el nombre a un producto que no tiene código? Ponele primero un código: sin código, un nombre distinto es un producto nuevo.</div>
</div>

<h6 class="ayuda-titulo">Para no equivocarte</h6>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-eraser"></i></span>
    <div><strong>Una celda vacía no borra nada.</strong><br>Para cambiar sólo los precios, alcanza con las columnas Código y Precio. Los productos que no están en la planilla no se tocan.</div>
</div>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-arrow-repeat"></i></span>
    <div><strong>Exportar y volver a subir.</strong><br>Desde Productos › Excel › Exportar bajás tus productos en la misma planilla: cambiás lo que quieras y la subís acá.</div>
</div>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-clipboard-check"></i></span>
    <div><strong>Copiá en vez de escribir.</strong><br>La planilla trae las hojas «Categorías» y «Valores» con lo que ya tenés cargado, por ejemplo «Bebidas &gt; Gaseosas» o la marca «Arcor». Copialos de ahí y no se crean repetidos.</div>
</div>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-exclamation-triangle"></i></span>
    <div><strong>Si hay filas con algo mal, no se cargan.</strong><br>Las bajás en un Excel que dice qué corregir en cada una, las arreglás y subís sólo esas.</div>
</div>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-percent"></i></span>
    <div><strong>Costo y Margen %.</strong><br>Si un producto calcula su precio por margen, cambiá el Costo (por ejemplo, con la lista nueva de tu proveedor) y el precio se recalcula solo.</div>
</div>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-box-seam"></i></span>
    <div><strong>El stock es cuánto tenés ahora.</strong><br>Si cambia, la diferencia queda anotada en el historial del producto, con el nombre de la planilla.</div>
</div>
