# Plan: panel más simple y con mejor diseño

> Documento vivo (2026-10-08). Encargo del dueño, que se fue 12 horas: "hacé todo de la tanda 3, analizá
> si Categorías y Características se pueden unificar, optimizá al máximo el front pensando en un
> futuro template tipo Material, decidí por tu cuenta pero con cambios sólidos, test y verificación
> en cada paso". Reglas de estilo y textos: memoria `feedback-ux-simple` (a prueba de niños,
> ejemplo de supermercado, ayudas `<x-ayuda>`).

## Estado

| Paso | Qué | Estado |
|---|---|---|
| 0 | Tanda 3 de claridad (agente) | hecho: 5f94530…93b7f61 (14 commits, 283 tests) |
| 1 | "Todos los productos" en Categorías (unificación) | hecho |
| 2 | Sistema de diseño (tokens + admin.css) | hecho |
| 3 | Layout: menú lateral fijo en escritorio, barra superior clara | hecho |
| 4 | Pasada visual por pantalla (productos, categorías, características, menús, pedidos, ajustes) | hecho: 0051978…96b59ee |
| 5 | Verificación escritorio + celular, tests, bitácora | pendiente |

## 1. ¿Unificar Categorías y Características?

**Pregunta del dueño:** ¿pueden ser una sola pantalla? ¿Y qué pasa si una categoría pide Peso
obligatorio y una subcategoría pide Peso opcional?

**Qué hace hoy el sistema (reglas, ya implementadas y con tests):**
- Lo que pide una categoría = lo de "todos los productos" + lo de las categorías de arriba + lo propio.
- Si el mismo dato aparece en dos niveles, **manda el más cercano**: Almacén pide Peso
  *obligatorio* y Almacén › Especias lo pide *opcional* → una especia puede guardarse sin peso,
  un paquete de fideos no. El formulario de la categoría lo explica en la fila: "En «Almacén» es
  obligatoria; en esta categoría vale lo que elijas acá".
- Un producto en dos categorías (Especias y Ofertas) pide la unión; es **obligatorio si
  alguna de sus categorías lo exige** (la regla más segura: no se pierde un dato que alguien
  pidió).

**Decisión: unificar la *configuración*, no las pantallas.**
Las dos pantallas hacen trabajos distintos:
- **Categorías** = *qué se pide* (en cada estante). Es donde se configura todo.
- **Características** = *los valores ya cargados*: corregir «Arcorr», juntar duplicados, ocultar
  un valor. Es una tarea de mantenimiento, no de configuración.

Hoy lo que confunde es que "qué se pide en todos los productos" (las globales) se configura en la
otra pantalla, dentro de cada característica. Cambio:
- En **Categorías**, arriba del árbol, una fila fija **"Todos los productos"** con lo que se pide a
  cualquier producto (ej. Marca *) y su botón Editar: abre el mismo editor de "Datos que se piden"
  que una categoría. Así *todo lo que se pide* se configura en un solo lugar, con el mismo control.
- En **Características**, el interruptor "Pedirla en todos los productos" sigue (mismo dato, misma
  regla), con un texto que dice que también se cambia desde Categorías.
- En el **formulario de producto**, cada dato dice quién lo pide ("lo piden todos los productos",
  "lo pide Gaseosas"), para que nadie se pregunte por qué aparece.
- En el menú lateral, Categorías y Características van juntas, bajo "Catálogo".

## 2. Front: sistema de diseño preparado para un tema (tipo Material)

**Diagnóstico del panel actual ("tosco"):**
- Todo el estilo está inline o en `<style>` dentro de cada vista; no hay tokens ni un archivo de
  estilos del panel. Cambiar el aspecto implica tocar decenas de vistas.
- Barra superior del color de la tienda (amarilla en perfumes, naranja en oleomc): el panel
  cambia de cara según la tienda y el texto no siempre se lee bien.
- El menú sólo aparece con ☰ aun en escritorio grande.
- Tarjetas con encabezado gris, tablas con bordes duros, tipografía del sistema, radios chicos.

**Referencia (Tiendanube, revisado en vivo):** menú lateral blanco fijo y agrupado (Gestión,
Canales de venta), contenido sobre gris muy claro, títulos de página grandes (32px, 600), tarjetas
blancas de borde sutil, botones de radio 8px, una barra de búsqueda + "Filtrar".
(TiendaNegocio: el navegador no permitió abrirlo.)

