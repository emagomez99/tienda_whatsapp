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
 * Qué productos muestra un submenú.
 *
 *   - Hereda el filtro de sus ancestros: "Notebook › Asus" son las notebooks Asus.
 *     Antes mostraba todo lo de Asus, celulares incluidos.
 *   - Bajo un contenedor no cambia nada, porque el contenedor no filtra.
 *   - El valor de la etiqueta se compara sin distinguir mayúsculas: una notebook
 *     cargada con marca "asus" también entra en el menú "Asus".
 */
class MenuFiltroHeredadoTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testmenuheredado';
    const TENANT_DOMAIN = 'testmenuheredado.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);
            $moneda    = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true]);
            $categoria = Etiqueta::create(['nombre' => 'Categoria', 'visible_usuarios' => true]);
            $marca     = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);

            $crear = function ($descripcion, $valorCategoria, $valorMarca) use ($proveedor, $moneda, $categoria, $marca) {
                $producto = Producto::create([
                    'proveedor_id' => $proveedor->id,
                    'descripcion'  => $descripcion,
                    'precio'       => 100,
                    'moneda_id'    => $moneda->id,
                    'disponible'   => true,
                    'stock'        => 5,
                ]);
                $producto->etiquetas()->attach([
                    $categoria->id => ['valor' => $valorCategoria],
                    $marca->id     => ['valor' => $valorMarca],
                ]);
            };

            $crear('Notebook Asus Vivobook', 'Notebook', 'Asus');
            $crear('Notebook Asus Zenbook', 'Notebook', 'asus');
            $crear('Notebook HP Pavilion', 'Notebook', 'HP');
            $crear('Celular Asus Zenfone', 'Celular', 'Asus');

            $notebook = Menu::create([
                'nombre' => 'Notebook', 'slug' => 'notebook', 'tipo_enlace' => 'etiqueta',
                'enlace_id' => $categoria->id, 'enlace_valor' => 'Notebook', 'activo' => true,
            ]);
            $notebookAsus = Menu::create([
                'nombre' => 'Asus', 'slug' => 'notebook-asus', 'tipo_enlace' => 'etiqueta',
                'enlace_id' => $marca->id, 'enlace_valor' => 'Asus', 'parent_id' => $notebook->id,
                'activo' => true, 'filtros_etiquetas' => [(string) $marca->id],
            ]);

            $marcas = Menu::create([
                'nombre' => 'Marcas', 'slug' => 'marcas', 'tipo_enlace' => 'ninguno', 'activo' => true,
            ]);
            Menu::create([
                'nombre' => 'Asus', 'slug' => 'marcas-asus', 'tipo_enlace' => 'etiqueta',
                'enlace_id' => $marca->id, 'enlace_valor' => 'Asus', 'parent_id' => $marcas->id, 'activo' => true,
            ]);

            $this->ids = [
                'notebookAsus' => $notebookAsus->id,
                'marca'        => $marca->id,
            ];
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function descripcionesDe($slug)
    {
        return $this->get($this->urlTenant('catalogo/' . $slug))
            ->assertStatus(200)
            ->viewData('productos')
            ->pluck('descripcion')
            ->all();
    }

    /** @test */
    public function el_submenu_suma_el_filtro_del_menu_padre()
    {
        $this->assertSame(
            ['Notebook Asus Vivobook', 'Notebook Asus Zenbook'],
            $this->descripcionesDe('notebook-asus')
        );
    }

    /** @test */
    public function bajo_un_contenedor_el_submenu_filtra_solo_por_lo_suyo()
    {
        $this->assertSame(
            ['Celular Asus Zenfone', 'Notebook Asus Vivobook', 'Notebook Asus Zenbook'],
            $this->descripcionesDe('marcas-asus')
        );
    }

    /**
     * La paginación por AJAX tiene que dar lo mismo que la página: si no, la página 2
     * vuelve a colar los celulares.
     *
     * @test
     */
    public function el_ajax_del_submenu_tambien_hereda_el_filtro()
    {
        $html = $this->getJson($this->urlTenant('productos/ajax?menu_id=' . $this->ids['notebookAsus']))
            ->assertStatus(200)
            ->json('html');

        $this->assertStringContainsString('Notebook Asus Vivobook', $html);
        $this->assertStringNotContainsString('Celular Asus Zenfone', $html);
        $this->assertStringNotContainsString('Notebook HP Pavilion', $html);
    }

    /**
     * "Asus" y "asus" son una sola opción en el filtro del cliente, y elegirla trae
     * las dos notebooks.
     *
     * @test
     */
    public function el_filtro_del_cliente_unifica_mayusculas()
    {
        $valores = $this->getJson($this->urlTenant(
            'filtros/valores?menu_id=' . $this->ids['notebookAsus'] . '&etiqueta_id=' . $this->ids['marca']
        ))->assertStatus(200)->json();

        $this->assertCount(1, $valores);
        $this->assertSame('asus', strtolower($valores[0]));

        $productos = $this->get($this->urlTenant('catalogo/notebook-asus?f' . $this->ids['marca'] . '=ASUS'))
            ->assertStatus(200)
            ->viewData('productos');

        $this->assertSame(2, $productos->total());
    }
}
