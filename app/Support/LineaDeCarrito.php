<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\Variante;

/**
 * Una línea del carrito: un producto, o una variante de un producto (Remera, Talle M).
 *
 * En la sesión el carrito es clave => cantidad. La clave es el id del producto
 * ("12") o el del producto y su variante ("12-5"): la remera M y la remera L son dos
 * líneas. Un carrito guardado antes de las variantes ("12" => 2) se sigue leyendo.
 */
final class LineaDeCarrito
{
    /** @var Producto */
    public $producto;

    /** @var Variante|null */
    public $variante;

    /** @var int */
    public $cantidad;

    public function __construct(Producto $producto, ?Variante $variante, int $cantidad)
    {
        $this->producto = $producto;
        $this->variante = $variante;
        $this->cantidad = $cantidad;
    }

    public static function clave(int $productoId, ?int $varianteId = null): string
    {
        return $varianteId ? $productoId . '-' . $varianteId : (string) $productoId;
    }

    /** @return array{0: int, 1: int|null} producto_id, variante_id */
    public static function leerClave($clave): array
    {
        $partes = explode('-', (string) $clave, 2);

        return [(int) $partes[0], isset($partes[1]) ? (int) $partes[1] : null];
    }

    public function claveDeLinea(): string
    {
        return self::clave($this->producto->id, $this->variante ? $this->variante->id : null);
    }

    /** De dónde sale el stock: la variante o el producto. */
    public function origen()
    {
        return $this->variante ?: $this->producto;
    }

    public function precio()
    {
        return $this->variante ? $this->variante->precioFinal() : $this->producto->precio;
    }

    public function subtotal()
    {
        return $this->precio() * $this->cantidad;
    }

    /** "Remera lisa (M · Rojo)" o el nombre del producto. */
    public function descripcion(): string
    {
        return $this->variante ? $this->variante->descripcionCompleta() : $this->producto->descripcion;
    }

    /** El código de la variante si tiene, si no el del producto. */
    public function codigo(): ?string
    {
        return ($this->variante && $this->variante->codigo) ? $this->variante->codigo : $this->producto->id_proveedor;
    }

    /** Para las URLs de actualizar, quitar y consultar stock de esta línea. */
    public function parametros(): array
    {
        return $this->variante ? ['variante' => $this->variante->id] : [];
    }
}
