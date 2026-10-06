<?php

namespace Tests\Feature;

use App\Models\Etiqueta;
use App\Models\Menu;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * Una etiqueta oculta es un dato interno: el cliente no la ve en el carrito, y un
 * menú que la tenía como filtro en cascada no muestra ese desplegable (quedaría
 * vacío) ni la exige para mostrar productos.
 */
class EtiquetaOcultaTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testetiquetaoculta';
    const TENANT_DOMAIN = 'testetiquetaoculta.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);
            $moneda    = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true]);
            $marca     = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);
            $interna   = Etiqueta::create(['nombre' => 'Condicion real', 'visible_usuarios' => false]);

            $producto = Producto::create([
                'proveedor_id' => $proveedor->id, 'descripcion' => 'Perfume', 'precio' => 100,
                'moneda_id' => $moneda->id, 'disponible' => true, 'stock' => 5,
            ]);
            $producto->etiquetas()->attach([
                $marca->id   => ['valor' => 'Afnan'],
                $interna->id => ['valor' => 'Le queda poco tiempo'],
            ]);

            Menu::create([
                'nombre' => 'Perfumes', 'slug' => 'perfumes', 'tipo_enlace' => 'proveedor',
                'enlace_id' => $proveedor->id, 'activo' => true,
                'filtros_etiquetas' => [(string) $interna->id, (string) $marca->id],
                'filtros_requeridos' => true,
            ]);

            $this->ids = ['producto' => $producto->id, 'marca' => $marca->id, 'interna' => $interna->id];
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    /** @test */
    public function el_carrito_no_muestra_etiquetas_ocultas()
    {
        $this->withSession(['carrito' => [$this->ids['producto'] => 1]])
            ->get($this->urlTenant('carrito'))
            ->assertOk()
            ->assertSee('Marca: Afnan')
            ->assertDontSee('Le queda poco tiempo');
    }

    /** @test */
    public function el_menu_no_dibuja_el_filtro_de_una_etiqueta_oculta()
    {
        $this->get($this->urlTenant('catalogo/perfumes'))
            ->assertOk()
            ->assertSee('id="filtro_' . $this->ids['marca'] . '"', false)
            ->assertDontSee('id="filtro_' . $this->ids['interna'] . '"', false);
    }

    /**
     * Con "completar todos los filtros", el oculto no se puede completar: no tiene
     * que contar, o el menú no mostraría nada nunca.
     *
     * @test
     */
    public function completar_los_filtros_no_exige_el_de_una_etiqueta_oculta()
    {
        $productos = $this->get($this->urlTenant('catalogo/perfumes?f' . $this->ids['marca'] . '=Afnan'))
            ->assertOk()
            ->viewData('productos');

        $this->assertSame(1, $productos->total());
    }
}
