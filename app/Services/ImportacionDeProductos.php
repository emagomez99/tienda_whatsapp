<?php

namespace App\Services;

use App\Imports\LecturaDePlanilla;
use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Importacion;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\StockMovimiento;
use App\Support\DatosDeProducto;
use App\Support\Planilla\FormatoDePlanilla;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Importar productos desde una planilla de Excel, en dos tiempos:
 *
 *  1. preparar(): guarda el archivo, lo lee (LecturaDePlanilla) y lo analiza
 *     (AnalisisDeImportacion) sin cambiar nada: es la vista previa.
 *  2. aplicarLote(): aplica la siguiente tanda de filas. La pantalla lo llama una y
 *     otra vez mostrando el avance, así una planilla de 30.000 productos no depende
 *     de que una sola página aguante minutos, ni de procesos en segundo plano (el
 *     servidor no los tiene: QUEUE_CONNECTION=sync).
 *
 * Cada fila se guarda con GuardadoDeProducto, el mismo servicio que el formulario:
 * las reglas son las mismas. Una fila que falla al aplicar no frena a las demás:
 * queda anotada con el motivo.
 */
class ImportacionDeProductos
{
    /** Filas por tanda: unos segundos por pedido. */
    const POR_TANDA = 100;

    /** @var GuardadoDeProducto */
    private $guardado;

    /** @var array<string, int>|null nombre normalizado => proveedor_id (se arma una vez por tanda) */
    private $proveedores = null;

    /** @var array<string, int|null> código de moneda => moneda_id */
    private $monedas = [];

    public function __construct(GuardadoDeProducto $guardado)
    {
        $this->guardado = $guardado;
    }

    public function preparar(UploadedFile $archivo, ?int $usuarioId): Importacion
    {
        // Una planilla de 30.000 productos se lee y analiza en ~25 s y ~250 MB (medido
        // con oleomc): más que los límites habituales de una página.
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        // Con su extensión original: el lector elige el formato por ahí (y un .csv
        // guardado con store() quedaría como .txt).
        $extension = strtolower($archivo->getClientOriginalExtension() ?: 'xlsx');
        $extension = $extension === 'txt' ? 'csv' : $extension;   // un CSV que llegó como texto
        $ruta = $archivo->storeAs('importaciones/' . tenant('id'), Str::random(24) . '.' . $extension, 'local');

        $lectura = new LecturaDePlanilla($this->caracteristicasPorNombre());
        $lectura->leer(Storage::disk('local')->path($ruta));

        $columnas = [];
        foreach ($lectura->columnas() as $columna) {
            if ($columna['tipo'] === 'fija') {
                $columnas[$columna['clave']] = true;
            }
        }

        $analisis = (new AnalisisDeImportacion())->analizar($lectura->filas, $columnas);
        $resumen = $analisis['resumen'];
        if ($lectura->columnasDesconocidas) {
            $resumen['avisos'][] = 'Estas columnas no se usan (no son de la planilla ni una característica): '
                . implode(', ', $lectura->columnasDesconocidas) . '.';
        }

        $importacion = Importacion::create([
            'user_id'        => $usuarioId,
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'ruta'           => $ruta,
            'estado'         => Importacion::REVISANDO,
            'total'          => count($analisis['filas']),
            'resumen'        => $resumen,
        ]);
        $importacion->guardarFilas($analisis['filas']);

        return $importacion;
    }

    /**
     * Aplica la siguiente tanda. Devuelve la importación actualizada.
     */
    public function aplicarLote(Importacion $importacion, ?int $usuarioId): Importacion
    {
        if ($importacion->terminada()) {
            return $importacion;
        }

        $filas = array_slice($importacion->filas(), $importacion->procesadas, self::POR_TANDA);
        $erroresAlAplicar = [];
        $creados = $actualizados = $conError = 0;

        foreach ($filas as $fila) {
            if ($fila['accion'] === 'error') {
                $conError++;
                continue;
            }
            try {
                $this->aplicarFila($fila, $importacion, $usuarioId);
                $fila['accion'] === 'crear' ? $creados++ : $actualizados++;
            } catch (ValidationException $e) {
                $conError++;
                $erroresAlAplicar[] = ['fila' => $fila['fila'], 'mensaje' => collect($e->errors())->flatten()->implode(' ')];
            } catch (Throwable $e) {
                report($e);
                $conError++;
                $erroresAlAplicar[] = ['fila' => $fila['fila'], 'mensaje' => 'No se pudo guardar: ' . $e->getMessage()];
            }
        }

        $resumen = $importacion->resumen ?: [];
        $resumen['errores_al_aplicar'] = array_slice(
            array_merge($resumen['errores_al_aplicar'] ?? [], $erroresAlAplicar), 0, AnalisisDeImportacion::MAX_ERRORES_A_MOSTRAR
        );

        $procesadas = $importacion->procesadas + count($filas);
        $importacion->update([
            'estado'       => $procesadas >= $importacion->total ? Importacion::TERMINADA : Importacion::APLICANDO,
            'procesadas'   => $procesadas,
            'creados'      => $importacion->creados + $creados,
            'actualizados' => $importacion->actualizados + $actualizados,
            'con_error'    => $importacion->con_error + $conError,
            'resumen'      => $resumen,
        ]);

        return $importacion;
    }

