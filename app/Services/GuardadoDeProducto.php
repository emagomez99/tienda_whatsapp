<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoEspecificacion;
use App\Models\Proveedor;
use App\Models\StockMovimiento;
use App\Support\DatosDeProducto;
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
 * obligatorios, precio derivado, stock como movimiento, etiquetas, especificaciones
 * y galería. Todo o nada: si una regla falla, no se guarda ninguna parte.
 */
class GuardadoDeProducto
{
    /** Lo que se conserva del HTML de la descripción detallada. */
    const HTML_PERMITIDO = '<p><br><b><strong><i><em><u><ul><ol><li><a><h1><h2><h3><h4><blockquote><pre><code><img><table><thead><tbody><tr><th><td>';

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
            $atributos['detalle'] = strip_tags($atributos['detalle'], self::HTML_PERMITIDO);
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
     * Las etiquetas que el producto tiene que tener cargadas.
     *
     * Hoy las decide el proveedor. En la Etapa 2 del plan de Categorías pasan a
     * decidirlas las categorías del producto: el cambio es sólo acá.
     *
     * @throws ValidationException
     */
    private function validarObligatorias(Producto $producto, DatosDeProducto $datos): void
    {
        $proveedor = Proveedor::with('etiquetasObligatorias')->find($producto->proveedor_id);
        if (!$proveedor || $proveedor->etiquetasObligatorias->isEmpty()) {
            return;
        }

        // Si estos datos no traen etiquetas (ej. una planilla sin esas columnas), se
        // controla contra las que el producto ya tiene.
        $cargadas = $datos->etiquetas();
        if ($cargadas === null) {
            $cargadas = $producto->exists
                ? $producto->etiquetas()->pluck('producto_etiqueta.valor', 'etiquetas.id')->all()
                : [];
        }

        $faltantes = $proveedor->etiquetasObligatorias
            ->filter(function ($etiqueta) use ($cargadas) {
                return trim((string) ($cargadas[$etiqueta->id] ?? '')) === '';
            })
            ->pluck('nombre')
            ->all();

        if (!empty($faltantes)) {
            throw ValidationException::withMessages([
                'etiquetas' => 'Faltan etiquetas obligatorias para este proveedor: ' . implode(', ', $faltantes) . '.',
            ]);
        }
    }
}