**Decisiones:**
1. **Tokens en un solo archivo** `public/css/admin.css`: colores, tipografía, radios, sombras y
   espacios como variables CSS (`--panel-*`), mapeadas sobre las variables de Bootstrap 5.3
   (`--bs-primary`, `--bs-border-radius`, `--bs-body-font-family`, …). Un tema futuro (Material)
   = otro archivo que redefine los tokens, sin tocar vistas.
2. **Tipografía Inter** (Google Fonts), tamaños con escala fija.
3. **Color de acción fijo del panel** (azul), independiente del color de la tienda. El color de
   la tienda queda como identidad (logo y marca de la opción activa), no en botones ni barras.
4. **Layout:** menú lateral blanco **fijo en escritorio** (≥ 992px) y en celular el mismo menú
   como cajón (☰). Agrupado: Inicio · Ventas (Pedidos) · Catálogo (Productos, Categorías,
   Características, Proveedores) · Tienda (Menú, Ajustes, Monedas) · Equipo (Usuarios, Perfiles).
   Barra superior blanca y baja: buscador/nombre de la tienda, Ver tienda, usuario.
5. **Componentes base** restilizados desde el archivo (no en cada vista): tarjetas, encabezados de
   página, tablas, botones, campos, badges, paginación, árbol, ayudas, barra de guardar.
6. Sin dependencias nuevas de JS: Bootstrap sigue. Material (MUI) es de React; un "tipo Material"
   en este stack es Material Design 3 aplicado con tokens (o Material Web Components más adelante),
   y para eso alcanza con esta capa.

## Bitácora

| Fecha | Qué | Commit |
|---|---|---|
| 2026-10-08 | Plan escrito; inspiración de Tiendanube revisada | — |
| 2026-10-08 | Paso 1: fila "Todos los productos" arriba del árbol de Categorías y su pantalla (`categorias/todos`, mismo editor que una categoría, sin orden); `CategoriaController::caracteristicasDelFormulario` compartido. Formulario de producto: cada dato dice quién lo pide ("Lo pide Medicamentos", "Se pide en todos los productos") | f696f34 |
| 2026-10-08 | Pasos 2 y 3: `public/css/admin.css` (tokens `--panel-*` volcados en las variables de Bootstrap; Inter; color de acción propio del panel; tarjetas, tablas, campos, botones). Layout nuevo: menú lateral blanco fijo en escritorio y cajón en celular con el mismo marcado (`offcanvas-lg`), agrupado (Inicio · Ventas · Catálogo · Tienda · Equipo) desde `App\Support\MenuDelPanel`; barra superior clara. "Dashboard" → "Inicio", "Configuraciones" → "Ajustes". Verificado en farmacia: escritorio (inicio, productos, categorías, ajustes, pedidos) y celular 390px (listado, formulario, menú abierto) | (este commit) |
| 2026-10-08 | Paso 4, base y catálogo: piezas repetidas a `admin.css` (`.panel-rotulo`, `.panel-dato`, `.panel-miniatura`, `.cifras`, `.badge-chica`); flecha de filtros plegables por CSS (`aria-expanded`), sin script global; `.panel-barra-guardar` flotante; títulos en minúscula como el menú; Historial con el encabezado común y sin "-0" | 0051978 |
| 2026-10-08 | Paso 4, Inicio y Pedidos: números del mes con `.panel-dato`, acciones rápidas "Nuevo pedido"/"Ajustes"; Nuevo pedido con tarjetas del panel y "¿Para quién es el pedido?"; detalle sin `<code>` rosa; ayuda nueva `ayudas/pedidos` | 3868aaf |
| 2026-10-08 | Paso 4, Proveedores, Monedas, Usuarios, Perfiles: `barra-guardar` (con aviso de cambios sin guardar) en vez de botones sueltos; encabezados comunes con el nombre de lo editado; ayudas nuevas `ayudas/monedas` y `ayudas/usuarios` | 53f1631 |
| 2026-10-08 | Paso 4, Ajustes: `.ajuste-*` e `.imagen-miniatura` a `admin.css` con tokens; vistas previas pegajosas con `--panel-barra-alto` | 8df7901 |
| 2026-10-08 | Paso 4, inicio de sesión: página propia con el aspecto del panel (antes, layout y botón del color de la tienda). Verificado en farmacia, escritorio 1280 y celular 390 (sin desborde horizontal en ninguna pantalla), pedido de prueba #1 creado; 284 tests en verde | 96b59ee |
