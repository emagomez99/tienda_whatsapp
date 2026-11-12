<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\Caracteristica;
use App\Support\HtmlSeguro;
use App\Support\Planilla\FormatoDePlanilla;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * La vista previa de una importación: qué pasaría con cada fila, sin cambiar nada.
 *
 * Para cada fila decide si crea o actualiza (por el Código) y junta los errores que
 * la frenarían ("Falta el nombre", "Falta Sabor, obligatoria en Gaseosas"), y para
 * toda la planilla lo que conviene ver antes de aplicar: proveedores y categorías
 * que se van a crear, y valores nuevos que se parecen a uno que ya existe ("Arcorr"
 * ≈ "Arcor"), el error de tipeo que duplica valores.
 *
 * Carga de antemano lo que necesita consultar (códigos, proveedores, monedas,
 * categorías y valores existentes): con 30.000 filas, una consulta por fila sería
 * eterna.
 *
 * Reglas de la planilla (ver también FormatoDePlanilla):
 *  - Con Código de un producto que existe: se actualiza. Una celda vacía no cambia
 *    nada; sólo cambia lo que trae valor. Si nada de lo que trae es distinto de lo
 *    que el producto ya tiene, la fila queda "igual" y no se aplica (ver cambios()).
 *  - Sin Código y con el Nombre exacto de un producto que existe (uno solo): se
 *    actualiza ese. Es lo que deja volver a subir lo exportado en una tienda que
 *    no usa códigos.
 *  - Si no, se crea. Necesita Nombre y Proveedor (salvo que la tienda tenga un
 *    solo proveedor).
 *  - Las características obligatorias se controlan con las categorías que va a
 *    tener el producto y los valores que va a tener (lo que ya tenía más lo nuevo).
 */
class AnalisisDeImportacion
{
    /** Cuántos errores se guardan para mostrar (las filas con error se cuentan todas). */
    const MAX_ERRORES_A_MOSTRAR = 300;

    /** Cuántos cambios se guardan para mostrar con su antes y después (se cuentan todos). */
    const MAX_CAMBIOS_A_MOSTRAR = 100;

    /** @var array<string, int> código normalizado => producto_id */
    private $productosPorCodigo = [];

    /** @var array<string, int[]> nombre normalizado => ids de los productos que se llaman así */
    private $productosPorNombre = [];

    /** @var array<int, Producto> datos chicos de los productos que se actualizan */
    private $productos = [];

    /** @var array<string, int> nombre normalizado => proveedor_id */
    private $proveedores = [];

    /** @var array<string, int> código de moneda (mayúsculas) => moneda_id */
    private $monedas = [];

    /** @var array<string, array> camino normalizado => [id|null, id del ancestro existente más cercano|null] */
    private $caminos = [];

    /** @var CaracteristicasPorCategoria */
    private $caracteristicas;

    /** @var array<int, string> etiqueta_id => nombre */
    private $nombresDeEtiqueta = [];

    /** @var array<int, array<string, true>> etiqueta_id => valores normalizados existentes */
    private $valoresExistentes = [];

    /** @var array<string, true> valores nuevos ya mirados por parecidos (cada uno, una vez) */
    private $valoresMirados = [];

    /** @var array<int, array<int, string>> producto_id => [etiqueta_id => valor] de los que se actualizan */
    private $valoresDelProducto = [];

    /** @var array<int, int[]> producto_id => categoria_ids de los que se actualizan */
    private $categoriasDelProducto = [];

    /** @var array<int, string[]> producto_id => imágenes adicionales, en orden */
    private $imagenesDelProducto = [];

    /** @var array<int, true> los productos que tienen variantes (su stock no va por planilla) */
    private $conVariantes = [];

    /** @var array<int, string> proveedor_id => nombre */
    private $nombresDeProveedor = [];

    /** @var array<int, string> moneda_id => código (todas, también las inactivas) */
    private $codigosDeMoneda = [];

