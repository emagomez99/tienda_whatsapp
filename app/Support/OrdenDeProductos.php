<?php

namespace App\Support;

use App\Models\Categoria;
use App\Models\Menu;
use App\Models\Moneda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * En qué orden se ven los productos en la tienda.
 *
 * Cada categoría elige el suyo (categorias.orden_productos); fuera de una categoría
 * (la portada, una búsqueda, un menú que no es de categoría) van por nombre, como
 * siempre. El nombre desempata siempre, así el orden no cambia entre una página y la
 * siguiente.
 *
 * - nombre:       A → Z.
 * - nuevos:       los últimos que se cargaron primero.
 * - precio_menor: el más barato primero. Los precios en otra moneda se pasan a la
 *   moneda base con su cotización: 10 dólares no son "más barato" que 5.000 pesos.
 * - precio_mayor: al revés.
 * - manual:       los destacados de la categoría primero, en el orden elegido;
 *   después el resto por nombre.
 */
final class OrdenDeProductos
{
    const NOMBRE = 'nombre';
    const NUEVOS = 'nuevos';
    const PRECIO_MENOR = 'precio_menor';
    const PRECIO_MAYOR = 'precio_mayor';
    const MANUAL = 'manual';

    /** Para el formulario de la categoría: valor => cómo se lee. */
    const OPCIONES = [
        self::NOMBRE       => 'Por nombre (de la A a la Z)',
        self::NUEVOS       => 'Los más nuevos primero',
        self::PRECIO_MENOR => 'El más barato primero',
        self::PRECIO_MAYOR => 'El más caro primero',
        self::MANUAL       => 'Elijo cuáles van primero',
    ];

    /** @var string */
    private $criterio;

    /** @var int|null categoría de los destacados (sólo en manual) */
    private $categoriaId;

    private function __construct(string $criterio, ?int $categoriaId = null)
    {
        $this->criterio = array_key_exists($criterio, self::OPCIONES) ? $criterio : self::NOMBRE;
        $this->categoriaId = $categoriaId;
    }

    /** El que elige el cliente en "Ordenar por" (nunca "a mano": ése es de la categoría). */
    public static function elegido(string $criterio): self
    {
        return new self($criterio === self::MANUAL ? self::NOMBRE : $criterio);
    }

    public static function porNombre(): self
    {
        return new self(self::NOMBRE);
    }

    public static function deCategoria(?Categoria $categoria): self
    {
        return $categoria ? new self((string) $categoria->orden_productos, $categoria->id) : self::porNombre();
    }

    /** Un menú que lleva a una categoría muestra sus productos en el orden de esa categoría. */
    public static function deMenu(?Menu $menu): self
    {
        if ($menu && $menu->tipo_enlace === Menu::TIPO_CATEGORIA) {
            return self::deCategoria($menu->categoria);
        }

        return self::porNombre();
    }

    public function criterio(): string
    {
        return $this->criterio;
    }

    public function aplicar(Builder $query): Builder
    {
        switch ($this->criterio) {
            case self::NUEVOS:
                $query->orderByDesc('productos.created_at')->orderByDesc('productos.id');
                break;

            case self::PRECIO_MENOR:
            case self::PRECIO_MAYOR:
                $direccion = $this->criterio === self::PRECIO_MENOR ? 'asc' : 'desc';
                // Sin precio (null) van al final en los dos sentidos: no son "gratis".
                [$cotizacion, $valores] = self::cotizacionPorMoneda();
                $query->orderByRaw('(productos.precio * ' . $cotizacion . ') ' . $direccion . ' NULLS LAST', $valores);
                break;

            case self::MANUAL:
                // Los destacados son pocos: van como lista y no como subconsulta por
                // fila, que en una categoría de 30.000 productos son 30.000 búsquedas.
                $destacados = DB::table('categoria_destacados')
                    ->where('categoria_id', (int) $this->categoriaId)
                    ->orderBy('posicion')
                    ->pluck('producto_id')
                    ->map(function ($id) { return (int) $id; })
                    ->all();
                if (!empty($destacados)) {
                    $query->orderByRaw('array_position(ARRAY[' . implode(',', $destacados) . ']::bigint[], productos.id) ASC NULLS LAST');
                }
                break;
        }

        return $query->orderBy('productos.descripcion')->orderBy('productos.id');
    }

    /**
     * La cotización de la moneda de cada producto, como CASE con las monedas de la
     * tienda (son un puñado). Antes era una subconsulta a monedas por fila: ordenar
     * una categoría de 30.000 productos por precio tardaba el doble.
     *
     * @return array{0: string, 1: array} la expresión SQL y sus valores
     */
    public static function cotizacionPorMoneda(): array
    {
        $cuando = '';
        $valores = [];
        foreach (Moneda::query()->pluck('cotizacion', 'id') as $id => $cotizacion) {
            $cuando .= ' WHEN ? THEN CAST(? AS numeric)';
            $valores[] = (int) $id;
            $valores[] = $cotizacion === null ? '1' : (string) $cotizacion;
        }

        if ($cuando === '') {
            return ['1', []];
        }

        return ['(CASE productos.moneda_id' . $cuando . ' ELSE 1 END)', $valores];
    }
}
