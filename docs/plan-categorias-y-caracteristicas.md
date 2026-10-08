# Plan: Categorías y Características

> Documento vivo. Se actualiza al cerrar cada tarea (casillas y **Bitácora** al final).
> Para retomar: leer **Estado actual**, después la primera etapa con casillas sin marcar.

## Estado actual

- **Etapas 1 y 2: hechas** y corridas en las 3 tiendas locales (2026-10-09). Quedan de la 1: línea de destino al arrastrar (detalle visual).
- **Limpieza: hecha** (2026-10-10). La migración `2026_10_10_000001_migrar_etiquetas_viejas_y_limpiar` pasa "Categoria"/"Subcategoria" a categorías, pasa las reglas por proveedor a las categorías y recién después borra esas etiquetas y la tabla `proveedor_etiqueta`. Los comandos `categorias:desde-etiquetas` y `caracteristicas:desde-proveedores` se quitaron: los reemplaza la migración.
- **Producción (o una copia nueva de la base de un cliente):** alcanza con `php artisan tenants:migrate`; se puede repetir sobre copias nuevas cuantas veces se quiera y da lo mismo. Después, a mano: Acoples Rapidos (MC-923370) también en Accesorios › Hidráulica. En perfumes, las categorías se crean sueltas (Notebooks, Drones, Paletas): para tenerlas dentro de "Producto" como en local, crear "Producto › …" antes de migrar, o moverlas después arrastrando.
- **Etapa 5 (Excel): hecha** (2026-10-10). Productos › Excel: plantilla por categoría, exportar con los filtros, subir, vista previa y aplicar por partes con barra de avance. Ver la Bitácora.
- **Etapa 4: hecha** (2026-10-11): Marca en tiendas nuevas, orden de productos por categoría, varios valores por característica y variantes con stock propio. Probada en el navegador por un agente en farmacia.
- **Próxima:** Etapa 3 (estado vacío de Categorías y plantillas por rubro).
- **Orden de trabajo:** 0 → 1 → 2 (con migración real de las 2 tiendas en cada una) → 5 (Excel) → 3 → 4.
- **Último commit antes del plan:** `9516afe` (árbol de menús con arrastrar y soltar).

---

## 1. Problema

"Etiquetas" hace hoy tres trabajos distintos y los clientes no entienden qué son:

| Trabajo | Ejemplo actual | Problema |
|---|---|---|
| Decir **qué es** el producto | Etiqueta `Categoria: Notebooks` | Es texto libre, no un árbol; no sirve para navegar |
| Describir **cómo es** | `Marca: Asus`, `Talle: M` | Está bien, pero mezclado con lo anterior |
| Decidir **qué datos lleva** cada producto | Reglas por **proveedor** (no aplica / opcional / obligatoria) | Lo decide el proveedor, y dos proveedores pueden venderte lo mismo; uno puede venderte cosas distintas |

Lo que decide si un producto lleva "Talle" o "Capacidad" es **qué tipo de producto es**, no a quién se le compra.

