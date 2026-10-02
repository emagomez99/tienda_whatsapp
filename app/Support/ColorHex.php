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
