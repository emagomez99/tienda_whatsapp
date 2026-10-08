<?php

namespace App\Services;

use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Producto;
use App\Models\ProductoEtiqueta;
use App\Models\StockMovimiento;
use App\Models\Variante;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guarda las variantes de un producto (Talle M, Color Rojo) tal como vienen del
 * formulario: una lista completa que reemplaza a la anterior.
 *
 * Cada variante llega como:
 *   ['id' => 12|null, 'valores' => [etiqueta_id => 'M', ...], 'codigo' => ?, 'precio' => ?,
 *    'stock' => ?int, 'activa' => bool]
 *
 * Reglas:
 * - Se eligen por características que admiten varios valores (Talle, Color), todas
 *   las variantes por las mismas, y sin repetir una combinación.
 * - Los valores de las variantes quedan también como valores del producto: si hay
 *   Talle M para comprar, el producto "tiene" Talle M (y aparece al filtrar por M).
 * - El stock cambia sólo con movimientos: lo que se escribe en cada variante es
 *   "cuánto hay ahora", y la diferencia queda en el historial.
 * - Al pasar a tener variantes, el stock que tenía el producto sale (con su
 *   movimiento): desde ahí es la suma de las variantes.
 * - Una variante que ya no viene se borra (su stock sale con un movimiento). Los
 *   pedidos viejos la siguen nombrando por su descripción.
 */
class VariantesDeProducto
{
    /**
     * @param array $lista variantes en el orden en que se muestran; [] = sin variantes
     * @throws ValidationException
     */
    public function sincronizar(Producto $producto, array $lista, ?int $usuarioId = null): void
    {
        $existentes = $producto->variantes()->with('valores')->get()->keyBy('id');

        if (empty($lista)) {
            foreach ($existentes as $variante) {
                $this->borrar($variante, $usuarioId);
            }
            $producto->unsetRelation('variantes');
            return;
        }

        $lista = $this->validar($lista);
        $this->sumarValoresAlProducto($producto, $lista);

        // El stock que tenía el producto pasa a las variantes: sale antes de crearlas.
        if ($existentes->isEmpty() && (int) $producto->fresh()->stock > 0) {
            $producto->registrarMovimiento(
                -(int) $producto->fresh()->stock,
                StockMovimiento::TIPO_AJUSTE_MANUAL,
                'El stock pasa a cargarse en cada variante',
                null,
                $usuarioId
            );
        }

        $porCombinacion = $existentes->mapWithKeys(function (Variante $v) {
            return [Variante::claveDeCombinacion($v->combinacion()) => $v];
        });

        $quedan = [];
        foreach ($lista as $orden => $datos) {
            $variante = (!empty($datos['id']) && $existentes->has((int) $datos['id']))
                ? $existentes[(int) $datos['id']]
                : ($porCombinacion[$datos['clave']] ?? null);

            $nueva = $variante === null;
            if ($nueva) {
                $variante = new Variante(['producto_id' => $producto->id, 'stock' => 0]);
            }

            $variante->fill([
                'codigo' => $datos['codigo'],
                'precio' => $datos['precio'],
                'activa' => $datos['activa'],
                'orden'  => $orden + 1,
            ])->save();

            $filas = [];
            foreach ($datos['combinacion'] as $etiquetaId => $valorId) {
                $filas[$valorId] = ['etiqueta_id' => $etiquetaId];
            }
            $variante->valores()->sync($filas);
            $variante->setRelation('producto', $producto);
            $variante->load('valores');

            if ($datos['stock'] !== null && $datos['stock'] !== (int) $variante->stock) {
                $variante->registrarMovimiento(
                    $datos['stock'] - (int) $variante->stock,
                    $nueva ? StockMovimiento::TIPO_AJUSTE_INICIAL : StockMovimiento::TIPO_AJUSTE_MANUAL,
                    $nueva ? 'Stock inicial de la variante' : 'Ajuste desde el producto',
                    null,
                    $usuarioId
                );
            }

            $quedan[] = $variante->id;
        }

        foreach ($existentes as $variante) {
            if (!in_array($variante->id, $quedan, true)) {
                $this->borrar($variante, $usuarioId);
            }
        }

        // Prender o apagar una variante cambia la suma aunque no se mueva stock.
        $producto->recalcularStockDeVariantes();
        $producto->unsetRelation('variantes');
    }