Referencias de otras plataformas (capturas del 2026-10-07):
- **TiendaNegocio:** todo es un árbol de categorías (hasta Marca › Afnan). Simple, pero no permite datos obligatorios ni filtrar por talle o capacidad.
- **Tiendanube:** árbol de categorías que **es el menú de la tienda**; un producto en **varias** categorías (panel con casillas y búsqueda); **Variantes** aparte (color + tamaño con stock); Marca y Tags como campos. No tiene características por categoría: eso, que oleomc necesita (Fabricante → Aplicación → Modelo), es una ventaja propia.
  - Su ayuda ([cómo asociar productos a una categoría](https://ayuda.tiendanube.com/es_AR/122709-categorias/como-asociar-mis-productos-a-una-categoria)) confirma: hasta **3 niveles**; al asignar una subcategoría el producto **aparece también en la madre**; tres formas de asignar: **en masa desde el listado**, desde el producto, y por planilla (columna "Categorías", varias separadas por coma, niveles con " > ").
  - Su ayuda para [ordenar categorías](https://ayuda.tiendanube.com/es_AR/122709-categorias/como-ordenar-las-categorias-de-mis-productos): arrastrar desde los seis puntos, a la derecha para volverla subcategoría y a la izquierda para volverla principal, línea azul de destino, botón "Guardar cambios", recomiendan no pasar de 3 niveles (experiencia y SEO).
  - Diferencia de implementación elegida: Tiendanube guarda el vínculo con la madre; acá se **calcula** (productos de una categoría = los suyos + los de sus subcategorías). Mismo resultado, sin datos repetidos que puedan desincronizarse.

## 2. Solución elegida (opción B)

Dos conceptos, cada uno con un solo trabajo:

- **Categorías: qué es.** Árbol (Ropa › Remeras, Deportes › Pádel › Paletas). Cada producto pertenece a una.
- **Características: cómo es.** Marca, Talle, Tela, Capacidad, Género. **Cada categoría define cuáles lleva y cuáles son obligatorias**; las subcategorías las heredan.
- **Proveedor:** vuelve a ser sólo comercial (de quién se compra, código, costo). Deja de decidir qué datos lleva el producto.
- **Menú de la tienda:** por defecto **se arma solo con las categorías visibles** (como Tiendanube: "creá categorías y subcategorías que aparecerán en el menú de la tienda"). La pantalla **Menús** queda como opción avanzada para armados especiales ("En stock", "Kits por Modelo", "Ofertas"), y sus menús también pueden apuntar a una categoría.
- **Características globales:** algunas (ej. Marca) aplican a todos los productos sin importar la categoría; se definen una vez.

Frase para el cliente final:
> *La **categoría** es qué es tu producto. Las **características** son lo que lo describe: según la categoría, te pedimos los datos que importan.*

Ejemplo de herencia:
```
Ropa ........................ Talle*, Color
  └ Remeras ................. + Tela
Celulares ................... Marca*, Almacenamiento*, Color
Deportes › Pádel › Paletas .. Marca*, Género, Peso
```

## 2b. Estrategia de migración y lo que viene después

- **Se migra ahora, mientras hay 2 tiendas reales.** Sin período de convivencia: cada etapa migra los datos reales y borra la estructura vieja en el mismo paso. **Objetivo: Etapas 1 y 2 terminadas antes de sumar tiendas nuevas.**
- **Pensado para importar desde Excel (Etapa 5).** Todo lo que se haga en las etapas 1 y 2 tiene que poder usarse desde una carga masiva:
  - Las reglas de guardado de un producto (categorías, características obligatorias, valores, galería) viven en **un servicio**, no en el controlador: el formulario y la importación usan el mismo.
  - Las categorías se pueden resolver por **camino de texto** (`Ropa > Remeras`) y crear si no existen.
  - Las características se resuelven **por nombre** (columna "Marca"), y sus valores pasan por `EtiquetaValor::resolver` (ya unifica mayúsculas y espacios).
  - El producto tiene una **clave para actualizar** desde una planilla (D10).

## 3. Decisiones

Marcar la elegida. Las recomendadas van primero.

| # | Decisión | Opciones | Elegida |
|---|---|---|---|
| D1 | Nombre visible de "Etiquetas" | **Características** (rec.) · Atributos · dejar Etiquetas | ✅ Características |
| D2 | Categorías por producto | **Varias** (rec. desde 2026-10-07, como Tiendanube: una cucha en "Perros" y en "Ofertas"; pide las características de todas) · una | ✅ Varias |
| D3 | Producto sin categoría | **Permitido**, se ve como "Sin categoría" con aviso en el listado (rec.) · obligatorio | ✅ Permitido, con aviso |
| D4 | Profundidad del árbol de categorías | **3 niveles** (rec.: igual que el menú) · libre | ✅ 3 niveles |
| D5 | Nombres en la base | **Mantener** `etiquetas` / `etiqueta_valores` y renombrar sólo la interfaz (rec.: sin migración riesgosa) · renombrar tablas | ✅ Mantener nombres en la base |
| D6 | oleomc: nombre de la categoría de los productos de Hercules | "Kits hidráulicos" · "Kits Hercules" · otro | ✅ Kits hidráulicos |
| D7 | Reglas por proveedor (`proveedor_etiqueta`) | **Se migran y se borran** en la Etapa 2 (rec.) · conviven un tiempo | ✅ Se migran y se borran |
| D8 | Menú de la tienda | **Automático desde las categorías**, con Menús personalizado como opción en Ajustes (rec.) · siempre manual como hoy | ✅ Automático desde categorías |
| D9 | Tiendas que ya tienen menú armado (perfumes, oleomc) | **Siguen con su menú personalizado** hasta que lo cambien (rec.) · pasar a automático | ✅ Conservan su menú |
| D10 | Clave del producto para actualizar desde una planilla | **Código** (`id_proveedor`) **único por tienda** cuando está cargado (rec.: en oleomc los 30.908 ya son únicos) · id interno · otra columna nueva (SKU) | ✅ Código único por tienda |
| D11 | ¿Cuáles son las 2 tiendas reales a migrar? | oleomc + ? (en local también están perfumes y arcor) | ✅ oleomc y perfumes (arcor es de prueba) |

## 4. Modelo de datos

Tablas nuevas (todas por tenant, en `database/migrations/tenant/`):

```
categorias
  id, nombre, slug (único), parent_id → categorias (null = raíz), orden,
  visible (bool), timestamps

categoria_caracteristica
  categoria_id → categorias (cascade), etiqueta_id → etiquetas (cascade),
  obligatoria (bool), orden, timestamps
  unique(categoria_id, etiqueta_id)

categoria_producto
  categoria_id → categorias (cascade), producto_id → productos (cascade)
  unique(categoria_id, producto_id)
  (con D2 = varias; si se eligiera una, sería productos.categoria_id)

etiquetas.global (bool)   → la característica aplica a todos los productos
```

Reglas:
- **Características efectivas de una categoría** = las globales + las propias + las de sus ancestros. Si se repite, manda la más cercana (una subcategoría puede volver obligatoria una opcional del padre).
- **Características de un producto** = la unión de las de todas sus categorías; es obligatoria si lo es en alguna.
- Al elegir una subcategoría en el producto, el producto aparece también en las de arriba (Perros › Camas lista también en Perros) sin tener que marcarlas.
- **Productos de una categoría** = los de ella y los de todas sus subcategorías (para menús y conteos).
- **Visibilidad heredada** (como Tiendanube: "al ocultar esta categoría, las subcategorías asociadas también quedarán ocultas"): una categoría se ve en la tienda sólo si ella y todos sus ancestros están visibles. Se calcula, no se copia a las hijas: al volver a mostrar la madre, cada hija recupera su propio estado.
  - En el panel, una hija oculta por herencia se ve atenuada con "oculta por «Ropa»", y su ojo indica que depende de la madre.
  - Ocultar una categoría **no oculta los productos**: siguen apareciendo en la búsqueda y en sus otras categorías visibles. Lo que desaparece es la categoría del menú automático, del sitemap y su página (`/categoria/{slug}` responde 404).
  - Un menú personalizado que apunta a una categoría oculta queda vacío: se avisa en Menús como hoy con "0 productos".
- Los valores de las características siguen como hoy: `etiqueta_valores`, únicos, ocultables, con "¿Quisiste decir?".

Lo que se reutiliza sin cambios: `EtiquetaValor`, `ProductoEtiqueta` (pivot que unifica valores), árbol de valores, sugerencias con cantidad, `Producto::scopeDelMenu` (se le suma el tipo categoría), árbol de menús con arrastrar y soltar.

## 5. Etapas

Cada etapa queda usable sola, con tests, commiteada y migrada en las 3 tiendas locales antes de seguir.

### Etapa 0: decisiones y diseño (sin código)
- [x] Cerrar las decisiones D1–D11 (tabla de arriba).
- [x] Maqueta de las pantallas (abajo): aprobada.

#### Maqueta

**A. Admin › Categorías** (reemplaza la necesidad de entender "Menús" para la mayoría)
```
Categorías                                                     [+ Crear categoría]
Organizá tus productos. Las categorías visibles arman el menú de la tienda.   (?) Cómo funciona
┌──────────────────────────────────────────────────────────────────────────────┐
│ ⋮⋮ ⌄ Ropa                       Talle*, Color          24 productos    ＋ ⋯   │
│ ┊ ⋮⋮   Remeras                  + Tela                 15 productos    ＋ ⋯   │
│ ┊ ⋮⋮   Pantalones                                       9 productos    ＋ ⋯   │
│ ⋮⋮   Celulares                  Marca*, Almacenamiento*  6 productos    ＋ ⋯   │
│ ⋮⋮   Ofertas  (oculta)                                   3 productos    ＋ ⋯   │
└──────────────────────────────────────────────────────────────────────────────┘
⋯ = Editar · Crear subcategoría · Ver en la tienda · Ocultar · Eliminar
```
- Mismo árbol que Menús hoy: arrastrar (derecha = adentro), plegar, `＋` y `⋯` al pasar el mouse.
- Cada fila muestra qué características pide (las heredadas no se repiten: "+ Tela").
- Sin categorías todavía: pantalla "Categorizá tus productos" con plantillas de ejemplo (Etapa 3).

**B. Editar categoría** (panel o página)
```
Nombre        [Remeras                 ]     Dentro de  [Ropa ▾]     ☑ Visible en la tienda

Características que pide
  De Ropa (heredadas)     Talle  obligatoria · Color  opcional            (gris, no se editan acá)
  Propias                 ⋮⋮ Tela       ( ) opcional (•) obligatoria   ✕
                          [+ Agregar característica ▾]   (elegir una existente o crear "Cuello")

▸ SEO (dirección /categoria/remeras, título y descripción para Google)
```

**C. Producto › sección Categorías** (arriba de Características)
```
Categorías   [Ropa › Remeras ✕] [Ofertas ✕]   ✎ Elegir categorías
                                              ┌ panel lateral ───────────────────┐
                                              │ [Buscar categoría        ]       │
                                              │ ⊕ Crear categoría                │
                                              │ Ropa                        ☐    │
                                              │ Ropa/Remeras                ☑    │
                                              │ Ropa/Pantalones             ☐    │
                                              │ Ofertas                     ☑    │
                                              └──────────────────────────────────┘
Características  (según Ropa › Remeras y Ofertas)
  Talle *  [M          ]   ✓ Valor existente
  Color    [Negro      ]
  Tela     [Algodón    ]
  Marca    [Nik        ]   ⚠ ¿Quisiste decir «Nike» (12 productos)?      ← global
```
- Igual que el componente actual de etiquetas, pero los campos salen de las categorías elegidas (no del proveedor).
- Sin categoría: aviso suave "Elegí una categoría para que te pidamos los datos que importan".

**D. Productos › listado con asignación masiva**
```
[☑] 3 seleccionados   [Asignar categorías ▾] [Quitar categorías ▾]   o  ☐ Seleccionar los 30.902 del filtro
☑  Kit sellos CAT 320    Kits hidráulicos      ...
☑  Kit sellos CAT 330    Kits hidráulicos      ...
⚠ 4 productos sin categoría  [Ver]
```

**E. Menú (formulario), opción nueva en "Qué productos muestra"**
```
○ Ninguno (sólo agrupa)
● Los de una categoría     [Ropa › Remeras ▾]     (incluye sus subcategorías)
○ Los que tienen una característica   [Marca ▾] [Nike]
○ Los de un proveedor
```

**F. Ajustes › Tienda**
```
Menú de la tienda   (•) Se arma con las categorías visibles (recomendado)
                    ( ) Personalizado → Admin › Menús
```
- [x] Revisar con el usuario la migración propuesta para cada tienda (sección 6).

### Etapa 1: Categorías
Objetivo: cada producto tiene una categoría, y los menús pueden apuntar a categorías.

- [x] **Servicio de guardado de producto** (antes que nada): sacar de `ProductoController` las reglas que hoy viven ahí (`validarEtiquetasObligatorias`, `sincronizarEtiquetas`, especificaciones, `sincronizarGaleria`) a un servicio (ej. `App\Services\GuardadoDeProducto`) que reciba datos ya validados y devuelva errores por campo. El controlador sólo orquesta. Lo va a usar la importación desde Excel (Etapa 5). Tests de las reglas en el servicio, sin HTTP.
- [x] Migración `create_categorias_table` + `categoria_producto`.
- [x] `Categoria::resolverCamino('Ropa > Remeras', crear: bool)`: busca por camino (sin distinguir mayúsculas, con el mismo normalizado que los valores) y opcionalmente crea lo que falta. Lo usa el formulario ("Crear categoría" en el panel) y la importación.
- [x] Modelo `Categoria`: `parent`, `children`, `productos`, `linaje()`, `idsConDescendientes()`, `nivel()`; límite de niveles (D4).
- [x] Pantalla **Admin › Categorías**: árbol con arrastrar y soltar (reutilizar el de menús: `ArbolDeMenus` → generalizar o un `ArbolDeCategorias`), ojo de visible, cantidad de productos, `+` para subcategoría, `⋯` con editar y eliminar. Mismas reglas que Tiendanube y que el árbol de menús actual: derecha = meter adentro, izquierda = sacar, máximo 3 niveles.
- [ ] Mejora del árbol (sirve también para Menús): **línea de destino** donde va a caer lo arrastrado, como la línea azul de Tiendanube. Se mantiene el guardado automático al soltar, con aviso y Deshacer (Tiendanube usa un botón "Guardar cambios"; acá se prefiere no perder lo hecho si se sale de la página).
- [x] Alta/edición de categoría (nombre, dentro de, visible). Eliminar: sólo sin productos; si tiene, ofrecer moverlos a otra.
- [x] Formulario de producto: **Categorías** con chips y un panel lateral con búsqueda y casillas, mostrando el camino ("Perros/Camas y cuchas") y "Crear categoría" ahí mismo (como Tiendanube).
- [x] Listado de productos: columna y filtro por categoría; aviso "N productos sin categoría".
- [x] **Asignar categorías en masa** desde el listado (como Tiendanube): casillas por producto + "seleccionar todos los del filtro" (para catálogos grandes como oleomc), acción "Asignar categorías" / "Quitar categorías", y "Crear categoría" en el mismo panel.
- [x] `Producto::scopeEnCategoria(Categoria)` (incluye subcategorías) y `scopeSinCategoria`.
- [x] Menús: nuevo tipo **"Los de una categoría"** (`tipo_enlace = categoria`), en `scopeDelMenu`, en el formulario por bloques, en el contador en vivo y en la vista previa.
- [x] **Menú automático (D8):** ajuste "El menú de la tienda se arma con las categorías"; la tienda (escritorio y celular) lo dibuja desde el árbol de categorías visibles. Las tiendas actuales siguen con su menú personalizado (D9).
- [x] Página de categoría en la tienda: `/categoria/{slug}` con SEO (título, descripción, canónica) y en el sitemap.
- [x] Comando de migración de datos (ver sección 6): crea categorías desde la etiqueta "Categoria"/"Subcategoria", asigna productos y convierte los menús que filtraban por esas etiquetas. Con `--dry-run` que muestra qué haría.
- [ ] Tests: árbol (ciclos, profundidad), productos de una categoría con subcategorías, menú por categoría, comando de migración, **visibilidad heredada** (ocultar la madre oculta las hijas en el menú automático, el sitemap y su página; al mostrarla, cada hija recupera su estado; los productos siguen en la búsqueda).
- [x] Correrlo en perfumes, arcor y oleomc; revisar con el usuario.

### Etapa 2: Características por categoría
Objetivo: lo que hoy decide el proveedor pasa a decidirlo la categoría.

- [x] Migración `create_categoria_caracteristica_table`.
- [x] `Categoria::caracteristicas()` efectivas (con herencia) y `obligatorias()`.
- [x] Pantalla de la categoría: lista de características con obligatoria/opcional, orden, y las heredadas (en gris, "de Ropa").
- [x] Formulario de producto: el componente `partials/etiquetas` pasa a tomar los campos **de la categoría** en vez del proveedor (al cambiar de categoría se rearma, como hoy con el proveedor).
- [x] `ProductoController::validarEtiquetasObligatorias` según la categoría.
- [x] Menú: los filtros en cascada sugieren las características de la categoría elegida.
- [x] Comando de migración: reglas de `proveedor_etiqueta` → `categoria_caracteristica` (sección 6).
- [x] Quitar las reglas por proveedor: tabla `proveedor_etiqueta`, sección "Proveedores" de la edición de etiqueta, `mapEtiquetasObligatorias/Aplicables` en `ProductoController` y `MenuController`, `Proveedor::etiquetas*`.
- [x] Renombrar en la interfaz "Etiquetas" → **Características** (D1): menú lateral, títulos, ayudas (`resources/lang/es/productos.php`), mensajes.
- [x] `ImportHercules`: asignar la categoría de Hercules (D6) a los productos que importa.
- [x] Tests: herencia y "manda la más cercana", validación de obligatorias por categoría, formulario que cambia de campos, migración de reglas. (El rearmado de campos al cambiar de categoría es JavaScript: se verificó en el navegador; los tests cubren el mapa que recibe y la validación del servidor.)

### Etapa 3: que se entienda solo
- [ ] Estado vacío de Categorías: "Categorizá tus productos" con ilustración/ícono y botón, como el competidor.
- [ ] **Plantillas de ejemplo** para crear de un clic, con sus características: Ropa (Talle, Color, Tela), Celulares (Marca, Almacenamiento, Color), Pádel (Marca, Género, Peso), Calzado (Talle, Género), Comida (Peso neto, Sabor).
- [ ] Ayudas "Cómo funciona" en Categorías y Características, con la frase de la sección 2.
- [ ] `tenant:create --seed`: ofrecer una plantilla inicial.
- [ ] Revisar textos de todo el panel con la palabra nueva.

### Etapa 5: Importar productos desde Excel (próxima, después de 1 y 2)
Objetivo: carga y actualización masiva por planilla, con las mismas reglas que el formulario.

- [x] D10: código único por tienda (índice único parcial `WHERE id_proveedor IS NOT NULL`) y aviso en el formulario si se repite.
- [x] Librería: **OpenSpout 3.7** (lee y escribe de corrido; compatible con PHP 7.4). Se probó `maatwebsite/excel` (PhpSpreadsheet) y se descartó: con los 30.917 productos de oleomc tardaba 144 s en exportar y 174 s en leer, con cientos de MB; con OpenSpout, 25 s y 23 s.
- [x] **Plantilla descargable por categoría**: columnas fijas (Código, Nombre, Descripción, Precio, Moneda, Stock, Disponible, Por encargue, Categorías, Imagen 1…N) + **una columna por característica de esa categoría**, marcando las obligatorias. Así el cliente no adivina qué cargar.
- [x] Formato: Categorías como Tiendanube (`Ropa > Remeras, Ofertas`); imágenes por URL (la primera es la principal, vía `GaleriaProducto`); valores de características uno por celda (varios valores: separador a definir junto con la Etapa 4).
- [x] **Vista previa antes de aplicar**: cuántos se crean, cuántos se actualizan (por código), errores por fila ("Fila 12: falta Talle, obligatoria en Remeras"), categorías y valores nuevos que se van a crear, y valores parecidos a existentes ("Asuz" ≈ "Asus") para corregir antes.
- [x] Aplicar en lotes (oleomc tiene 30.917 productos), con el servicio de guardado de la Etapa 1. El stock inicial entra como movimiento de stock, como en el alta.
- [x] Exportar a Excel con el mismo formato (sirve de respaldo y para editar en masa).
- [x] Permiso propio (`productos.importar`) y registro de quién importó qué.
- [x] Tests: plantilla por categoría, vista previa con errores, creación y actualización por código, categorías por camino, valores unificados.

### Etapa 4 (opcional, se decide después)
- [x] Marca como característica global por defecto en tiendas nuevas (`TenantDatabaseSeeder`: global, opcional, visible).
- [x] **Orden de los productos dentro de una categoría**: por nombre, más nuevos, precio (pasado a la moneda base) o "Elijo cuáles van primero" (destacados en `categoria_destacados`, el resto por nombre). `App\Support\OrdenDeProductos`; vale en la página de la categoría, en sus filtros y en un menú que lleva a ella.
- [x] Varios valores por característica (ej. "Talles: S, M, L"; Modelo en oleomc): se prende en la característica (`etiquetas.varios_valores`). `producto_etiqueta` pasa a una fila por valor (único producto+característica+valor). Formulario con fichitas; tienda "Talle: S, M"; Excel con punto y coma ("S; M; L"); no se apaga si hay productos con varios.
- [x] Variantes con stock propio (talle/color que el cliente elige al comprar): `producto_variantes` + `producto_variante_valores`, armadas en el formulario a partir de las características con varios valores (`VariantesDeProducto`). Stock, código y precio propios; el stock del producto es la suma de las activas. Carrito por línea (`LineaDeCarrito`, "12-5"), ficha con botones (`SelectorDeVariantes`), pedidos y movimientos con `variante_id`. Excel: el stock de un producto con variantes no se cambia por planilla (avisa).

## 6. Migración por tienda (datos reales al 2026-10-07)

### perfumes (5 productos)
- Etiqueta "Categoria" (3 valores, 4 productos) → categorías raíz con esos nombres (Notebooks, Drones, …). El producto sin valor queda "Sin categoría".
- Menús `Notebook` (Categoria=Notebooks) y `Drones` (Categoria=Drones) → "Los de una categoría".
- Reglas actuales (Mas Tecnologia: Marca, Condicion, Categoria, Genero obligatorias…; PC One: Marca, Genero obligatorias) → en cada categoría nueva: las características que usan sus productos, **obligatoria si lo era para todos los proveedores de esos productos**. Revisar a mano después (son pocas).
- "Genero" y "Rango de Precio" siguen como características (no son "qué es").

### arcor (4 productos) — tienda de prueba (D11): se migra igual, sin revisión
- No usa "Categoria": todos quedan "Sin categoría".
- Reglas por proveedor (Georgalos: Calorias y Tamaño obligatorias) **no tienen a qué categoría pasar**: se pierden como obligatorias y quedan opcionales. Avisar al usuario; si quiere, crear una categoría (ej. "Golosinas") y marcarlas ahí.

### oleomc (30.917 productos)
- "Categoria" + "Subcategoria" (14 productos) → árbol `Accesorios › Hidráulica`, `Accesorios › Neumática`, `Industrial`.
- Productos de **Hercules** (30.902, sin "Categoria") → categoría nueva **"Kits hidráulicos"** (D6) con **Fabricante\*, Aplicación\*, Modelo\*** (las reglas actuales de Hercules).
- Proveedor MC exigía Categoria y Subcategoria: deja de hacer falta (ahora es la categoría del producto).
- Menús: `Todos` (Categoria=Accesorios) → categoría Accesorios; `Hidráulica`/`Neumática` (Subcategoria=…) → esas categorías. `Kits por Modelo` y `En stock` (por proveedor) pueden quedar igual o pasar a la categoría de Hercules: decidir en la Etapa 1.
- Al final, las etiquetas "Categoria" y "Subcategoria" quedan sin uso: ocultarlas y borrarlas en la limpieza, después de que el usuario lo verifique.

## 7. Cómo retomar

1. Leer **Estado actual** y la **Bitácora**.
2. `git log --oneline -15` para ver lo último.
3. `php vendor/bin/phpunit` (todo verde antes de empezar).
4. Seguir por la primera casilla sin marcar.
5. Al terminar una tarea: marcar la casilla, sumar una línea a la Bitácora y commitear junto con el código.

Datos útiles:
- Código actual de etiquetas/valores: `app/Models/Etiqueta.php`, `EtiquetaValor.php`, `ProductoEtiqueta.php`, `app/Support/EstadoValorEtiqueta.php`.
- Qué muestra un menú: `Producto::scopeDelMenu`, `Menu::linaje`.
- Árbol con arrastrar y soltar para copiar: `resources/views/admin/menus/index.blade.php`, `partials/menu-item.blade.php`, `app/Services/ArbolDeMenus.php`.
- Componente de etiquetas del producto (hoy por proveedor): `resources/views/admin/productos/partials/etiquetas.blade.php`.
- Producción: después de cada etapa, `php artisan tenants:migrate` y `ANALYZE`.

## 8. Bitácora

| Fecha | Qué | Commit |
|---|---|---|
| 2026-10-07 | Plan aprobado (opción B, en etapas) | — |
| 2026-10-07 | Revisado con capturas de Tiendanube: varias categorías por producto (D2), menú automático desde categorías (D8, D9), características globales | — |
| 2026-10-07 | Revisado con la ayuda de Tiendanube: confirma 3 niveles y la madre automática; se suma asignación masiva desde el listado (Etapa 1) y planilla (Etapa 4) | — |
| 2026-10-07 | Ayuda de Tiendanube sobre ordenar: confirma el árbol (derecha/izquierda, 3 niveles); se suma línea de destino (Etapa 1) y orden de productos por categoría (Etapa 4) | — |
| 2026-10-07 | Decisiones D1–D11 cerradas (todas las recomendadas; D6 = Kits hidráulicos; D11 = oleomc y perfumes) | — |
| 2026-10-07 | Se migra ahora (2 tiendas reales, sin convivencia). Se planifica la importación desde Excel (Etapa 5) y se adelanta a la Etapa 1 el servicio de guardado de producto que va a compartir. Nuevas decisiones D10 (clave por código) y D11 (qué tiendas) | — |
| 2026-10-08 | Etapa 1: servicio `GuardadoDeProducto` + `DatosDeProducto` (null = no tocar, [] = vaciar); el formulario lo usa | (este commit) |
| 2026-10-08 | Etapa 1: tablas `categorias` y `categoria_producto`, modelo `Categoria` (camino, niveles, visibilidad heredada, descendientes por CTE recursiva), `Producto::enCategoria` / `sinCategoria`. Ojo: dentro de un modelo `$this->visible` es la propiedad de Eloquent, no la columna (`marcadaVisible()`) | (este commit) |
| 2026-10-08 | Etapa 1: pantalla Admin › Categorías (árbol compartido con Menús vía `admin/partials/arbol-ordenable` + `ArbolOrdenable`), alta/edición/eliminar/ocultar/subir-bajar, permisos `categorias.ver`/`categorias.gestionar`. Eliminar: los productos sólo pierden la categoría; con subcategorías no se puede | (este commit) |
| 2026-10-08 | Etapa 1: categorías en el formulario de producto (chips + panel lateral con búsqueda, "Ya aparece acá por…", crear categoría sin salir: `POST admin/categorias/rapida`); pasan por `GuardadoDeProducto` (`DatosDeProducto::conCategorias`, null = no tocar) | (este commit) |
| 2026-10-08 | Etapa 1: listado de productos con categorías bajo el nombre, filtro (incluye "Sin categoría", con subcategorías) y aviso "N sin categoría". Agregar/quitar categorías en masa: marcados o "todos los del filtro" (`FiltroDeProductos` compartido con el listado; `AsignacionDeCategorias` en una sentencia: 30.917 productos de oleomc en ~0,5 s). Panel de elegir categorías compartido (`admin/categorias/partials/panel-elegir`, `PanelDeCategorias` en JS) | 1e5b234 |
| 2026-10-08 | Etapa 1: menús de tipo "Los de una categoría" (incluye subcategorías; migración del CHECK de `menus.tipo_enlace`, `Menu::TIPOS`/`TIPOS_CON_REGISTRO`), en el formulario, el contador, la lista y la ayuda. No se puede eliminar una categoría que usa un menú | e0ab8f9 |
| 2026-10-08 | Etapa 1: página `/categoria/{slug}` (migas, subcategorías visibles como accesos, búsqueda dentro, título/descripción/canónica; oculta = 404) y en el sitemap. Menú automático: `MenuDeLaTienda` arma el árbol desde los menús o desde las categorías visibles (`OpcionDeMenu`, lo dibujan los 3 componentes); se elige en Admin › Menús ("Con las categorías" / "Armado a mano"). Sin ajuste: automático si la tienda no tiene menús (D9). Los menús armados se conservan | 31540df |
| 2026-10-08 | Etapa 1: comando `categorias:desde-etiquetas --tenant= [--proveedor="Hercules=Kits hidráulicos"] [--dry-run]` (`CategoriasDesdeEtiquetas`). Usa las categorías existentes por nombre (y madre), crea las que faltan, sólo agrega, se puede repetir; convierte los menús y avisa si alguno cambia lo que muestra. `--dry-run` = transacción deshecha. **perfumes migrado** (Notebook y Drones → Producto › …, mismos productos). arcor: nada que migrar. **oleomc pendiente**: el menú Hidráulica pasaría de 12 a 11 (Acoples Rapidos, MC-923370, es Industrial › Hidráulica) | 90a30ae |
| 2026-10-08 | **oleomc migrado** (opción 2 del usuario): Acoples Rapidos (MC-923370) queda en Industrial › Hidráulica y también en Accesorios › Hidráulica; el menú Hidráulica sigue en 12 y "Todos" (Accesorios) pasa de 13 a 14. **perfumes**: la rama de categorías "Marca" se quitó a pedido del usuario (Marca va a ser característica en la Etapa 2); de paso se corrigió la Notebook Lenovo, que tenía la etiqueta Marca = Asus (el menú Notebook › Asus pasa de 2 a 1, que es lo correcto). Categorías de perfumes: Producto › Celulares, Notebooks, Drones, Paletas | — |
| 2026-10-09 | Etapa 2: tabla `categoria_caracteristica` (obligatoria, orden) y `etiquetas.global`. `CaracteristicasPorCategoria` resuelve la herencia (globales + madres + propias; manda la más cercana; en un producto con varias categorías, obligatoria si alguna la exige) y devuelve `Caracteristica` (VO). Formulario de categoría: "Características que pide" (heredadas en gris, propias con opcional/obligatoria, orden, agregar existente o nueva sin duplicar). El árbol muestra qué pide cada una | 1568792 |
| 2026-10-09 | Etapa 2: el formulario de producto arma los campos con las categorías elegidas (y las globales), se rearma al cambiarlas; `GuardadoDeProducto::validarObligatorias` exige las obligatorias de sus categorías (si los datos no traen categorías, las que ya tiene). Ya no mira al proveedor | 3f20e55 |
| 2026-10-09 | Etapa 2: comando `caracteristicas:desde-proveedores --tenant= [--dry-run]` (`CaracteristicasDesdeProveedores`): global obligatoria la que exigen todos los proveedores con productos; por categoría, las que usan sus productos (obligatoria si lo era para todos sus proveedores); lo común de todas las hijas sube a la madre; Categoria/Subcategoria no pasan; avisa las obligatorias sin destino. Nueva columna `etiquetas.obligatoria_global`. **Corrido en las 3 tiendas**: perfumes → Marca global obligatoria, Condicion* en Notebooks/Drones/Paletas (+ Demora envio, Rango de Precio); oleomc → Kits hidráulicos: Aplicacion*, Fabricante*, Modelo*; arcor → Georgalos: Calorias y Tamaño sin destino. Ningún producto quedó sin una obligatoria. Fuera del código las reglas por proveedor (relaciones, sección Proveedores de la etiqueta, mapas en Producto y Menú); **la tabla `proveedor_etiqueta` queda hasta la limpieza** (en producción hay que correr el comando antes). Edición de característica: "Pedirla en todos los productos" (+ obligatoria) y "Qué categorías la piden". Menú: con una categoría elegida, los filtros ofrecen sus características. `import:hercules` pone los productos en "Kits hidráulicos" (la crea con sus 3 obligatorias si no existe) | 214112d |
| 2026-10-09 | Etapa 2: "Etiquetas" → "Características" en todo lo visible (panel, menús, producto, tienda, carrito, WhatsApp, permisos con migración de textos). Rutas, tablas, permisos y código siguen como `etiquetas` (D5). **Pendiente**: limpieza (ocultar/borrar etiquetas Categoria/Subcategoria y tabla `proveedor_etiqueta`, después de que el usuario verifique) | 55f6f24 |
| 2026-10-08 | Tanda 3 (claridad): filtros del listado de productos al elegir; botón "Buscar" con texto; "Quitar los filtros" | 5f94530 |
| 2026-10-08 | Tanda 3: característica global dice "La piden todos los productos (obligatoria/opcional)"; obligatoria/opcional con botones como en la categoría; leyenda del asterisco en el producto | c949110 |
| 2026-10-08 | Tanda 3: "Características" al primer nivel del menú del panel, debajo de Categorías | 9a450cb |
| 2026-10-08 | Tanda 3: botones "+" y "⋯" del árbol (Categorías y Menús) siempre visibles; en celular la cuenta queda en el número y los avisos bajan de renglón | 67c8d6c |
| 2026-10-08 | Tanda 3: textos sin jerga (Letras para los códigos, Código, Dato/Cómo es en Otros datos que arranca sin filas, "Se puede pedir sin stock" / "Sin stock ahora: lo encargamos para vos", pie de Características, "En ninguna (va suelta)") | 557c5eb |
| 2026-10-08 | Tanda 3: título de la ficha = producto \| tienda; los valores de características se suman al meta title sólo si la descripción se repite (catálogos importados), sin repetir lo ya dicho | 0b0238e |
| 2026-10-08 | Tanda 3: ayuda "Cómo funciona" en Proveedores; Escape cierra cualquier ayuda | 0e13f88 |
| 2026-10-08 | Tanda 3: corrección de la ayuda de Proveedores (ajuste "Mostrar el proveedor") | efc9601 |
| 2026-10-08 | Tanda 3: migas de pan en la ficha (Inicio › Bebidas › Gaseosas › Producto) con la categoría visible de más niveles (`Producto::estanteParaMigas`), y su JSON-LD | 745ea3c |
| 2026-10-08 | Tanda 3: el script de Escape va en el lugar y no con @push (en Laravel 8 reordenaba el stack y rompía "Agregar categorías"); test del orden | 5c0b61e |
| 2026-10-08 | Tanda 3: crear categoría desde el panel con etiquetas visibles, scroll y resaltado de la nueva; arreglo: crear una subcategoría desde el panel daba error (madre como texto) | b7398e0 |
| 2026-10-08 | Tanda 3: orden estable de las características en la tienda (globales, las de las categorías en su orden, el resto por nombre) con un ranking único por pedido (`OrdenDeCaracteristicas`, array_position en `etiquetasPublicas`, sin N+1) | 94542b9 |
| 2026-10-08 | Tanda 3: listado de productos vacío con "Cargar el primer producto", o "Ningún producto coincide con el filtro" con enlace para quitarlo | 1e0b527 |
| 2026-10-10 | Limpieza en una migración que primero pasa los datos y después borra lo viejo ("Categoria", "Subcategoria", `proveedor_etiqueta`); se quitan los dos comandos. Corrida en las 4 tiendas locales: menús y categorías muestran los mismos productos que antes; 261 pantallas OK | (este commit) |
| 2026-10-10 | Etapa 5, paso A: código único por tienda (índice `productos_codigo_unico`, aviso en el formulario), permiso `productos.importar`, tabla `importaciones` | fe70ac3 |
| 2026-10-10 | Etapa 5, paso B: `FormatoDePlanilla`, `LecturaDePlanilla`, `AnalisisDeImportacion` (vista previa), `ImportacionDeProductos` (aplicar de a 100 con `GuardadoDeProducto`), exportación y plantilla | 2949d82 |
| 2026-10-10 | Etapa 5, paso C: pantallas (Productos › Excel, Importar productos, vista previa con avance), ayuda; cambio a OpenSpout por rendimiento; una fila sin código con el nombre de un producto existente frena (no duplica). Probado con oleomc (30.917: exportar 25 s, analizar 23 s, tanda de 100 en 2 s) y en farmacia por el navegador (subir, revisar, aplicar) | (este commit) |
| 2026-10-11 | Etapa 4: Marca en tiendas nuevas; orden de los productos por categoría (nombre, nuevos, precio, destacados a mano) | (este) |
| 2026-10-11 | Etapa 4: varios valores por característica (fichitas en el producto, "S; M; L" en Excel, unir valores sin chocar) | (este) |
| 2026-10-11 | Etapa 4: variantes con stock propio (panel, tienda, carrito, pedidos, historial) | (este) |
| 2026-10-11 | Etapa 4: correcciones de la prueba en el navegador (apagar variantes devuelve el stock y pide confirmación, historial con el nombre de la variante, "Desde $" con precios distintos, flechas en destacados, textos) | (este) |
