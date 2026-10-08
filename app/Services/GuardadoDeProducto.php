<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoEspecificacion;
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

    public function __construct(GaleriaProducto $galeria = null)
    {
        $this->galeria = $galeria ?: new GaleriaProducto();
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
            $this->guardar($producto, $datos);

            if ($datos->stockInicial() > 0) {
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
    public function actualizar(Producto $producto, DatosDeProducto $datos): Producto
    {
        return DB::transaction(function () use ($producto, $datos) {
            $this->guardar($producto, $datos);

            return $producto;
        });
    }

    private function guardar(Producto $producto, DatosDeProducto $datos): void
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
            $sync = [];
            foreach ($datos->etiquetas() as $etiquetaId => $valor) {
                $sync[$etiquetaId] = ['valor' => $valor];
            }
            $producto->etiquetas()->sync($sync);
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
            $cargadas = $producto->exists
                ? $producto->etiquetas()->pluck('producto_etiqueta.valor', 'etiquetas.id')->all()
                : [];
        }

        $faltantes = [];
        foreach ($obligatorias as $caracteristica) {
            if (trim((string) ($cargadas[$caracteristica->etiquetaId()] ?? '')) === '') {
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
