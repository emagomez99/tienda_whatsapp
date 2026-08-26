<?php

namespace Tests\Unit;

use App\Models\Producto;
use Tests\TestCase;

/**
 * Cubre la parte pura del esquema de URL /producto/{slug}/{id}:
 * la generación del slug decorativo y el segmento de slug que va en la URL.
 *
 * No toca la base: usa modelos en memoria. La resolución HTTP end-to-end
 * está cubierta en Tests\Feature\ProductoUrlSchemeTest.
 */
class ProductoSlugTest extends TestCase
{
    /** @test */
    public function genera_slug_a_partir_de_una_descripcion_normal()
    {
        $this->assertSame('turron-con-mani', Producto::generarSlug('Turron con Mani'));
        $this->assertSame('aceite-5w-30', Producto::generarSlug('Aceite 5W-30'));
    }

    /** @test */
    public function normaliza_acentos_y_mayusculas()
    {
        $this->assertSame('cafe-100-puro', Producto::generarSlug('Café 100% Puro'));
        $this->assertSame('nandu', Producto::generarSlug('Ñandú'));
    }

    /** @test */
    public function colapsa_espacios_y_simbolos_intermedios()
    {
        $this->assertSame('motor-diesel', Producto::generarSlug('Motor  Diesel'));
        $this->assertSame('perfume-amber-co', Producto::generarSlug('Perfume "Amber" & Co'));
    }

    /**
     * Caso borde: si la descripción no deja nada slugificable el slug es null,
     * y la URL usa el slug por defecto (ver slugUrl).
     *
     * @test
     */
    public function devuelve_null_cuando_no_queda_nada_slugificable()
    {
        $this->assertNull(Producto::generarSlug('!!!'));
        $this->assertNull(Producto::generarSlug(''));
        $this->assertNull(Producto::generarSlug('   '));
        $this->assertNull(Producto::generarSlug(null));
    }

    /**
     * Se recorta a 100 caracteres. El rtrim evita que el corte deje un guión colgando.
     *
     * @test
     */
    public function recorta_a_100_caracteres_sin_dejar_guion_colgando()
    {
        $slug = Producto::generarSlug(str_repeat('palabra ', 40));

        $this->assertLessThanOrEqual(100, strlen($slug));
        $this->assertStringEndsNotWith('-', $slug);
    }

    /**
     * Un slug de puros dígitos sería indistinguible de un id en la ruta corta
     * /producto/{id}: pedir el slug suelto serviría el producto con ese id, que es
     * la ambigüedad que este esquema elimina. Se le antepone un prefijo.
     *
     * @test
     */
    public function nunca_genera_un_slug_de_puros_digitos()
    {
        $this->assertFalse(ctype_digit(Producto::generarSlug('12345')));
        $this->assertFalse(ctype_digit(Producto::generarSlug('  0099  ')));
        $this->assertSame('producto-12345', Producto::generarSlug('12345'));

        // Con cualquier letra de por medio no hace falta el prefijo.
        $this->assertSame('a-12345', Producto::generarSlug('A 12345'));
    }

    /** @test */
    public function el_segmento_de_slug_es_el_slug_del_producto()
    {
        $producto = new Producto();
        $producto->slug = 'aceite-motor-5w30';

        $this->assertSame('aceite-motor-5w30', $producto->slugUrl());
    }

    /**
     * Sin slug se usa un segmento por defecto, para no dejar la URL con un
     * segmento vacío (/producto//7).
     *
     * @test
     */
    public function el_segmento_de_slug_cae_al_valor_por_defecto_cuando_no_hay_slug()
    {
        $producto = new Producto();
        $producto->slug = null;

        $this->assertSame(Producto::SLUG_POR_DEFECTO, $producto->slugUrl());
        $this->assertNotSame('', $producto->slugUrl());
    }

    /**
     * El id es la route key: las rutas internas ({producto} en carrito y admin)
     * usan sólo el id, sin el slug decorativo.
     *
     * @test
     */
    public function la_route_key_es_el_id()
    {
        $producto = new Producto();
        $producto->id = 17700;
        $producto->slug = 'aceite-motor-5w30';

        $this->assertSame('id', $producto->getRouteKeyName());
        $this->assertSame(17700, $producto->getRouteKey());
    }
}
