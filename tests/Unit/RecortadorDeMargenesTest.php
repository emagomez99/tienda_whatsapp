<?php

namespace Tests\Unit;

use App\Support\RecortadorDeMargenes;
use Tests\TestCase;

/**
 * Los logos vienen exportados sobre lienzos con mucho aire transparente; al subirlos
 * se recorta ese aire para que el dibujo use todo el alto de la cabecera.
 */
class RecortadorDeMargenesTest extends TestCase
{
    /** @var string[] */
    private $archivos = [];

    protected function tearDown(): void
    {
        foreach ($this->archivos as $archivo) {
            @unlink($archivo);
        }

        parent::tearDown();
    }

    /** Lienzo transparente de $ancho x $alto con un rectángulo opaco en la posición dada. */
    private function png($ancho, $alto, $x, $y, $anchoDibujo, $altoDibujo)
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);
        imagefill($imagen, 0, 0, imagecolorallocatealpha($imagen, 0, 0, 0, 127));
        if ($anchoDibujo > 0 && $altoDibujo > 0) {
            imagefilledrectangle($imagen, $x, $y, $x + $anchoDibujo - 1, $y + $altoDibujo - 1, imagecolorallocate($imagen, 255, 80, 0));
        }

        $ruta = tempnam(sys_get_temp_dir(), 'logo') . '.png';
        imagepng($imagen, $ruta);
        imagedestroy($imagen);

        return $this->archivos[] = $ruta;
    }

    private function tamanio($ruta)
    {
        clearstatcache();
        $info = getimagesize($ruta);

        return [$info[0], $info[1]];
    }

    public function test_recorta_el_aire_transparente_alrededor_del_dibujo()
    {
        $ruta = $this->png(300, 300, 60, 100, 150, 80);

        $this->assertTrue(RecortadorDeMargenes::recortar($ruta));
        $this->assertSame([150, 80], $this->tamanio($ruta));
    }

    public function test_conserva_la_transparencia_al_guardar()
    {
        $ruta = $this->png(100, 100, 10, 10, 50, 50);
        RecortadorDeMargenes::recortar($ruta);

        $imagen = imagecreatefrompng($ruta);
        $this->assertTrue(imageistruecolor($imagen));
        $this->assertSame(0, (imagecolorat($imagen, 0, 0) >> 24) & 0x7F, 'la esquina es parte del dibujo, opaca');
        imagedestroy($imagen);
    }

    public function test_sin_margenes_no_toca_el_archivo()
    {
        $ruta = $this->png(120, 40, 0, 0, 120, 40);

        $this->assertFalse(RecortadorDeMargenes::recortar($ruta));
        $this->assertSame([120, 40], $this->tamanio($ruta));
    }

    public function test_imagen_totalmente_transparente_queda_igual()
    {
        $ruta = $this->png(50, 50, 0, 0, 0, 0);

        $this->assertFalse(RecortadorDeMargenes::recortar($ruta));
        $this->assertSame([50, 50], $this->tamanio($ruta));
    }

    public function test_un_jpg_no_se_toca()
    {
        $imagen = imagecreatetruecolor(80, 80);
        $ruta = $this->archivos[] = tempnam(sys_get_temp_dir(), 'logo') . '.jpg';
        imagejpeg($imagen, $ruta);
        imagedestroy($imagen);

        $this->assertFalse(RecortadorDeMargenes::recortar($ruta));
        $this->assertSame([80, 80], $this->tamanio($ruta));
    }
}