    /** @var array<int, string>|null categoria_id => camino, para mostrar (se arma si hace falta) */
    private $caminosPorId = null;

    public function analizar(array $filas, array $columnasPresentes): array
    {
        $this->precargar($filas);

        $resumen = [
            'crear' => 0, 'actualizar' => 0, 'sin_cambios' => 0, 'con_error' => 0,
            'errores' => [], 'avisos' => [],
            'cambios' => [],    // qué dato cambia => en cuántos productos
            'ejemplos' => [],   // los primeros cambios, con el antes y el después
            'proveedores_nuevos' => [], 'categorias_nuevas' => [], 'parecidos' => [],
        ];
        $codigosVistos = [];
        $nombresSinCodigo = [];
        $productosVistos = [];
        $unSoloProveedor = count($this->proveedores) === 1 ? reset($this->proveedores) : null;

        foreach ($filas as &$fila) {
            $v = $fila['valores'];
            $errores = $fila['errores'];
            $codigo = isset($v['codigo']) ? mb_strtolower(trim($v['codigo']), 'UTF-8') : null;

            // Un código repetido dentro de la misma planilla: la segunda vez es error.
            if ($codigo !== null && isset($codigosVistos[$codigo])) {
                $errores[] = "El código «{$v['codigo']}» ya aparece en la fila {$codigosVistos[$codigo]}.";
            } elseif ($codigo !== null) {
                $codigosVistos[$codigo] = $fila['fila'];
            }

            $productoId = $codigo !== null ? ($this->productosPorCodigo[$codigo] ?? null) : null;

            // Sin código, el producto se reconoce por su nombre exacto: los que se
            // cargaron a mano en el panel no suelen tener código, y lo que se exporta
            // se tiene que poder volver a subir. Si hay varios con ese nombre no se
            // sabe cuál es: se frena en vez de tocar el equivocado o duplicarlo.
            $tocayos = ($codigo === null && !empty($v['nombre'])) ? ($this->productosPorNombre[$this->normalizar($v['nombre'])] ?? []) : [];
            if (count($tocayos) === 1) {
                $productoId = $tocayos[0];
            }

            // El mismo producto dos veces en la planilla: la segunda vez es error.
            if ($productoId && isset($productosVistos[$productoId]) && ($codigo === null || $codigosVistos[$codigo] === $fila['fila'])) {
                $errores[] = "Este producto ya aparece en la fila {$productosVistos[$productoId]}.";
            } elseif ($productoId) {
                $productosVistos[$productoId] = $productosVistos[$productoId] ?? $fila['fila'];
            }

            $fila['accion'] = $productoId ? 'actualizar' : 'crear';
            $fila['producto_id'] = $productoId;

            if (!$productoId) {
                if (empty($v['nombre'])) {
                    $errores[] = 'Falta el nombre (es obligatorio para un producto nuevo).';
                } elseif ($codigo === null) {
                    $nombre = $this->normalizar($v['nombre']);
                    if (count($tocayos) > 1) {
                        $errores[] = 'Tenés ' . count($tocayos) . " productos que se llaman «{$v['nombre']}» y no se sabe cuál es. Poneles un código distinto a cada uno (en el panel) y usalo en la planilla.";
                    } elseif (isset($nombresSinCodigo[$nombre])) {
                        $errores[] = "«{$v['nombre']}» ya aparece sin código en la fila {$nombresSinCodigo[$nombre]}: se crearía dos veces. Poneles código.";
                    } else {
                        $nombresSinCodigo[$nombre] = $fila['fila'];
                    }
                }
                if (empty($v['proveedor']) && !$unSoloProveedor && count($tocayos) < 2) {
                    $errores[] = 'Falta el proveedor (es obligatorio para un producto nuevo).';
                }
            }

            if (!empty($v['proveedor']) && !isset($this->proveedores[$this->normalizar($v['proveedor'])])) {
                $resumen['proveedores_nuevos'][$this->normalizar($v['proveedor'])] = $v['proveedor'];
            }

            if (!empty($v['moneda']) && !isset($this->monedas[mb_strtoupper(trim($v['moneda']), 'UTF-8')])) {
                $errores[] = "Moneda: no hay ninguna «{$v['moneda']}». Usá el código: " . implode(', ', array_keys($this->monedas)) . '.';
            }

            // Precio distinto en un producto que calcula su precio por margen: se ignora.
            if ($productoId && isset($v['precio']) && $this->productos[$productoId]->modo_precio_venta === Producto::MODO_PRECIO_MARGEN
                && $this->distintos($v['precio'], $this->productos[$productoId]->precio)) {
                $resumen['avisos']['margen'] = 'Algunos productos calculan su precio por margen: el Precio de la planilla no se les aplica (se recalcula solo). Para cambiarlo, cambiá el Costo o el Margen %.';
            }

            if (!empty($v['moneda_costo']) && !isset($this->monedas[mb_strtoupper(trim($v['moneda_costo']), 'UTF-8')])) {
                $errores[] = "Moneda del costo: no hay ninguna «{$v['moneda_costo']}». Usá el código: " . implode(', ', array_keys($this->monedas)) . '.';
            }

            // Con variantes, la planilla trae el total: cuál talle cambió no se sabe.
            if ($productoId && isset($v['stock']) && isset($this->conVariantes[$productoId])
                && (int) $v['stock'] !== (int) $this->productos[$productoId]->stock) {
                $errores[] = 'Stock: tiene variantes (talles, colores…) y su stock se cambia en cada una, desde el producto en el panel. '
                    . 'Dejá la celda vacía o con el stock que tiene (' . (int) $this->productos[$productoId]->stock . ').';
            }

            // Más niveles de los que admite la tienda: se avisa acá, en palabras.
            $muyProfundas = array_filter($v['categorias'] ?? [], function ($camino) {
                return count(array_filter(array_map('trim', explode(Categoria::SEPARADOR, $camino)), 'strlen')) > Categoria::NIVEL_MAXIMO + 1;
            });
            foreach ($muyProfundas as $camino) {
                $errores[] = "Categorías: «{$camino}» tiene demasiados niveles. Se puede hasta " . (Categoria::NIVEL_MAXIMO + 1) . ', por ejemplo Bebidas > Gaseosas > Cola.';
            }
            if ($muyProfundas) {
                $fila['valores']['categorias'] = array_values(array_diff($v['categorias'], $muyProfundas));
            }

            $categoriaIds = $this->categoriasResultantes($fila, $productoId, $resumen);
            foreach ($this->faltantes($fila, $productoId, $categoriaIds) as $faltante) {
                $errores[] = $faltante;
            }

            $this->buscarParecidos($fila, $resumen);

            $fila['errores'] = $errores;
            if ($errores) {
                $fila['accion'] = 'error';
                $resumen['con_error']++;
                foreach ($errores as $error) {
                    if (count($resumen['errores']) < self::MAX_ERRORES_A_MOSTRAR) {
                        $resumen['errores'][] = ['fila' => $fila['fila'], 'mensaje' => $error];
                    }
                }
                continue;
            }

            if (!$productoId) {
                $resumen['crear']++;
                continue;
            }

            // Lo que la fila le cambia al producto. Si no le cambia nada, no se toca:
            // al volver a subir 30.000 productos exportados con 20 precios nuevos,
            // se aplican 20 filas y se ve cuáles.
            $cambios = $this->cambios($fila, $this->productos[$productoId]);
            if (!$cambios) {
                $fila['accion'] = 'igual';
                $resumen['sin_cambios']++;
                continue;
            }

            $resumen['actualizar']++;
            foreach ($cambios as $campo => [$antes, $despues]) {
                $resumen['cambios'][$campo] = ($resumen['cambios'][$campo] ?? 0) + 1;
                if (count($resumen['ejemplos']) < self::MAX_CAMBIOS_A_MOSTRAR) {
                    $resumen['ejemplos'][] = [
                        'fila' => $fila['fila'],
                        'producto' => $v['nombre'] ?? (string) $this->productos[$productoId]->descripcion,
                        'campo' => $campo, 'antes' => $antes, 'despues' => $despues,
                    ];
                }
            }
        }
        unset($fila);
        arsort($resumen['cambios']);

        $resumen['proveedores_nuevos'] = array_values($resumen['proveedores_nuevos']);
        $resumen['categorias_nuevas'] = array_values($resumen['categorias_nuevas']);
        $resumen['parecidos'] = array_values($resumen['parecidos']);
        $resumen['avisos'] = array_values($resumen['avisos']);
        if (!isset($columnasPresentes['codigo'])) {
            $resumen['avisos'][] = 'La planilla no tiene columna Código: los productos que ya tenés se reconocen por su nombre exacto, y el resto se crea.';
        }

        return ['filas' => $filas, 'resumen' => $resumen];
    }

