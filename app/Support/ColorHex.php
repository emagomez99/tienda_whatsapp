<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Color en formato #rrggbb. Además del valor, sabe qué color de texto se lee mejor
 * encima de él: la tienda deja elegir cualquier color principal, y un color claro
 * con texto blanco (lo que asumía el diseño original) queda ilegible.
 */
class ColorHex
{
    const PATRON = '/^#[0-9a-fA-F]{6}$/';

    const TEXTO_CLARO  = '#ffffff';
    const TEXTO_OSCURO = '#212529'; // el "dark" de Bootstrap

    private $hex;

    private function __construct($hex)
    {
        $this->hex = strtolower($hex);
    }

    public static function desde($hex)
    {
        if (! self::esValido($hex)) {
            throw new InvalidArgumentException("Color inválido: {$hex}. Se espera #rrggbb.");
        }

        return new self($hex);
    }

    public static function esValido($hex)
    {
        return is_string($hex) && preg_match(self::PATRON, $hex) === 1;
    }

    public function hex()
    {
        return $this->hex;
    }

    /** Componentes "r, g, b" para usar en rgba(var(--x), alfa) desde CSS. */
    public function rgb()
    {
        return implode(', ', $this->componentes());
    }

    /**
     * El color de texto (blanco u oscuro) con mejor contraste sobre este color,
     * según la razón de contraste de WCAG.
     */
    public function textoLegible()
    {
        $blanco = self::desde(self::TEXTO_CLARO);
        $oscuro = self::desde(self::TEXTO_OSCURO);

        return $this->contrasteCon($blanco) >= $this->contrasteCon($oscuro) ? $blanco : $oscuro;
    }

    public function esClaro()
    {
        return $this->textoLegible()->hex() === self::TEXTO_OSCURO;
    }

    /**
     * Este color, usado como texto sobre blanco (precios, links de la tienda). Si no
     * llega al contraste mínimo se oscurece de a poco hacia el negro, conservando el
     * tono: un amarillo de marca pasa a un ocre legible en vez de desaparecer. Los
     * colores que ya se leen bien se devuelven tal cual (no cambia la identidad).
     *
     * 3:1 es el mínimo de WCAG para texto grande y en negrita, que es como se usa.
     */
    public function legibleSobreBlanco($contrasteMinimo = 3.0)
    {
        $blanco = self::desde(self::TEXTO_CLARO);
        $color = $this;

        for ($paso = 1; $paso <= 20 && $color->contrasteCon($blanco) < $contrasteMinimo; $paso++) {
            $factor = 1 - $paso * 0.05;
            $color = self::desde('#' . implode('', array_map(function ($canal) use ($factor) {
                return str_pad(dechex((int) round($canal * $factor)), 2, '0', STR_PAD_LEFT);
            }, $this->componentes())));
        }

        return $color;
    }

    public function contrasteCon(ColorHex $otro)
    {
        $a = $this->luminancia();
        $b = $otro->luminancia();

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /** Luminancia relativa WCAG 2.x. */
    private function luminancia()
    {
        $lineal = array_map(function ($canal) {
            $c = $canal / 255;
            return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }, $this->componentes());

        return 0.2126 * $lineal[0] + 0.7152 * $lineal[1] + 0.0722 * $lineal[2];
    }

    private function componentes()
    {
        return [
            hexdec(substr($this->hex, 1, 2)),
            hexdec(substr($this->hex, 3, 2)),
            hexdec(substr($this->hex, 5, 2)),
        ];
    }
}
