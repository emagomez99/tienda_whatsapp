<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Menu;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\MenuDeLaTienda;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** La página pública de cada categoría, el sitemap y el menú automático con las categorías. */
class TiendaCategoriaTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testtiendacategoria';
    const TENANT_DOMAIN = 'testtiendacategoria.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor'])->id;
            $moneda = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id;

            $remeras = Categoria::resolverCamino('Ropa > Remeras', true);
            $ocultas = Categoria::resolverCamino('Ropa > Liquidación', true);
            $ocultas->update(['visible' => false]);
            Categoria::resolverCamino('Deportes', true);

            $crear = function ($descripcion, Categoria $categoria = null) use ($proveedor, $moneda) {
                $p = Producto::create([
                    'proveedor_id' => $proveedor, 'descripcion' => $descripcion, 'precio' => 1, 'moneda_id' => $moneda,
                    'disponible' => true, 'stock' => 3,
                ]);
                if ($categoria) {
                    $p->categorias()->attach($categoria->id);
                }
            };
            $crear('Remera lisa', $remeras);
            $crear('Remera rayada', $remeras);
            $crear('Buzo viejo', $ocultas);
            $crear('Pelota suelta');

            $this->ids = ['remeras' => $remeras->id, 'liquidacion' => $ocultas->id];
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    /** @test */
    public function la_pagina_de_la_madre_incluye_las_subcategorias_visibles()
    {
        $this->get($this->urlTenant('categoria/ropa'))
            ->assertOk()
            ->assertSee('<title>Ropa', false)
            ->assertSee('Remera lisa')
            ->assertSee('Remera rayada')
            ->assertDontSee('Pelota suelta')
            // Liquidación está oculta: ni su chip ni su enlace.
            ->assertSee('/categoria/remeras', false)
            ->assertDontSee('/categoria/liquidacion', false);

        $this->get($this->urlTenant('categoria/remeras?buscar=lisa'))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://' . self::TENANT_DOMAIN . '/categoria/remeras">', false)
            ->assertSee('"name":"Ropa"', false)   // migas de pan para Google
            ->assertSee('Remera lisa');
    }

    /** @test */
    public function una_categoria_oculta_o_inexistente_da_404()
    {
        $this->get($this->urlTenant('categoria/liquidacion'))->assertNotFound();
        $this->get($this->urlTenant('categoria/no-existe'))->assertNotFound();

        // Al ocultar la madre, la hija tampoco existe para el cliente.
        $this->enTenant(function () {
            Categoria::resolverCamino('Ropa')->update(['visible' => false]);
        });
        $this->get($this->urlTenant('categoria/remeras'))->assertNotFound();
    }

    /** @test */
    public function buscar_dentro_de_la_categoria_no_sale_de_ella()
    {
        $this->getJson($this->urlTenant('productos/ajax?categoria_id=' . $this->ids['remeras'] . '&buscar=lisa'))
            ->assertOk()
            ->assertJson(['total' => 1]);

        $this->getJson($this->urlTenant('productos/ajax?categoria_id=' . $this->ids['liquidacion']))->assertNotFound();
    }

    /** @test */
    public function el_sitemap_lista_solo_las_visibles()
    {
        $this->get($this->urlTenant('sitemap-paginas.xml'))
            ->assertOk()
            ->assertSee('/categoria/ropa', false)
            ->assertSee('/categoria/remeras', false)
            ->assertDontSee('/categoria/liquidacion', false)
            ->assertDontSee('/categoria/deportes', false);   // vacía: nada que indexar
    }

    /** @test */
    public function sin_menus_armados_la_tienda_usa_las_categorias()
    {
        $this->get($this->urlTenant('/'))
            ->assertOk()
            ->assertSee('/categoria/ropa', false)
            ->assertSee('/categoria/remeras', false)
            // Oculta, y vacía: ninguna de las dos va al menú.
            ->assertDontSee('/categoria/liquidacion', false)
            ->assertDontSee('/categoria/deportes', false);

        // Con su primer producto, Deportes aparece sola.
        $this->enTenant(function () {
            Producto::where('descripcion', 'Pelota suelta')->first()->categorias()->attach(Categoria::resolverCamino('Deportes')->id);
        });
        $this->get($this->urlTenant('/'))->assertSee('/categoria/deportes', false);
    }

    /** @test */
    public function con_menus_armados_los_conserva_hasta_elegir_el_automatico()
    {
        $this->enTenant(function () {
            Menu::create(['nombre' => 'Ofertas de hoy', 'tipo_enlace' => 'categoria', 'enlace_id' => $this->ids['remeras'], 'activo' => true, 'orden' => 0]);
        });

        $this->get($this->urlTenant('/'))
            ->assertSee('Ofertas de hoy')
            ->assertDontSee('/categoria/deportes', false);

        $admin = $this->enTenant(function () {
            return User::create(['name' => 'Admin', 'email' => 'admin@' . self::TENANT_DOMAIN, 'password' => bcrypt('x'), 'is_admin' => true, 'activo' => true]);
        });
        $this->actingAs($admin)->get($this->urlTenant('admin/menus'))
            ->assertOk()
            ->assertSee('id="origen-propio" value="propio" checked', false);

        // En Categorías, la vacía avisa que no se ve en la tienda.
        $this->actingAs($admin)->get($this->urlTenant('admin/categorias'))
            ->assertSee('Vacía: todavía no se ve en la tienda');

        $this->actingAs($admin)->patch($this->urlTenant('admin/menus/origen'), ['origen' => MenuDeLaTienda::ORIGEN_CATEGORIAS])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->get($this->urlTenant('/'))
            ->assertSee('/categoria/remeras', false)
            ->assertDontSee('Ofertas de hoy');

        // En el panel: cómo queda el automático, y lo armado a mano plegado.
        $this->actingAs($admin)->get($this->urlTenant('admin/menus'))
            ->assertSee('Así se ve en la tienda')
            ->assertSee('Remeras')
            ->assertSee('Menú armado a mano');

        // El menú armado sigue ahí, y su página también.
        $this->enTenant(function () {
            $this->assertSame(1, Menu::count());
        });
        $this->get($this->urlTenant('catalogo/ofertas-de-hoy'))->assertOk()->assertSee('Remera lisa');
    }

    /**
     * La pestaña del navegador dice el producto y la tienda. Los valores de las
     * características se suman sólo si otro producto se llama igual (catálogos
     * importados con códigos repetidos), para que Google no los vea duplicados.
     *
     * @test
     */
    public function el_titulo_de_la_ficha_es_el_producto_y_la_tienda()
    {
        $producto = $this->enTenant(function () {
            return Producto::where('descripcion', 'Remera lisa')->first();
        });
        $this->get($this->urlTenant('producto/' . $producto->slugUrl() . '/' . $producto->id))
            ->assertOk()
            ->assertSee('<title>Remera lisa | Tienda MC</title>', false);

        $repetidos = $this->enTenant(function () use ($producto) {
            $marca = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);
            $otro = $producto->replicate(['slug', 'public_id']);
            $otro->save();
            $producto->etiquetas()->attach($marca->id, ['valor' => 'Acme']);
            $otro->etiquetas()->attach($marca->id, ['valor' => 'Lisa']);   // ya lo dice el nombre

            return [$producto->fresh(), $otro->fresh()];
        });

        $this->get($this->urlTenant('producto/' . $repetidos[0]->slugUrl() . '/' . $repetidos[0]->id))
            ->assertSee('<title>Remera lisa · Acme | Tienda MC</title>', false);
        $this->get($this->urlTenant('producto/' . $repetidos[1]->slugUrl() . '/' . $repetidos[1]->id))
            ->assertSee('<title>Remera lisa | Tienda MC</title>', false);
    }

    /** @test */
    public function un_dominio_sin_tienda_da_404_y_no_un_error()
    {
        // La página 404 usa el diseño de la tienda, con su menú: sin tienda no hay menú que armar.
        $this->get('http://dominio-sin-tienda.test/')->assertNotFound();
    }
}