    private function precargar(array $filas): void
    {
        $codigos = [];
        foreach ($filas as $fila) {
            if (isset($fila['valores']['codigo'])) {
                $codigos[] = mb_strtolower(trim($fila['valores']['codigo']), 'UTF-8');
            }
        }

        foreach (array_chunk(array_unique($codigos), 2000) as $parte) {
            $marcadores = implode(',', array_fill(0, count($parte), '?'));
            foreach (DB::select("select id, lower(trim(id_proveedor)) as codigo from productos where lower(trim(id_proveedor)) in ($marcadores)", $parte) as $r) {
                $this->productosPorCodigo[$r->codigo] = (int) $r->id;
            }
        }

        foreach (DB::table('productos')->orderBy('id')->get(['id', 'descripcion']) as $r) {
            $this->productosPorNombre[$this->normalizar((string) $r->descripcion)][] = (int) $r->id;
        }
        // Los que la planilla nombra sin código (si el nombre es de uno solo).
        $porNombre = [];
        foreach ($filas as $fila) {
            if (!isset($fila['valores']['codigo']) && !empty($fila['valores']['nombre'])) {
                $tocayos = $this->productosPorNombre[$this->normalizar($fila['valores']['nombre'])] ?? [];
                if (count($tocayos) === 1) {
                    $porNombre[] = $tocayos[0];
                }
            }
        }

        $columnas = ['id', 'descripcion', 'proveedor_id', 'precio', 'moneda_id', 'precio_compra', 'moneda_compra_id',
            'margen_ganancia', 'modo_precio_venta', 'stock', 'disponible', 'por_encargue', 'detalle', 'url_imagen'];
        $aActualizar = array_values(array_unique(array_merge(array_values($this->productosPorCodigo), $porNombre)));
        foreach (array_chunk($aActualizar, 2000) as $ids) {
            foreach (DB::table('productos')->whereIn('id', $ids)->get($columnas) as $p) {
                $this->productos[$p->id] = $p;
            }
            foreach (DB::table('producto_etiqueta')->whereIn('producto_id', $ids)->orderBy('id')->get(['producto_id', 'etiqueta_id', 'valor']) as $r) {
                $this->valoresDelProducto[$r->producto_id][$r->etiqueta_id][] = $r->valor;
            }
            foreach (DB::table('categoria_producto')->whereIn('producto_id', $ids)->get(['producto_id', 'categoria_id']) as $r) {
                $this->categoriasDelProducto[$r->producto_id][] = (int) $r->categoria_id;
            }
            foreach (DB::table('producto_imagenes')->whereIn('producto_id', $ids)->orderBy('orden')->orderBy('id')->get(['producto_id', 'url']) as $r) {
                $this->imagenesDelProducto[$r->producto_id][] = $r->url;
            }
            foreach (DB::table('producto_variantes')->whereIn('producto_id', $ids)->distinct()->pluck('producto_id') as $id) {
                $this->conVariantes[$id] = true;
            }
        }

        foreach (Proveedor::all(['id', 'nombre']) as $p) {
            $this->proveedores[$this->normalizar($p->nombre)] = $p->id;
            $this->nombresDeProveedor[$p->id] = $p->nombre;
        }
        $this->codigosDeMoneda = Moneda::pluck('codigo', 'id')->all();
        foreach (Moneda::where('activa', true)->get(['id', 'codigo']) as $m) {
            $this->monedas[mb_strtoupper($m->codigo, 'UTF-8')] = $m->id;
        }

        $this->caracteristicas = new CaracteristicasPorCategoria();
        $this->nombresDeEtiqueta = Etiqueta::pluck('nombre', 'id')->all();
        foreach (DB::table('etiqueta_valores')->select('etiqueta_id', 'normalizado')->get() as $v) {
            $this->valoresExistentes[$v->etiqueta_id][$v->normalizado] = true;
        }
    }

