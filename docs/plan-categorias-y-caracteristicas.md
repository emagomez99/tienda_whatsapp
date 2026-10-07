# Plan: Categorías y Características

> Documento vivo. Se actualiza al cerrar cada tarea (casillas y **Bitácora** al final).
> Para retomar: leer **Estado actual**, después la primera etapa con casillas sin marcar.

## Estado actual

- **Etapa en curso:** Etapa 0. Decisiones D1–D11 cerradas el 2026-10-07; falta revisar la maqueta (sección 5, Etapa 0).
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
- Los valores de las características siguen como hoy: `etiqueta_valores`, únicos, ocultables, con "¿Quisiste decir?".

Lo que se reutiliza sin cambios: `EtiquetaValor`, `ProductoEtiqueta` (pivot que unifica valores), árbol de valores, sugerencias con cantidad, `Producto::scopeDelMenu` (se le suma el tipo categoría), árbol de menús con arrastrar y soltar.

## 5. Etapas

Cada etapa queda usable sola, con tests, commiteada y migrada en las 3 tiendas locales antes de seguir.

### Etapa 0: decisiones y diseño (sin código)
- [x] Cerrar las decisiones D1–D11 (tabla de arriba).
- [ ] Maqueta de las pantallas (abajo): revisarla con el usuario.

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
- [ ] Revisar con el usuario la migración propuesta para cada tienda (sección 6).

### Etapa 1: Categorías
Objetivo: cada producto tiene una categoría, y los menús pueden apuntar a categorías.

- [ ] **Servicio de guardado de producto** (antes que nada): sacar de `ProductoController` las reglas que hoy viven ahí (`validarEtiquetasObligatorias`, `sincronizarEtiquetas`, especificaciones, `sincronizarGaleria`) a un servicio (ej. `App\Services\GuardadoDeProducto`) que reciba datos ya validados y devuelva errores por campo. El controlador sólo orquesta. Lo va a usar la importación desde Excel (Etapa 5). Tests de las reglas en el servicio, sin HTTP.
- [ ] Migración `create_categorias_table` + `categoria_producto`.
- [ ] `Categoria::resolverCamino('Ropa > Remeras', crear: bool)`: busca por camino (sin distinguir mayúsculas, con el mismo normalizado que los valores) y opcionalmente crea lo que falta. Lo usa el formulario ("Crear categoría" en el panel) y la importación.
- [ ] Modelo `Categoria`: `parent`, `children`, `productos`, `linaje()`, `idsConDescendientes()`, `nivel()`; límite de niveles (D4).
- [ ] Pantalla **Admin › Categorías**: árbol con arrastrar y soltar (reutilizar el de menús: `ArbolDeMenus` → generalizar o un `ArbolDeCategorias`), ojo de visible, cantidad de productos, `+` para subcategoría, `⋯` con editar y eliminar. Mismas reglas que Tiendanube y que el árbol de menús actual: derecha = meter adentro, izquierda = sacar, máximo 3 niveles.
- [ ] Mejora del árbol (sirve también para Menús): **línea de destino** donde va a caer lo arrastrado, como la línea azul de Tiendanube. Se mantiene el guardado automático al soltar, con aviso y Deshacer (Tiendanube usa un botón "Guardar cambios"; acá se prefiere no perder lo hecho si se sale de la página).
- [ ] Alta/edición de categoría (nombre, dentro de, visible). Eliminar: sólo sin productos; si tiene, ofrecer moverlos a otra.
- [ ] Formulario de producto: **Categorías** con chips y un panel lateral con búsqueda y casillas, mostrando el camino ("Perros/Camas y cuchas") y "Crear categoría" ahí mismo (como Tiendanube).
- [ ] Listado de productos: columna y filtro por categoría; aviso "N productos sin categoría".
- [ ] **Asignar categorías en masa** desde el listado (como Tiendanube): casillas por producto + "seleccionar todos los del filtro" (para catálogos grandes como oleomc), acción "Asignar categorías" / "Quitar categorías", y "Crear categoría" en el mismo panel.
- [ ] `Producto::scopeEnCategoria(Categoria)` (incluye subcategorías).
- [ ] Menús: nuevo tipo **"Los de una categoría"** (`tipo_enlace = categoria`), en `scopeDelMenu`, en el formulario por bloques, en el contador en vivo y en la vista previa.
- [ ] **Menú automático (D8):** ajuste "El menú de la tienda se arma con las categorías"; la tienda (escritorio y celular) lo dibuja desde el árbol de categorías visibles. Las tiendas actuales siguen con su menú personalizado (D9).
- [ ] Página de categoría en la tienda: `/categoria/{slug}` con SEO (título, descripción, canónica) y en el sitemap.
- [ ] Comando de migración de datos (ver sección 6): crea categorías desde la etiqueta "Categoria"/"Subcategoria", asigna productos y convierte los menús que filtraban por esas etiquetas. Con `--dry-run` que muestra qué haría.
- [ ] Tests: árbol (ciclos, profundidad), productos de una categoría con subcategorías, menú por categoría, comando de migración.
- [ ] Correrlo en perfumes, arcor y oleomc; revisar con el usuario.

