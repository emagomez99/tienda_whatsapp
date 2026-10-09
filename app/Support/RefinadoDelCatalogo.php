<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Lo que el cliente elige en la tienda para acomodar lo que ve, además de los
 * filtros del menú: ordenar (menor precio, más nuevos…), un rango de precio y
 * "solo con stock". Viaja en la dirección (orden, precio_desde, precio_hasta,
 * con_stock) para que se pueda compartir y volver.
 *
 * Vale igual en la página (TiendaController) y en la carga por AJAX de los filtros.
 * Sin "orden", cada página usa el suyo (el de la categoría o por nombre).
 */
final class RefinadoDelCatalogo
{
    /** Lo que ofrece "Ordenar por". '' = el de siempre de esa página. */
    const ORDENES = [
        ''                              => 'Recomendados',
        OrdenDeProductos::PRECIO_MENOR  => 'Menor precio',
        OrdenDeProductos::PRECIO_MAYOR  => 'Mayor precio',
        OrdenDeProductos::NUEVOS        => 'Más nuevos',
        OrdenDeProductos::NOMBRE        => 'Nombre (A-Z)',
    ];

    /** @var string|null */
    private $orden;

    /** @var float|null */
    private $precioDesde;

    /** @var float|null */
    private $precioHasta;

    /** @var bool */
    private $conStock;

    private function __construct(?string $orden, ?float $precioDesde, ?float $precioHasta, bool $conStock)
    {
        $this->orden = $orden;
        $this->precioDesde = $precioDesde;
        $this->precioHasta = $precioHasta;
        $this->conStock = $conStock;
    }

    public static function desde(Request $request): self
    {
        $orden = (string) $request->query('orden', '');
        $numero = function ($valor) {
            return is_numeric($valor) && (float) $valor >= 0 ? (float) $valor : null;
        };

        return new self(
            ($orden !== '' && array_key_exists($orden, self::ORDENES)) ? $orden : null,
            $numero($request->query('precio_desde')),
            $numero($request->query('precio_hasta')),
            $request->query('con_stock') === '1'
        );
    }

    /** Precio y stock. El precio se compara pasado a la moneda base: 10 dólares no son "menos de 5.000". */
    public function aplicar(Builder $query): Builder
    {
        if ($this->precioDesde !== null || $this->precioHasta !== null) {
            [$cotizacion, $valores] = OrdenDeProductos::cotizacionPorMoneda();
            $expresion = '(productos.precio * ' . $cotizacion . ')';
            if ($this->precioDesde !== null) {
                $query->whereRaw($expresion . ' >= ?', array_merge($valores, [$this->precioDesde]));
            }
            if ($this->precioHasta !== null) {
                $query->whereRaw($expresion . ' <= ?', array_merge($valores, [$this->precioHasta]));
            }
        }

        if ($this->conStock) {
            $query->where('productos.stock', '>', 0);
        }

        return $query;
    }

    /** El orden elegido por el cliente o, si no eligió, el de la página. */
    public function orden(OrdenDeProductos $porDefecto): OrdenDeProductos
    {
        return $this->orden ? OrdenDeProductos::elegido($this->orden) : $porDefecto;
    }

    public function ordenElegido(): string
    {
        return (string) $this->orden;
    }

    /** Cuántos filtros del panel puso (el orden no cuenta: está a la vista). */
    public function cantidad(): int
    {
        return ($this->precioDesde !== null || $this->precioHasta !== null ? 1 : 0) + ($this->conStock ? 1 : 0);
    }

    public function precioDesde(): ?float
    {
        return $this->precioDesde;
    }

    public function precioHasta(): ?float
    {
        return $this->precioHasta;
    }

    public function conStock(): bool
    {
        return $this->conStock;
    }
}