    private function aplicarFila(array $fila, Importacion $importacion, ?int $usuarioId): void
    {
        DB::transaction(function () use ($fila, $importacion, $usuarioId) {
            $v = $fila['valores'];
            $producto = $fila['producto_id'] ? Producto::find($fila['producto_id']) : null;

            $datos = new DatosDeProducto($this->atributos($v, $producto));

            if (($v['categorias'] ?? null) !== null) {
                $datos = $datos->conCategorias(array_map(function ($camino) {
                    return Categoria::resolverCamino($camino, true)->id;
                }, $v['categorias']));
            }

            // Las características que trae la planilla se suman a las que ya tiene
            // (una columna que no vino o una celda vacía no borra nada).
            if ($fila['caracteristicas']) {
                $actuales = $producto ? $producto->etiquetas()->pluck('producto_etiqueta.valor', 'etiquetas.id')->all() : [];
                $datos = $datos->conEtiquetas(array_replace($actuales, $fila['caracteristicas']));
            }

            if ($fila['imagenes']) {
                $datos = $datos->conGaleria(array_map(function ($url) { return 'url:' . $url; }, $fila['imagenes']), []);
            }

            if (!$producto) {
                $datos = $datos->conStockInicial((int) ($v['stock'] ?? 0));
                $this->guardado->crear($datos, $usuarioId);
                return;
            }

            $this->guardado->actualizar($producto, $datos);

            // Stock = cuánto hay ahora: la diferencia entra como movimiento, con el
            // nombre de la planilla, para que se vea en el historial de dónde salió.
            $stockActual = (int) Producto::whereKey($producto->id)->value('stock');
            if (isset($v['stock']) && (int) $v['stock'] !== $stockActual) {
                $producto->registrarMovimiento(
                    (int) $v['stock'] - $stockActual,
                    StockMovimiento::TIPO_IMPORTACION,
                    'Importación desde Excel («' . $importacion->nombre_archivo . '»)',
                    null,
                    $usuarioId
                );
            }
        });
    }

    /**
     * Los datos del producto que trae la fila. Al actualizar, sólo lo que vino con
     * valor; al crear, con los valores por defecto del formulario.
     */
    private function atributos(array $v, ?Producto $producto): array
    {
        $atributos = [];
        if (!empty($v['codigo'])) {
            $atributos['id_proveedor'] = $v['codigo'];
        }
        if (!empty($v['nombre'])) {
            $atributos['descripcion'] = $v['nombre'];
        }
        if (!empty($v['proveedor'])) {
            $atributos['proveedor_id'] = $this->proveedor($v['proveedor']);
        }
        if (isset($v['detalle'])) {
            $atributos['detalle'] = $v['detalle'];
        }
        foreach (['disponible', 'por_encargue'] as $campo) {
            if (isset($v[$campo])) {
                $atributos[$campo] = $v[$campo];
            }
        }
        if (!empty($v['moneda'])) {
            $codigo = mb_strtoupper(trim($v['moneda']), 'UTF-8');
            if (!array_key_exists($codigo, $this->monedas)) {
                $this->monedas[$codigo] = Moneda::where('codigo', 'ilike', $codigo)->value('id');
            }
            $atributos['moneda_id'] = $this->monedas[$codigo];
        }
        // A un producto que calcula su precio por margen no se le pisa el precio.
        if (isset($v['precio']) && (!$producto || $producto->modo_precio_venta !== Producto::MODO_PRECIO_MARGEN)) {
            $atributos['precio'] = $v['precio'];
        }

        if (!$producto) {
            $atributos += [
                'proveedor_id'      => Proveedor::count() === 1 ? Proveedor::value('id') : null,
                'precio'            => 0,
                'moneda_id'         => Moneda::idPorDefecto(),
                'modo_precio_venta' => Producto::MODO_PRECIO_MANUAL,
                'disponible'        => true,
                'por_encargue'      => false,
            ];
        }

        return $atributos;
    }

    /** El proveedor con ese nombre (sin importar mayúsculas), o uno nuevo. */
    private function proveedor(string $nombre): int
    {
        if ($this->proveedores === null) {
            $this->proveedores = [];
            foreach (Proveedor::all(['id', 'nombre']) as $p) {
                $this->proveedores[EtiquetaValor::normalizar($p->nombre)] = $p->id;
            }
        }

        $clave = EtiquetaValor::normalizar($nombre);
        if (!isset($this->proveedores[$clave])) {
            $this->proveedores[$clave] = Proveedor::create(['nombre' => trim($nombre), 'activo' => true])->id;
        }

        return $this->proveedores[$clave];
    }

    /** @return array<string, int> nombre normalizado => etiqueta_id, para reconocer las columnas */
    private function caracteristicasPorNombre(): array
    {
        $mapa = [];
        foreach (Etiqueta::all(['id', 'nombre']) as $e) {
            $mapa[FormatoDePlanilla::normalizarTitulo($e->nombre)] = $e->id;
        }
        return $mapa;
    }
}
