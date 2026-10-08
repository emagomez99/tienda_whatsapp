<?php

namespace App\Services;

use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Producto;
use App\Models\ProductoEspecificacion;
use App\Models\ProductoEtiqueta;
use App\Models\StockMovimiento;
use App\Support\Caracteristica;
use App\Support\DatosDeProducto;
use App\Support\HtmlSeguro;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Las reglas para guardar un producto, en un solo lugar.
 *
 * Las usa el formulario del panel y las va a usar la importación desde Excel: si
 * cada una tuviera su copia, con el tiempo dejarían de coincidir y una planilla
 * podría cargar productos que el formulario rechaza.
 *
 * Recibe datos con el formato ya validado (tipos, largos, que existan los ids):
 * eso es de quien arma DatosDeProducto. Acá van las reglas del negocio: datos
 * obligatorios, precio derivado, stock como movimiento, categorías, características,
 * especificaciones y galería. Todo o nada: si una regla falla, no se guarda ninguna parte.
 */
class GuardadoDeProducto
{
    /** @var GaleriaProducto */
    private $galeria;

    /** @var VariantesDeProducto */
    private $variantes;

    public function __construct(GaleriaProducto $galeria = null, VariantesDeProducto $variantes = null)
    {
        $this->galeria = $galeria ?: new GaleriaProducto();
        $this->variantes = $variantes ?: new VariantesDeProducto();
    }

    /**
     * @throws ValidationException si falta un dato obligatorio
     */
    public function crear(DatosDeProducto $datos, ?int $usuarioId = null): Producto
    {
        return DB::transaction(function () use ($datos, $usuarioId) {
            // El stock no se escribe directo: entra como movimiento, que queda en
            // el historial con quién y por qué.
            $producto = new Producto(['stock' => 0]);
            $this->guardar($producto, $datos, $usuarioId);

            // Con variantes, el stock es el de cada una.
            if ($datos->stockInicial() > 0 && !$producto->tieneVariantes()) {
                $producto->registrarMovimiento(
                    $datos->stockInicial(),
                    StockMovimiento::TIPO_AJUSTE_INICIAL,
                    'Stock inicial al crear el producto',
                    null,
                    $usuarioId
                );
            }

            return $producto;
        });
    }

    /**
     * @throws ValidationException si falta un dato obligatorio
     */
    public function actualizar(Producto $producto, DatosDeProducto $datos, ?int $usuarioId = null): Producto
    {
        return DB::transaction(function () use ($producto, $datos, $usuarioId) {
            $this->guardar($producto, $datos, $usuarioId);

            return $producto;
        });
    }

    private function guardar(Producto $producto, DatosDeProducto $datos, ?int $usuarioId = null): void
    {
        $atributos = $datos->atributos();
        unset($atributos['stock']);   // sólo por movimientos

        if (!empty($atributos['detalle'])) {
            $atributos['detalle'] = HtmlSeguro::limpiar($atributos['detalle']);
        }

        // En modo margen el precio de venta se calcula: lo que llegue en `precio`
        // se descarta para que nadie lo fije armando el pedido a mano.
        if (($atributos['modo_precio_venta'] ?? $producto->modo_precio_venta) === Producto::MODO_PRECIO_MARGEN) {
            unset($atributos['precio']);
        }

        $producto->fill($atributos);
        $this->validarObligatorias($producto, $datos);

        $producto->ajustarPrecioSegunModo();
        $producto->save();

        if ($datos->categorias() !== null) {
            $producto->categorias()->sync($datos->categorias());
        }

        if ($datos->etiquetas() !== null) {
            // Sin lista de variantes (ej. una planilla), los valores que usan las
            // variantes que ya tiene no se pueden sacar: quedarían sin producto.
            $this->guardarCaracteristicas($producto, $datos->etiquetas(), $datos->variantes() === null);
        }

        if ($datos->especificaciones() !== null) {
            $producto->especificaciones()->delete();
            foreach ($datos->especificaciones() as $par) {
                ProductoEspecificacion::create(['producto_id' => $producto->id] + $par);
            }
        }

        if ($datos->galeria() !== null) {
            $this->galeria->sincronizar($producto, $datos->galeria(), $datos->archivosGaleria());
        }

        // Después de las características: las variantes se eligen entre sus valores.
        if ($datos->variantes() !== null) {
            $this->variantes->sincronizar($producto, $datos->variantes(), $usuarioId);
        }
    }

