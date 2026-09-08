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
 * Regresión del filtro de rubro al paginar.
 *
 * La grilla de la tienda se repagina por AJAX contra /productos/ajax, y ese endpoint
 * sólo aplica el filtro del menú si recibe menu_id. El JS lo leía de `?menu=`, que
 * existe en la home pero NO en las URLs de rubro (/catalogo/{slug}, donde el menú va
 * en el path): al tocar "página 2" se pedía sin menu_id y volvía el catálogo entero.
 *
 * De ahí las dos garantías que blinda este test:
 *   - la página de rubro le pasa el id del menú al JS, para que pueda mandarlo;
 *   - /productos/ajax con menu_id respeta el filtro en cualquier página.
 */
class CatalogoPaginacionTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testcatalogo';
    const TENANT_DOMAIN = 'testcatalogo.test';

    /** @var \App\Models\Menu */
    protected $menu;

    /**
     * 13 en el rubro y 12 por página: la segunda queda con uno solo. Son los mismos
     * números del caso reportado en oleomc, donde esa página traía 12 productos
     * ajenos en lugar de ese único.
     */
    const EN_EL_RUBRO     = 13;
    const FUERA_DEL_RUBRO = 20;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);
            $moneda    = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true]);
            $etiqueta  = Etiqueta::create(['nombre' => 'Rubro', 'visible_usuarios' => true]);

            $crear = function ($descripcion) use ($proveedor, $moneda) {
                return Producto::create([
                    'proveedor_id' => $proveedor->id,
                    'descripcion'  => $descripcion,
                    'precio'       => 100,
                    'moneda_id'    => $moneda->id,
                    'disponible'   => true,
                    'stock'        => 5,
                ]);
            };

            foreach (range(1, self::EN_EL_RUBRO) as $i) {
                $crear('Accesorio ' . str_pad($i, 3, '0', STR_PAD_LEFT))
                    ->etiquetas()->attach($etiqueta->id, ['valor' => 'Accesorios']);
            }

            // Productos de otro rubro: son los que se colaban al paginar.
            foreach (range(1, self::FUERA_DEL_RUBRO) as $i) {
                $crear('Otro ' . str_pad($i, 3, '0', STR_PAD_LEFT))
                    ->etiquetas()->attach($etiqueta->id, ['valor' => 'Repuestos']);
            }

            $this->menu = Menu::create([
                'nombre'       => 'Todos',
                'slug'         => 'todos-accesorios',
                'tipo_enlace'  => 'etiqueta',
                'enlace_id'    => $etiqueta->id,
                'enlace_valor' => 'Accesorios',
                'activo'       => true,
                'orden'        => 1,
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    /**
     * Sin esto el JS no tiene de dónde sacar el menú: en /catalogo/{slug} no hay
     * ?menu= en la URL, y sin menu_id el AJAX devuelve todo el catálogo.
     *
     * @test
     */
    public function la_pagina_de_rubro_le_pasa_el_id_del_menu_al_javascript()
    {
        $this->get($this->urlTenant('catalogo/todos-accesorios'))
            ->assertStatus(200)
            ->assertSee('const MENU_ID = ' . $this->menu->id, false);
    }

    /** @test */
    public function la_primera_pagina_del_rubro_solo_trae_productos_del_rubro()
    {
        $respuesta = $this->get($this->urlTenant('catalogo/todos-accesorios'))
            ->assertStatus(200);

        $productos = $respuesta->viewData('productos');

        $this->assertSame(self::EN_EL_RUBRO, $productos->total());
        $this->assertStringNotContainsString('Otro 0', $respuesta->getContent());
    }

    /**
     * El caso reportado: la segunda página traía productos de otros rubros.
     *
     * @test
     */
    public function la_segunda_pagina_por_ajax_respeta_el_filtro_del_rubro()
    {
        $respuesta = $this->getJson($this->urlTenant(
            'productos/ajax?menu_id=' . $this->menu->id . '&page=2'
        ))->assertStatus(200);

        $html = $respuesta->json('html');

        // 13 productos con 12 por página: en la segunda queda uno solo, y del rubro.
        $this->assertSame(1, substr_count($html, 'producto-card'));
        $this->assertStringContainsString('Accesorio 013', $html);
        $this->assertStringNotContainsString('Otro 0', $html);
    }

    /** @test */
    public function el_ajax_del_rubro_no_pierde_el_filtro_en_ninguna_pagina()
    {
        foreach ([1, 2] as $pagina) {
            $html = $this->getJson($this->urlTenant(
                'productos/ajax?menu_id=' . $this->menu->id . '&page=' . $pagina
            ))->assertStatus(200)->json('html');

            $this->assertStringNotContainsString(
                'Otro 0',
                $html,
                'La página ' . $pagina . ' se llenó con productos de otro rubro.'
            );
        }
    }
}
