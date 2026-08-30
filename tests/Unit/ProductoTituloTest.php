<?php

namespace Tests\Unit;

use App\Models\Etiqueta;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Cubre el meta title generado a partir de la descripción y las etiquetas.
 *
 * Existe porque en catálogos importados la descripción suele ser un código de pieza
 * repetido: en oleomc hay 185 productos llamados "CTC-1140760", que para Google son
 * 185 páginas con el mismo título -- contenido duplicado, indexa una sola.
 *
 * No toca la base: arma las relaciones en memoria.
 */
class ProductoTituloTest extends TestCase
{
    /** Producto en memoria con sus etiquetas ya "cargadas". */
    private function producto($descripcion, array $etiquetas = [])
    {
        $producto = new Producto();
        $producto->descripcion = $descripcion;

        $relacion = new Collection();

        foreach ($etiquetas as $nombre => $valor) {
            $etiqueta = new Etiqueta();
            $etiqueta->nombre = $nombre;
            $etiqueta->visible_usuarios = true;
            $etiqueta->setRelation('pivot', new Pivot(['valor' => $valor]));

            $relacion->push($etiqueta);
        }

        $producto->setRelation('etiquetas', $relacion);

        return $producto;
    }

    /** @test */
    public function sin_etiquetas_el_titulo_es_la_descripcion()
    {
        $this->assertSame('Chicle', $this->producto('Chicle')->meta_title);
    }

    /**
     * Lo que hace que dos productos con la misma descripción dejen de tener el mismo
     * título: el modelo tiene que aparecer, por largas que sean las otras etiquetas.
     *
     * @test
     */
    public function suma_los_valores_de_las_etiquetas_visibles()
    {
        $titulo = $this->producto('CTC-1140760', [
            'Fabricante' => 'Caterpillar',
            'Aplicacion' => 'Caterpillar Forestry - Track Feller Bunchers',
            'Modelo'     => '551',
        ])->meta_title;

        $this->assertSame(
            'CTC-1140760 · Caterpillar · Caterpillar Forestry - Track Feller Bunchers · 551',
            $titulo
        );
    }

    /** @test */
    public function ignora_las_etiquetas_no_visibles_y_las_vacias()
    {
        $producto = $this->producto('Mantecol', [
            'Calorias' => '1500',
            'Tamanio'  => '',
            'Interna'  => 'no mostrar',
        ]);
        $producto->etiquetas->last()->visible_usuarios = false;

        $this->assertSame('Mantecol · 1500', $producto->meta_title);
    }

    /**
     * El meta title cargado a mano por el admin manda sobre el generado.
     *
     * @test
     */
    public function un_meta_title_manual_tiene_prioridad()
    {
        $producto = $this->producto('CTC-1140760', ['Fabricante' => 'Caterpillar']);
        $producto->meta_title = 'Filtro hidráulico para excavadora';

        $this->assertSame('Filtro hidráulico para excavadora', $producto->meta_title);
    }
}