    /**
     * Reemplaza los valores de las características: una fila de producto_etiqueta por
     * valor. Una característica que admite uno solo se queda con el primero.
     *
     * Las filas que no cambian se dejan como están (no se borra y vuelve a crear todo).
     *
     * @param array<int, string[]> $valores
     */
    private function guardarCaracteristicas(Producto $producto, array $valores, bool $protegerVariantes = true): void
    {
        $admiteVarios = Etiqueta::whereIn('id', array_keys($valores))->pluck('varios_valores', 'id')->all();

        $quedan = [];
        foreach ($valores as $etiquetaId => $lista) {
            if (!array_key_exists($etiquetaId, $admiteVarios)) {
                continue;   // la característica ya no existe
            }
            if (!$admiteVarios[$etiquetaId]) {
                $lista = array_slice($lista, 0, 1);
            }
            foreach ($lista as $valor) {
                $valorId = EtiquetaValor::resolver($etiquetaId, $valor)->id;
                $quedan[] = $valorId;

                $existe = DB::table('producto_etiqueta')
                    ->where(['producto_id' => $producto->id, 'etiqueta_id' => $etiquetaId, 'etiqueta_valor_id' => $valorId])
                    ->exists();
                if (!$existe) {
                    (new ProductoEtiqueta(['producto_id' => $producto->id, 'etiqueta_id' => $etiquetaId, 'valor' => $valor]))->save();
                }
            }
        }

        if ($protegerVariantes) {
            $quedan = array_merge($quedan, DB::table('producto_variante_valores')
                ->join('producto_variantes', 'producto_variantes.id', '=', 'producto_variante_valores.variante_id')
                ->where('producto_variantes.producto_id', $producto->id)
                ->pluck('producto_variante_valores.etiqueta_valor_id')->all());
        }

        DB::table('producto_etiqueta')->where('producto_id', $producto->id)
            ->when($quedan, function ($q) use ($quedan) { $q->whereNotIn('etiqueta_valor_id', $quedan); })
            ->delete();

        $producto->unsetRelation('etiquetas')->unsetRelation('etiquetasPublicas');
    }

    /**
     * Las características que el producto tiene que tener cargadas: las obligatorias
     * de sus categorías (con la herencia) y las globales obligatorias.
     *
     * Si estos datos no traen categorías (ej. una planilla sin esa columna), valen las
     * que el producto ya tiene; lo mismo con los valores de las características.
     *
     * @throws ValidationException
     */
    private function validarObligatorias(Producto $producto, DatosDeProducto $datos): void
    {
        $categorias = $datos->categorias();
        if ($categorias === null) {
            $categorias = $producto->exists ? $producto->categorias()->pluck('categorias.id')->all() : [];
        }

        $obligatorias = array_filter(
            (new CaracteristicasPorCategoria())->deCategorias($categorias),
            function (Caracteristica $c) { return $c->obligatoria(); }
        );
        if (empty($obligatorias)) {
            return;
        }

        $cargadas = $datos->etiquetas();
        if ($cargadas === null) {
            $cargadas = $producto->exists ? $producto->valoresDeCaracteristicas() : [];
        }
        foreach ($datos->variantes() ?: [] as $variante) {
            foreach ((array) ($variante['valores'] ?? []) as $propiedad => $valor) {
                $etiqueta = ctype_digit((string) $propiedad) ? Etiqueta::find((int) $propiedad) : Etiqueta::porNombre((string) $propiedad);
                if ($etiqueta && trim((string) $valor) !== '') {
                    $cargadas[$etiqueta->id][] = $valor;
                }
            }
        }

        $faltantes = [];
        foreach ($obligatorias as $caracteristica) {
            if (empty($cargadas[$caracteristica->etiquetaId()])) {
                $faltantes[] = $caracteristica->nombre();
            }
        }

        if (!empty($faltantes)) {
            throw ValidationException::withMessages([
                'etiquetas' => (count($faltantes) === 1 ? 'Falta una característica obligatoria: ' : 'Faltan características obligatorias: ')
                    . implode(', ', $faltantes) . '.',
            ]);
        }
    }
}