    /** Las categorías que va a tener el producto (las existentes: las nuevas no piden nada todavía). */
    private function categoriasResultantes(array $fila, ?int $productoId, array &$resumen): array
    {
        $caminos = $fila['valores']['categorias'] ?? null;
        if ($caminos === null) {
            return $productoId ? ($this->categoriasDelProducto[$productoId] ?? []) : [];
        }

        $ids = [];
        foreach ($caminos as $camino) {
            [$id, $ancestro] = $this->ubicarCamino($camino);
            if ($id) {
                $ids[] = $id;
            } else {
                $resumen['categorias_nuevas'][$this->normalizar($camino)] = str_replace('>', '›', $camino);
                // Una nueva dentro de una que existe pide lo de esa (herencia).
                if ($ancestro) {
                    $ids[] = $ancestro;
                }
            }
        }

        return $ids;
    }

    private function ubicarCamino(string $camino): array
    {
        $clave = $this->normalizar($camino);
        if (!isset($this->caminos[$clave])) {
            $partes = array_values(array_filter(array_map('trim', explode(Categoria::SEPARADOR, $camino)), 'strlen'));
            $ancestro = null;
            $id = null;
            for ($i = 1; $i <= count($partes); $i++) {
                $existente = Categoria::resolverCamino(implode(' ' . Categoria::SEPARADOR . ' ', array_slice($partes, 0, $i)));
                if (!$existente) {
                    break;
                }
                $ancestro = $existente->id;
                if ($i === count($partes)) {
                    $id = $existente->id;
                }
            }
            $this->caminos[$clave] = [$id, $id ? null : $ancestro];
        }

        return $this->caminos[$clave];
    }

