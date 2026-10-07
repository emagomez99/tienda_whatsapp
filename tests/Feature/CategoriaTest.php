<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * El árbol de categorías: caminos, niveles, productos heredados hacia arriba y
 * visibilidad heredada hacia abajo.
 */
class CategoriaTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testcategoria';
    const TENANT_DOMAIN = 'testcategoria.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function producto($nombre)
    {
        $proveedor = Proveedor::firstOrCreate(['nombre' => 'Proveedor']);
        $moneda    = Moneda::firstOrCreate(['codigo' => 'ARS'], ['nombre' => 'Peso', 'simbolo' => '$', 'es_base' => true]);

        return Producto::create([
            'proveedor_id' => $proveedor->id, 'descripcion' => $nombre, 'precio' => 100,
            'moneda_id' => $moneda->id, 'disponible' => true, 'stock' => 1,
        ]);
    }

    /** @test */
    public function resuelve_y_crea_por_camino_sin_distinguir_mayusculas()
    {
        $this->enTenant(function () {
            $remeras = Categoria::resolverCamino('Ropa > Remeras', true);

            $this->assertSame('Ropa > Remeras', $remeras->camino());
            $this->assertSame(1, $remeras->nivel());
            $this->assertSame('remeras', $remeras->slug);

            // Mismo camino escrito distinto: es la misma, no crea otra.
            $this->assertSame($remeras->id, Categoria::resolverCamino('  ropa>REMERAS ', true)->id);
            $this->assertSame(2, Categoria::count());

            // Sin crear, lo que no existe es null.
            $this->assertNull(Categoria::resolverCamino('Ropa > Pantalones'));
        });
    }

    /** @test */
    public function no_pasa_de_tres_niveles()
    {
        $this->enTenant(function () {
            $this->assertSame(2, Categoria::resolverCamino('Deportes > Pádel > Paletas', true)->nivel());

            $this->expectException(InvalidArgumentException::class);
            Categoria::resolverCamino('Deportes > Pádel > Paletas > Carbono', true);
        });
    }

    /** @test */
    public function dos_hermanas_no_pueden_llamarse_igual()
    {
        $this->enTenant(function () {
            Categoria::create(['nombre' => 'Ofertas']);

            $this->expectException(QueryException::class);
            Categoria::create(['nombre' => 'ofertas ']);
        });
    }

    /**
     * "Ropa" muestra lo de "Ropa › Remeras" sin asignarlo dos veces.
     *
     * @test
     */
    public function una_categoria_incluye_los_productos_de_sus_subcategorias()
    {
        $this->enTenant(function () {
            $remeras = Categoria::resolverCamino('Ropa > Remeras', true);
            $ropa    = $remeras->parent;
            $ofertas = Categoria::resolverCamino('Ofertas', true);

            $this->producto('Remera lisa')->categorias()->attach([$remeras->id, $ofertas->id]);
            $this->producto('Campera')->categorias()->attach($ropa->id);
            $this->producto('Sin categoría');

            $this->assertSame(['Campera', 'Remera lisa'], Producto::enCategoria($ropa)->orderBy('descripcion')->pluck('descripcion')->all());
            $this->assertSame(['Remera lisa'], Producto::enCategoria($remeras)->pluck('descripcion')->all());
            $this->assertSame(['Remera lisa'], Producto::enCategoria($ofertas)->pluck('descripcion')->all());
            $this->assertSame(['Sin categoría'], Producto::sinCategoria()->pluck('descripcion')->all());
        });
    }

    /**
     * Ocultar la madre oculta a las hijas; al mostrarla, cada hija vuelve a como
     * estaba.
     *
     * @test
     */
    public function la_visibilidad_se_hereda_sin_copiarse()
    {
        $this->enTenant(function () {
            $remeras    = Categoria::resolverCamino('Ropa > Remeras', true);
            $pantalones = Categoria::resolverCamino('Ropa > Pantalones', true);
            $ropa       = $remeras->parent;

            $pantalones->update(['visible' => false]);
            $ropa->update(['visible' => false]);

            $this->assertFalse($remeras->fresh()->esVisibleEnTienda());
            $this->assertSame($ropa->id, $remeras->fresh()->ocultaPor()->id);

            $ropa->update(['visible' => true]);

            $this->assertTrue($remeras->fresh()->esVisibleEnTienda());
            $this->assertFalse($pantalones->fresh()->esVisibleEnTienda());
        });
    }

    /** @test */
    public function no_se_borra_una_categoria_con_subcategorias()
    {
        $this->enTenant(function () {
            $ropa = Categoria::resolverCamino('Ropa > Remeras', true)->parent;

            $this->expectException(QueryException::class);
            $ropa->delete();
        });
    }
}