    /**
     * Normaliza la lista y aplica las reglas. Agrega a cada variante su 'combinacion'
     * (etiqueta_id => etiqueta_valor_id) y su 'clave'.
     *
     * @throws ValidationException
     */
    private function validar(array $lista): array
    {
        $ejes = null;
        $vistas = [];
        $codigos = [];
        $resultado = [];

        foreach (array_values($lista) as $i => $datos) {
            $valores = [];
            foreach ((array) ($datos['valores'] ?? []) as $etiquetaId => $valor) {
                $valor = EtiquetaValor::limpiar($valor);
                if ((int) $etiquetaId > 0 && $valor !== '') {
                    $valores[(int) $etiquetaId] = $valor;
                }
            }
            ksort($valores);

            if (empty($valores)) {
                $this->error('Cada variante tiene que decir qué es (por ejemplo, Talle M).');
            }

            $ejesDeEsta = array_keys($valores);
            if ($ejes === null) {
                $ejes = $ejesDeEsta;
                $this->validarEjes($ejes);
            } elseif ($ejes !== $ejesDeEsta) {
                $this->error('Todas las variantes tienen que elegirse por las mismas características.');
            }

            $combinacion = [];
            foreach ($valores as $etiquetaId => $valor) {
                $combinacion[$etiquetaId] = EtiquetaValor::resolver($etiquetaId, $valor)->id;
            }
            $clave = Variante::claveDeCombinacion($combinacion);
            if (isset($vistas[$clave])) {
                $this->error('La variante «' . implode(' · ', $valores) . '» está dos veces.');
            }
            $vistas[$clave] = true;

            $codigo = trim((string) ($datos['codigo'] ?? ''));
            if ($codigo !== '') {
                $normal = mb_strtolower($codigo, 'UTF-8');
                if (isset($codigos[$normal])) {
                    $this->error("El código «{$codigo}» está en dos variantes.");
                }
                $codigos[$normal] = true;
                $this->validarCodigoLibre($codigo, $datos['id'] ?? null);
            }

            $precio = $datos['precio'] ?? null;
            $stock = $datos['stock'] ?? null;

            $resultado[] = [
                'id'          => isset($datos['id']) && $datos['id'] !== '' ? (int) $datos['id'] : null,
                'valores'     => $valores,
                'combinacion' => $combinacion,
                'clave'       => $clave,
                'codigo'      => $codigo === '' ? null : $codigo,
                'precio'      => ($precio === null || $precio === '') ? null : round((float) $precio, 2),
                'stock'       => ($stock === null || $stock === '') ? null : max(0, (int) $stock),
                'activa'      => !array_key_exists('activa', $datos) || (bool) $datos['activa'],
            ];
        }

        return $resultado;
    }

    /** Sólo características con varios valores: con una sola no hay nada que elegir. */
    private function validarEjes(array $etiquetaIds): void
    {
        $etiquetas = Etiqueta::whereIn('id', $etiquetaIds)->get(['id', 'nombre', 'varios_valores']);
        if ($etiquetas->count() !== count($etiquetaIds)) {
            $this->error('Una de las características de las variantes ya no existe.');
        }
        foreach ($etiquetas as $etiqueta) {
            if (!$etiqueta->varios_valores) {
                $this->error("«{$etiqueta->nombre}» no admite varios valores: no se puede elegir por ella. Prendé «Un producto puede tener varios» en la característica.");
            }
        }
    }

    /** El código de una variante no puede ser el de otra variante ni el de un producto. */
    private function validarCodigoLibre(string $codigo, $varianteId): void
    {
        $normal = mb_strtolower(trim($codigo), 'UTF-8');

        $otraVariante = Variante::whereRaw('lower(trim(codigo)) = ?', [$normal])
            ->when($varianteId, function ($q) use ($varianteId) { $q->where('id', '!=', (int) $varianteId); })
            ->with('producto:id,descripcion')->first();
        if ($otraVariante) {
            $this->error("El código «{$codigo}» ya lo usa una variante de «{$otraVariante->producto->descripcion}».");
        }

        $producto = Producto::conCodigo($codigo)->first(['id', 'descripcion']);
        if ($producto) {
            $this->error("El código «{$codigo}» ya lo usa el producto «{$producto->descripcion}».");
        }
    }

    /** Si hay Talle M para comprar, el producto tiene Talle M. */
    private function sumarValoresAlProducto(Producto $producto, array $lista): void
    {
        $tiene = [];
        foreach (DB::table('producto_etiqueta')->where('producto_id', $producto->id)->get(['etiqueta_valor_id']) as $fila) {
            $tiene[(int) $fila->etiqueta_valor_id] = true;
        }

        foreach ($lista as $datos) {
            foreach ($datos['combinacion'] as $etiquetaId => $valorId) {
                if (!isset($tiene[$valorId])) {
                    (new ProductoEtiqueta(['producto_id' => $producto->id, 'etiqueta_id' => $etiquetaId, 'valor' => $datos['valores'][$etiquetaId]]))->save();
                    $tiene[$valorId] = true;
                }
            }
        }

        $producto->unsetRelation('etiquetas')->unsetRelation('etiquetasPublicas');
    }

    private function borrar(Variante $variante, ?int $usuarioId): void
    {
        if ((int) $variante->stock > 0) {
            $variante->registrarMovimiento(
                -(int) $variante->stock,
                StockMovimiento::TIPO_AJUSTE_MANUAL,
                'Se quitó la variante «' . $variante->nombre() . '»',
                null,
                $usuarioId
            );
        }
        $variante->delete();
        $variante->producto->recalcularStockDeVariantes();
    }

    /** @throws ValidationException */
    private function error(string $mensaje): void
    {
        throw ValidationException::withMessages(['variantes' => $mensaje]);
    }
}