    /** "Falta Sabor, obligatoria en Gaseosas" por cada obligatoria sin valor. */
    private function faltantes(array $fila, ?int $productoId, array $categoriaIds): array
    {
        $obligatorias = array_filter($this->caracteristicas->deCategorias($categoriaIds), function (Caracteristica $c) {
            return $c->obligatoria();
        });
        if (!$obligatorias) {
            return [];
        }

        $tiene = $fila['caracteristicas'];
        if ($productoId) {
            $tiene += $this->valoresDelProducto[$productoId] ?? [];
        }

        $faltantes = [];
        foreach ($obligatorias as $c) {
            if (empty($tiene[$c->etiquetaId()])) {
                $faltantes[] = "Falta {$c->nombre()}, " . ($c->esGlobal() ? 'obligatoria en todos los productos.' : "obligatoria en {$c->origen()}.");
            }
        }

        return $faltantes;
    }

    // ─── Qué cambia ──────────────────────────────────────────────────────────
    //
    // Compara la fila con el producto como lo haría aplicarla (ver
    // ImportacionDeProductos::aplicarFila): sólo cuenta lo que vino con valor.

    /**
     * Lo que la fila le cambia al producto, para mostrar.
     *
     * @param object $p la fila de `productos`
     * @return array<string, array{0: string, 1: string}> dato => [antes, después]; vacío si queda igual
     */
    private function cambios(array $fila, $p): array
    {
        $v = $fila['valores'];
        $cambios = [];

        if (!empty($v['nombre']) && $v['nombre'] !== trim((string) $p->descripcion)) {
            $cambios['Nombre'] = [(string) $p->descripcion, $v['nombre']];
        }

        if (!empty($v['proveedor']) && ($this->proveedores[$this->normalizar($v['proveedor'])] ?? null) !== (int) $p->proveedor_id) {
            $cambios['Proveedor'] = [$this->nombresDeProveedor[$p->proveedor_id] ?? '', $v['proveedor']];
        }

        if (($v['categorias'] ?? null) !== null) {
            $actuales = $this->categoriasDelProducto[$p->id] ?? [];
            if ($this->cambianLasCategorias($v['categorias'], $actuales)) {
                $cambios['Categorías'] = [
                    implode(', ', array_map(function ($id) { return $this->caminoParaMostrar($id); }, $actuales)),
                    str_replace(Categoria::SEPARADOR, '›', implode(', ', $v['categorias'])),
                ];
            }
        }

        // A un producto que calcula su precio por margen no se le pisa el precio.
        if (isset($v['precio']) && $p->modo_precio_venta !== Producto::MODO_PRECIO_MARGEN && $this->distintos($v['precio'], $p->precio)) {
            $cambios['Precio'] = [$this->importe($p->precio), $this->importe($v['precio'])];
        }
        if (!empty($v['moneda']) && $this->monedas[mb_strtoupper(trim($v['moneda']), 'UTF-8')] !== (int) $p->moneda_id) {
            $cambios['Moneda'] = [$this->codigosDeMoneda[$p->moneda_id] ?? '', mb_strtoupper(trim($v['moneda']), 'UTF-8')];
        }

        if (isset($v['costo']) && ($p->precio_compra === null || $this->distintos($v['costo'], $p->precio_compra))) {
            $cambios['Costo'] = [$this->importe($p->precio_compra), $this->importe($v['costo'])];
        }
        if (!empty($v['moneda_costo']) && $this->monedas[mb_strtoupper(trim($v['moneda_costo']), 'UTF-8')] !== (int) $p->moneda_compra_id) {
            $cambios['Moneda del costo'] = [$this->codigosDeMoneda[$p->moneda_compra_id] ?? '', mb_strtoupper(trim($v['moneda_costo']), 'UTF-8')];
        }
        if (isset($v['margen']) && ($p->margen_ganancia === null || $this->distintos($v['margen'], $p->margen_ganancia))) {
            $cambios['Margen %'] = [$this->importe($p->margen_ganancia), $this->importe($v['margen'])];
        }

        if (isset($v['stock']) && (int) $v['stock'] !== (int) $p->stock) {
            $cambios['Stock'] = [(string) (int) $p->stock, (string) (int) $v['stock']];
        }
        foreach (['disponible', 'por_encargue'] as $campo) {
            if (isset($v[$campo]) && (bool) $v[$campo] !== (bool) $p->$campo) {
                $cambios[FormatoDePlanilla::COLUMNAS[$campo][0]] = [
                    FormatoDePlanilla::escribirSiNo((bool) $p->$campo), FormatoDePlanilla::escribirSiNo((bool) $v[$campo]),
                ];
            }
        }

        // Se guarda limpio (HtmlSeguro): sólo cambia si, ya limpio, sigue siendo otro.
        if (isset($v['detalle']) && $this->texto($v['detalle']) !== $this->texto($p->detalle)
            && $this->texto(HtmlSeguro::limpiar($v['detalle'])) !== $this->texto($p->detalle)) {
            $cambios['Descripción detallada'] = [$this->recortar((string) $p->detalle), $this->recortar($v['detalle'])];
        }

        if ($fila['imagenes']) {
            $nuevas = array_slice(array_values(array_unique($fila['imagenes'])), 0, GaleriaProducto::maximo());
            $actuales = GaleriaProducto::urlsPublicas($p->url_imagen, $this->imagenesDelProducto[$p->id] ?? []);
            if ($nuevas !== $actuales) {
                $cambios['Fotos'] = [$this->cuantasFotos(count($actuales)), $this->cuantasFotos(count($nuevas))];
            }
        }

        foreach ($fila['caracteristicas'] as $etiquetaId => $valores) {
            $actuales = $this->valoresDelProducto[$p->id][$etiquetaId] ?? [];
            if ($this->conjunto((array) $valores) !== $this->conjunto($actuales)) {
                $cambios[$this->nombresDeEtiqueta[$etiquetaId] ?? 'Característica'] = [
                    FormatoDePlanilla::escribirValores($actuales), FormatoDePlanilla::escribirValores((array) $valores),
                ];
            }
        }

        return $cambios;
    }

