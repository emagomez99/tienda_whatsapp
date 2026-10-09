<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Menu;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** La barra de la tienda: "Ordenar por", precio, "solo con stock" y dónde van los filtros del menú. */
class RefinadoDelCatalogoTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testrefinado';
    const TENANT_DOMAIN = 'testrefinado.test';

    /** @var array */
    private $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Distribuidora'])->id;
            $pesos = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true, 'cotizacion' => 1])->id;
            $dolar = Moneda::create(['nombre' => 'Dólar', 'codigo' => 'USD', 'simbolo' => 'U$S', 'cotizacion' => 1000])->id;
            $bebidas = Categoria::resolverCamino('Bebidas', true);
            $marca = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);

            $crear = function ($nombre, $precio, $moneda, $stock, $valorMarca) use ($proveedor, $bebidas, $marca) {
                $p = Producto::create(['proveedor_id' => $proveedor, 'descripcion' => $nombre, 'precio' => $precio,
                    'moneda_id' => $moneda, 'disponible' => true, 'stock' => $stock, 'por_encargue' => $stock === 0]);
                $p->categorias()->attach($bebidas->id);
                $p->etiquetas()->attach($marca->id, ['valor' => $valorMarca]);
            };
            $crear('Agua', 500, $pesos, 10, 'Villavicencio');
            $crear('Coca-Cola', 2000, $pesos, 0, 'Coca-Cola');
            $crear('Vino importado', 3, $dolar, 4, 'Otra');   // 3.000 pesos

            $this->ids['marca'] = $marca->id;
            $this->ids['opcional'] = Menu::create(['nombre' => 'Bebidas', 'slug' => 'bebidas-menu', 'activo' => true, 'orden' => 1,
                'tipo_enlace' => Menu::TIPO_CATEGORIA, 'enlace_id' => $bebidas->id,
                'filtros_etiquetas' => [$marca->id], 'filtros_requeridos' => false])->id;
            $this->ids['requerido'] = Menu::create(['nombre' => 'Por marca', 'slug' => 'por-marca', 'activo' => true, 'orden' => 2,
                'tipo_enlace' => Menu::TIPO_CATEGORIA, 'enlace_id' => $bebidas->id,
                'filtros_etiquetas' => [$marca->id], 'filtros_requeridos' => true])->id;
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    /** @test */
    public function ordenar_por_precio_compara_en_pesos_y_el_precio_y_el_stock_filtran()
    {
        $this->get($this->urlTenant('categoria/bebidas?orden=precio_menor'))->assertOk()
            ->assertSeeInOrder(['Agua', 'Coca-Cola', 'Vino importado']);
        $this->get($this->urlTenant('categoria/bebidas?orden=precio_mayor'))->assertOk()
            ->assertSeeInOrder(['Vino importado', 'Coca-Cola', 'Agua']);

        $this->get($this->urlTenant('categoria/bebidas?precio_desde=1000&precio_hasta=2500'))->assertOk()
            ->assertSee('Coca-Cola')->assertDontSee('Vino importado')->assertDontSee('>Agua<', false)
            ->assertSee('De $1.000 a $2.500');

        $this->get($this->urlTenant('categoria/bebidas?con_stock=1'))->assertOk()
            ->assertSee('Solo con stock')->assertDontSee('Coca-Cola 1')
            ->assertSee('Vino importado');

        // La carga por AJAX respeta lo mismo.
        $html = $this->getJson($this->urlTenant('productos/ajax?menu_id=' . $this->ids['opcional'] . '&orden=precio_mayor'))->json('html');
        $this->assertLessThan(strpos($html, 'Agua'), strpos($html, 'Vino importado'));
        $html = $this->getJson($this->urlTenant('productos/ajax?menu_id=' . $this->ids['opcional'] . '&con_stock=1'))->json('html');
        $this->assertStringNotContainsString('Coca-Cola', strip_tags($html));
    }

    /** Sólo el HTML del panel "Filtrar", hasta su botón "Ver productos". */
    private function panel(string $html): string
    {
        $inicio = strpos($html, 'id="panel-refinar"');

        return substr($html, $inicio, strpos($html, 'id="refinar-aplicar"') - $inicio);
    }

    /** @test */
    public function los_filtros_opcionales_van_en_el_panel_y_los_obligatorios_a_la_vista()
    {
        $opcional = $this->get($this->urlTenant('catalogo/bebidas-menu'))->assertOk()->getContent();
        $this->assertStringContainsString('id="panel-refinar"', $opcional);
        $this->assertStringNotContainsString('id="filtros-cascada"', $opcional);   // sin la tarjeta
        $panel = $this->panel($opcional);
        $this->assertStringContainsString('filtro_' . $this->ids['marca'], $panel);

        $requerido = $this->get($this->urlTenant('catalogo/por-marca'))->assertOk()->getContent();
        $this->assertStringContainsString('id="filtros-cascada"', $requerido);
        $panel = $this->panel($requerido);
        $this->assertStringNotContainsString('filtro_' . $this->ids['marca'], $panel);
    }
}
