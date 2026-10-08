<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\Caracteristica;
use Illuminate\Support\Facades\DB;

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
 *    nada; sólo cambia lo que trae valor.
 *  - Sin Código, o con uno que no existe: se crea. Necesita Nombre y Proveedor
 *    (salvo que la tienda tenga un solo proveedor).
 *  - Las características obligatorias se controlan con las categorías que va a
 *    tener el producto y los valores que va a tener (lo que ya tenía más lo nuevo).
 */
class AnalisisDeImportacion
{
    /** Cuántos errores se guardan para mostrar (las filas con error se cuentan todas). */
    const MAX_ERRORES_A_MOSTRAR = 300;

    /** @var array<string, int> código normalizado => producto_id */
    private $productosPorCodigo = [];

    /** @var array<string, true> nombres normalizados de los productos que ya existen */
    private $nombresExistentes = [];

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

    public function analizar(array $filas, array $columnasPresentes): array
    {
        $this->precargar($filas);

        $resumen = [
            'crear' => 0, 'actualizar' => 0, 'con_error' => 0,
            'errores' => [], 'avisos' => [],
            'proveedores_nuevos' => [], 'categorias_nuevas' => [], 'parecidos' => [],
        ];
        $codigosVistos = [];
        $nombresSinCodigo = [];
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
            $fila['accion'] = $productoId ? 'actualizar' : 'crear';
            $fila['producto_id'] = $productoId;

            if (!$productoId) {
                if (empty($v['nombre'])) {
                    $errores[] = 'Falta el nombre (es obligatorio para un producto nuevo).';
                } elseif ($codigo === null) {
                    // Sin código no hay forma de saber si es uno que ya está: se frena en
                    // vez de duplicarlo (pasa al volver a subir productos exportados sin código).
                    $nombre = $this->normalizar($v['nombre']);
                    if (isset($this->nombresExistentes[$nombre])) {
                        $errores[] = "Ya tenés un producto «{$v['nombre']}». Si es ese, poné su código para actualizarlo; si es otro, cambiale el nombre.";
                    } elseif (isset($nombresSinCodigo[$nombre])) {
                        $errores[] = "«{$v['nombre']}» ya aparece sin código en la fila {$nombresSinCodigo[$nombre]}: se crearía dos veces. Poneles código.";
                    } else {
                        $nombresSinCodigo[$nombre] = $fila['fila'];
                    }
                }
                if (empty($v['proveedor']) && !$unSoloProveedor) {
                    $errores[] = 'Falta el proveedor (es obligatorio para un producto nuevo).';
                }
            }

            if (!empty($v['proveedor']) && !isset($this->proveedores[$this->normalizar($v['proveedor'])])) {
                $resumen['proveedores_nuevos'][$this->normalizar($v['proveedor'])] = $v['proveedor'];
            }

            if (!empty($v['moneda']) && !isset($this->monedas[mb_strtoupper(trim($v['moneda']), 'UTF-8')])) {
                $errores[] = "Moneda: no hay ninguna «{$v['moneda']}». Usá el código: " . implode(', ', array_keys($this->monedas)) . '.';
            }

            // Precio de un producto que calcula su precio por margen: se ignora.
            if ($productoId && isset($v['precio']) && $this->productos[$productoId]->modo_precio_venta === Producto::MODO_PRECIO_MARGEN) {
                $resumen['avisos']['margen'] = 'Algunos productos calculan su precio por margen: el Precio de la planilla no se les aplica (se recalcula solo).';
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
            } else {
                $resumen[$fila['accion']]++;
            }
        }
        unset($fila);

        $resumen['proveedores_nuevos'] = array_values($resumen['proveedores_nuevos']);
        $resumen['categorias_nuevas'] = array_values($resumen['categorias_nuevas']);
        $resumen['parecidos'] = array_values($resumen['parecidos']);
        $resumen['avisos'] = array_values($resumen['avisos']);
        if (!isset($columnasPresentes['codigo'])) {
            $resumen['avisos'][] = 'La planilla no tiene columna Código: todos los productos se van a crear como nuevos.';
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
        foreach (array_chunk(array_values($this->productosPorCodigo), 2000) as $ids) {
            foreach (Producto::whereIn('id', $ids)->get(['id', 'modo_precio_venta']) as $p) {
                $this->productos[$p->id] = $p;
            }
            foreach (DB::table('producto_etiqueta')->whereIn('producto_id', $ids)->get(['producto_id', 'etiqueta_id', 'valor']) as $r) {
                $this->valoresDelProducto[$r->producto_id][$r->etiqueta_id][] = $r->valor;
            }
            foreach (DB::table('categoria_producto')->whereIn('producto_id', $ids)->get(['producto_id', 'categoria_id']) as $r) {
                $this->categoriasDelProducto[$r->producto_id][] = (int) $r->categoria_id;
            }
        }

        foreach (DB::table('productos')->pluck('descripcion') as $descripcion) {
            $this->nombresExistentes[$this->normalizar((string) $descripcion)] = true;
        }

        foreach (Proveedor::all(['id', 'nombre']) as $p) {
            $this->proveedores[$this->normalizar($p->nombre)] = $p->id;
        }
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
