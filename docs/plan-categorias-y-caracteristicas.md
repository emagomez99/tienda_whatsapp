# Plan: Categorías y Características

> Documento vivo. Se actualiza al cerrar cada tarea (casillas y **Bitácora** al final).
> Para retomar: leer **Estado actual**, después la primera etapa con casillas sin marcar.

## Estado actual

- **Etapa en curso:** ninguna, plan aprobado el 2026-10-07. Arrancar por la **Etapa 0**.
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

## 3. Decisiones

Marcar la elegida. Las recomendadas van primero.

| # | Decisión | Opciones | Elegida |
|---|---|---|---|
| D1 | Nombre visible de "Etiquetas" | **Características** (rec.) · Atributos · dejar Etiquetas | ☐ |
| D2 | Categorías por producto | **Varias** (rec. desde 2026-10-07, como Tiendanube: una cucha en "Perros" y en "Ofertas"; pide las características de todas) · una | ☐ |
| D3 | Producto sin categoría | **Permitido**, se ve como "Sin categoría" con aviso en el listado (rec.) · obligatorio | ☐ |
| D4 | Profundidad del árbol de categorías | **3 niveles** (rec.: igual que el menú) · libre | ☐ |
| D5 | Nombres en la base | **Mantener** `etiquetas` / `etiqueta_valores` y renombrar sólo la interfaz (rec.: sin migración riesgosa) · renombrar tablas | ☐ |
| D6 | oleomc: nombre de la categoría de los productos de Hercules | "Kits hidráulicos" · "Kits Hercules" · otro | ☐ |
| D7 | Reglas por proveedor (`proveedor_etiqueta`) | **Se migran y se borran** en la Etapa 2 (rec.) · conviven un tiempo | ☐ |
| D8 | Menú de la tienda | **Automático desde las categorías**, con Menús personalizado como opción en Ajustes (rec.) · siempre manual como hoy | ☐ |
| D9 | Tiendas que ya tienen menú armado (perfumes, oleomc) | **Siguen con su menú personalizado** hasta que lo cambien (rec.) · pasar a automático | ☐ |

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
- [ ] Cerrar las decisiones D1–D7 (tabla de arriba).
- [ ] Maqueta de las 3 pantallas: Categorías, carga de producto y "Apunta a categoría" en el menú.
- [ ] Revisar con el usuario la migración propuesta para cada tienda (sección 6).

### Etapa 1: Categorías
Objetivo: cada producto tiene una categoría, y los menús pueden apuntar a categorías.

- [ ] Migración `create_categorias_table` + `productos.categoria_id`.
- [ ] Modelo `Categoria`: `parent`, `children`, `productos`, `linaje()`, `idsConDescendientes()`, `nivel()`; límite de niveles (D4).
- [ ] Pantalla **Admin › Categorías**: árbol con arrastrar y soltar (reutilizar el de menús: `ArbolDeMenus` → generalizar o un `ArbolDeCategorias`), ojo de visible, cantidad de productos, `+` para subcategoría, `⋯` con editar y eliminar.
- [ ] Alta/edición de categoría (nombre, dentro de, visible). Eliminar: sólo sin productos; si tiene, ofrecer moverlos a otra.
- [ ] Formulario de producto: **Categorías** con chips y un panel lateral con búsqueda y casillas, mostrando el camino ("Perros/Camas y cuchas") y "Crear categoría" ahí mismo (como Tiendanube).
- [ ] Listado de productos: columna y filtro por categoría; aviso "N productos sin categoría".
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

### Etapa 4 (opcional, se decide después)
- [ ] Marca como característica global por defecto en tiendas nuevas.
- [ ] Varios valores por característica (ej. "Talles: S, M, L"; Modelo en oleomc). Ver análisis del 2026-10-06.
- [ ] Variantes con stock propio (talle/color que el cliente elige al comprar). Proyecto aparte.

## 6. Migración por tienda (datos reales al 2026-10-07)

### perfumes (5 productos)
- Etiqueta "Categoria" (3 valores, 4 productos) → categorías raíz con esos nombres (Notebooks, Drones, …). El producto sin valor queda "Sin categoría".
- Menús `Notebook` (Categoria=Notebooks) y `Drones` (Categoria=Drones) → "Los de una categoría".
- Reglas actuales (Mas Tecnologia: Marca, Condicion, Categoria, Genero obligatorias…; PC One: Marca, Genero obligatorias) → en cada categoría nueva: las características que usan sus productos, **obligatoria si lo era para todos los proveedores de esos productos**. Revisar a mano después (son pocas).
- "Genero" y "Rango de Precio" siguen como características (no son "qué es").

### arcor (4 productos)
- No usa "Categoria": todos quedan "Sin categoría".
- Reglas por proveedor (Georgalos: Calorias y Tamaño obligatorias) **no tienen a qué categoría pasar**: se pierden como obligatorias y quedan opcionales. Avisar al usuario; si quiere, crear una categoría (ej. "Golosinas") y marcarlas ahí.

### oleomc (30.917 productos)
- "Categoria" + "Subcategoria" (14 productos) → árbol `Accesorios › Hidráulica`, `Accesorios › Neumática`, `Industrial`.
- Productos de **Hercules** (30.902, sin "Categoria") → categoría nueva (nombre D6) con **Fabricante\*, Aplicación\*, Modelo\*** (las reglas actuales de Hercules).
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