    /** Si la lista escrita deja al producto en otras categorías (una nueva siempre cambia). */
    private function cambianLasCategorias(array $caminos, array $actuales): bool
    {
        $ids = [];
        foreach ($caminos as $camino) {
            $id = $this->ubicarCamino($camino)[0];
            if (!$id) {
                return true;
            }
            $ids[] = $id;
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        sort($actuales);

        return $ids !== $actuales;
    }

    private function caminoParaMostrar(int $categoriaId): string
    {
        if ($this->caminosPorId === null) {
            $this->caminosPorId = [];
            foreach (Categoria::enOrdenDeArbol() as $categoria) {
                $this->caminosPorId[$categoria->id] = str_replace(Categoria::SEPARADOR, '›', $categoria->camino());
            }
        }

        return $this->caminosPorId[$categoriaId] ?? '';
    }

    /** Dos importes, comparados en centavos. */
    private function distintos($a, $b): bool
    {
        return round((float) $a, 2) !== round((float) $b, 2);
    }

    private function importe($numero): string
    {
        return $numero === null ? '' : number_format((float) $numero, 2, ',', '.');
    }

    /** Un texto largo para comparar: Excel devuelve los saltos de línea a su manera. */
    private function texto($texto): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", (string) $texto));
    }

    private function recortar(string $texto): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($texto))), 60);
    }

    private function cuantasFotos(int $n): string
    {
        return $n === 1 ? '1 foto' : $n . ' fotos';
    }

    /** Los valores sin repetir ni distinguir mayúsculas, ordenados: para comparar dos listas. */
    private function conjunto(array $valores): array
    {
        $normales = array_values(array_unique(array_filter(array_map(function ($valor) {
            return EtiquetaValor::normalizar($valor);
        }, $valores), 'strlen')));
        sort($normales);

        return $normales;
    }

    /** Valores nuevos que se parecen a uno existente: probablemente un error de tipeo. */
    private function buscarParecidos(array $fila, array &$resumen): void
    {
        foreach ($fila['caracteristicas'] as $etiquetaId => $valores) {
            foreach ((array) $valores as $valor) {
                $this->buscarParecido((int) $etiquetaId, (string) $valor, $fila['fila'], $resumen);
            }
        }
    }

    private function buscarParecido(int $etiquetaId, string $valor, int $numeroDeFila, array &$resumen): void
    {
        $normal = EtiquetaValor::normalizar($valor);
        $clave = $etiquetaId . '|' . $normal;
        if (isset($this->valoresExistentes[$etiquetaId][$normal]) || isset($this->valoresMirados[$clave])) {
            return;
        }
        $this->valoresMirados[$clave] = true;   // cada valor nuevo se mira una vez

        $etiqueta = new Etiqueta();
        $etiqueta->id = $etiquetaId;
        $etiqueta->exists = true;
        $parecido = $etiqueta->valorParecido($valor);
        if ($parecido) {
            $resumen['parecidos'][$clave] = [
                'caracteristica' => $this->nombresDeEtiqueta[$etiquetaId] ?? '',
                'escrito' => $valor,
                'existente' => $parecido->valor,
                'fila' => $numeroDeFila,
            ];
        }
    }

    private function normalizar(string $texto): string
    {
        return EtiquetaValor::normalizar($texto);
    }
}
