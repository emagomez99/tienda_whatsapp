<?php

namespace App\Support;

/**
 * Recorta los márgenes transparentes de una imagen (PNG, WebP o GIF).
 *
 * Los logos suelen exportarse sobre un lienzo cuadrado con mucho aire alrededor
 * (ej. 1080x1080 con el dibujo ocupando el 40% del alto). Como la cabecera limita
 * el alto del logo, ese aire se come el tamaño visible: se recorta al subirlo.
 *
 * Solo recorta transparencia, nunca un fondo de color: un fondo sólido puede ser
 * parte del diseño del logo.
 */
class RecortadorDeMargenes
{
    // Píxeles con alfa GD >= este valor cuentan como vacíos (127 = transparente
    // total). Tolera el halo casi invisible que dejan algunos exportadores.
    const ALFA_VACIO = 120;

    /**
     * Recorta el archivo en el lugar. Devuelve true si lo modificó; false si no
     * tenía márgenes transparentes, si el formato no admite transparencia o si GD
     * no puede leerlo (en ese caso la imagen queda como estaba).
     */
    public static function recortar($rutaAbsoluta)
    {
        $info = @getimagesize($rutaAbsoluta);
        $tipo = $info ? $info[2] : null;

        $abrir   = [IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_GIF => 'imagecreatefromgif'];
        $guardar = [IMAGETYPE_PNG => 'imagepng', IMAGETYPE_GIF => 'imagegif'];

        if (defined('IMAGETYPE_WEBP') && function_exists('imagecreatefromwebp')) {
            $abrir[IMAGETYPE_WEBP]   = 'imagecreatefromwebp';
            $guardar[IMAGETYPE_WEBP] = 'imagewebp';
        }

        if (! isset($abrir[$tipo])) {
            return false;
        }

        $imagen = @$abrir[$tipo]($rutaAbsoluta);
        if (! $imagen) {
            return false;
        }

        if (! imageistruecolor($imagen)) {
            imagepalettetotruecolor($imagen);
        }

        $caja = self::cajaConContenido($imagen);
        $ancho = imagesx($imagen);
        $alto  = imagesy($imagen);

        $sinCambios = $caja === null
            || ($caja['x'] === 0 && $caja['y'] === 0 && $caja['ancho'] === $ancho && $caja['alto'] === $alto);

        if ($sinCambios) {
            imagedestroy($imagen);
            return false;
        }

        $recorte = imagecreatetruecolor($caja['ancho'], $caja['alto']);
        imagealphablending($recorte, false);
        imagesavealpha($recorte, true);
        imagefill($recorte, 0, 0, imagecolorallocatealpha($recorte, 0, 0, 0, 127));
        imagecopy($recorte, $imagen, 0, 0, $caja['x'], $caja['y'], $caja['ancho'], $caja['alto']);

        $guardado = $guardar[$tipo]($recorte, $rutaAbsoluta);

        imagedestroy($imagen);
        imagedestroy($recorte);

        return (bool) $guardado;
    }

    /**
     * Rectángulo mínimo que contiene todos los píxeles visibles, o null si la imagen
     * es completamente transparente. Avanza desde cada borde y corta en el primer
     * píxel visible, así no recorre la imagen entera.
     */
    private static function cajaConContenido($imagen)
    {
        $ancho = imagesx($imagen);
        $alto  = imagesy($imagen);

        $filaVisible = function ($y, $desde, $hasta) use ($imagen) {
            for ($x = $desde; $x <= $hasta; $x++) {
                if (self::esVisible($imagen, $x, $y)) {
                    return true;
                }
            }
            return false;
        };

        $columnaVisible = function ($x, $desde, $hasta) use ($imagen) {
            for ($y = $desde; $y <= $hasta; $y++) {
                if (self::esVisible($imagen, $x, $y)) {
                    return true;
                }
            }
            return false;
        };

        $arriba = 0;
        while ($arriba < $alto && ! $filaVisible($arriba, 0, $ancho - 1)) {
            $arriba++;
        }

        if ($arriba === $alto) {
            return null;
        }

        $abajo = $alto - 1;
        while ($abajo > $arriba && ! $filaVisible($abajo, 0, $ancho - 1)) {
            $abajo--;
        }

        $izquierda = 0;
        while ($izquierda < $ancho && ! $columnaVisible($izquierda, $arriba, $abajo)) {
            $izquierda++;
        }

        $derecha = $ancho - 1;
        while ($derecha > $izquierda && ! $columnaVisible($derecha, $arriba, $abajo)) {
            $derecha--;
        }

        return [
            'x'     => $izquierda,
            'y'     => $arriba,
            'ancho' => $derecha - $izquierda + 1,
            'alto'  => $abajo - $arriba + 1,
        ];
    }

    private static function esVisible($imagen, $x, $y)
    {
        return ((imagecolorat($imagen, $x, $y) >> 24) & 0x7F) < self::ALFA_VACIO;
    }
}