### Etapa 2: Características por categoría
Objetivo: lo que hoy decide el proveedor pasa a decidirlo la categoría.

- [ ] Migración `create_categoria_caracteristica_table`.
- [ ] `Categoria::caracteristicas()` efectivas (con herencia) y `obligatorias()`.
- [ ] Pantalla de la categoría: lista de características con obligatoria/opcional, orden, y las heredadas (en gris, "de Ropa").
- [ ] Formulario de producto: el componente `partials/etiquetas` pasa a tomar los campos **de la categoría** en vez del proveedor (al cambiar de categoría se rearma, como hoy con el proveedor).
- [ ] `ProductoController::validarEtiquetasObligatorias` según la categoría.
- [ ] Menú: los filtros en cascada sugieren las características de la categoría elegida.
- [ ] Comando de migración: reglas de `proveedor_etiqueta` → `categoria_caracteristica` (sección 6).
- [ ] Quitar las reglas por proveedor: tabla `proveedor_etiqueta`, sección "Proveedores" de la edición de etiqueta, `mapEtiquetasObligatorias/Aplicables` en `ProductoController` y `MenuController`, `Proveedor::etiquetas*`.
- [ ] Renombrar en la interfaz "Etiquetas" → **Características** (D1): menú lateral, títulos, ayudas (`resources/lang/es/productos.php`), mensajes.
- [ ] `ImportHercules`: asignar la categoría de Hercules (D6) a los productos que importa.
- [ ] Tests: herencia y "manda la más cercana", validación de obligatorias por categoría, formulario que cambia de campos, migración de reglas.

### Etapa 3: que se entienda solo
- [ ] Estado vacío de Categorías: "Categorizá tus productos" con ilustración/ícono y botón, como el competidor.
- [ ] **Plantillas de ejemplo** para crear de un clic, con sus características: Ropa (Talle, Color, Tela), Celulares (Marca, Almacenamiento, Color), Pádel (Marca, Género, Peso), Calzado (Talle, Género), Comida (Peso neto, Sabor).
- [ ] Ayudas "Cómo funciona" en Categorías y Características, con la frase de la sección 2.
- [ ] `tenant:create --seed`: ofrecer una plantilla inicial.
- [ ] Revisar textos de todo el panel con la palabra nueva.

### Etapa 5: Importar productos desde Excel (próxima, después de 1 y 2)
Objetivo: carga y actualización masiva por planilla, con las mismas reglas que el formulario.

- [ ] D10: código único por tienda (índice único parcial `WHERE id_proveedor IS NOT NULL`) y aviso en el formulario si se repite.
- [ ] Librería: `maatwebsite/excel` 3.1 (compatible con Laravel 8 y PHP 7.4; verificar al instalar).
- [ ] **Plantilla descargable por categoría**: columnas fijas (Código, Nombre, Descripción, Precio, Moneda, Stock, Disponible, Por encargue, Categorías, Imagen 1…N) + **una columna por característica de esa categoría**, marcando las obligatorias. Así el cliente no adivina qué cargar.
- [ ] Formato: Categorías como Tiendanube (`Ropa > Remeras, Ofertas`); imágenes por URL (la primera es la principal, vía `GaleriaProducto`); valores de características uno por celda (varios valores: separador a definir junto con la Etapa 4).
- [ ] **Vista previa antes de aplicar**: cuántos se crean, cuántos se actualizan (por código), errores por fila ("Fila 12: falta Talle, obligatoria en Remeras"), categorías y valores nuevos que se van a crear, y valores parecidos a existentes ("Asuz" ≈ "Asus") para corregir antes.
- [ ] Aplicar en lotes (oleomc tiene 30.917 productos), con el servicio de guardado de la Etapa 1. El stock inicial entra como movimiento de stock, como en el alta.
- [ ] Exportar a Excel con el mismo formato (sirve de respaldo y para editar en masa).
- [ ] Permiso propio (`productos.importar`) y registro de quién importó qué.
- [ ] Tests: plantilla por categoría, vista previa con errores, creación y actualización por código, categorías por camino, valores unificados.

### Etapa 4 (opcional, se decide después)
- [ ] Marca como característica global por defecto en tiendas nuevas.
- [ ] **Orden de los productos dentro de una categoría** (hoy siempre por nombre): elegir por categoría entre manual, más nuevos, precio o nombre. Tiendanube lo tiene como función aparte.
- [ ] Varios valores por característica (ej. "Talles: S, M, L"; Modelo en oleomc). Ver análisis del 2026-10-06.
- [ ] Variantes con stock propio (talle/color que el cliente elige al comprar). Proyecto aparte.

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
