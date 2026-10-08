<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Moneda;
use App\Models\Perfil;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** Listado de productos: filtro por categoría y agregar o quitar categorías en masa. */
class ProductoCategoriasEnMasaTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testcategoriasmasa';
    const TENANT_DOMAIN = 'testcategoriasmasa.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            (new PermisoSeeder())->run();
            $proveedor = Proveedor::create(['nombre' => 'Proveedor'])->id;
            $moneda = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id;

            $producto = function ($descripcion) use ($proveedor, $moneda) {
                return Producto::create([
                    'proveedor_id' => $proveedor, 'descripcion' => $descripcion, 'precio' => 1, 'moneda_id' => $moneda,
                    'modo_precio_venta' => Producto::MODO_PRECIO_MANUAL, 'disponible' => true, 'stock' => 0,
                ])->id;
            };

            $this->ids = [
                'remeras'  => Categoria::resolverCamino('Ropa > Remeras', true)->id,
                'ofertas'  => Categoria::resolverCamino('Ofertas', true)->id,
                'lisa'     => $producto('Remera lisa'),
                'rayada'   => $producto('Remera rayada'),
                'pelota'   => $producto('Pelota'),
            ];
            Producto::find($this->ids['lisa'])->categorias()->attach($this->ids['remeras']);
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function como(string $perfil = 'Administrador')
    {
        $usuario = $this->enTenant(function () use ($perfil) {
            return User::create([
                'name' => $perfil, 'email' => uniqid() . '@' . self::TENANT_DOMAIN, 'password' => bcrypt('secreto'),
                'is_admin' => true, 'activo' => true, 'perfil_id' => Perfil::where('nombre', $perfil)->value('id'),
            ]);
        });

        return $this->actingAs($usuario);
    }

    private function categoriasDe(string $producto): array
    {
        return $this->enTenant(function () use ($producto) {
            return Producto::find($this->ids[$producto])->categorias()->orderBy('categorias.id')->pluck('categorias.id')->all();
        });
    }

    /** @test */
    public function el_listado_filtra_por_categoria_con_sus_subcategorias_y_por_sin_categoria()
    {
        $ropa = $this->enTenant(function () { return Categoria::resolverCamino('Ropa')->id; });

        $this->como()->get($this->urlTenant('admin/productos?categoria=' . $ropa))
            ->assertOk()
            ->assertSee('Remera lisa')
            ->assertSee('Ropa › Remeras')
            ->assertDontSee('Pelota');

        $this->como()->get($this->urlTenant('admin/productos?categoria=sin'))
            ->assertOk()
            ->assertSee('Pelota')
            ->assertSee('Remera rayada')
            ->assertDontSee('Remera lisa');

        // Sin filtro, el aviso con el enlace a los que faltan.
        $this->como()->get($this->urlTenant('admin/productos'))
            ->assertSee('2 productos no tienen')
            ->assertSee('id="masa-barra"', false)
            ->assertSee('Crear y elegir');
    }

    /**
     * PanelDeCategorias se define antes de usarse, en el listado y en el alta. Son
     * scripts distintos del stack, así que el orden importa; en Laravel 8 depende
     * de la profundidad de las vistas que empujan (pasó con la ayuda).
     *
     * @test
     */
    public function el_panel_de_categorias_se_define_antes_de_usarse()
    {
        foreach (['admin/productos', 'admin/productos/create'] as $ruta) {
            $html = $this->como()->get($this->urlTenant($ruta))->assertOk()->getContent();

            $definida = strpos($html, 'function PanelDeCategorias');
            $usada = strpos($html, 'new PanelDeCategorias');
            $this->assertNotFalse($definida, $ruta);
            $this->assertNotFalse($usada, $ruta);
            $this->assertLessThan($usada, $definida, $ruta);
        }
    }

    /** @test */
    public function agregar_a_los_marcados_suma_sin_duplicar()
    {
        $this->como()->post($this->urlTenant('admin/productos/categorias'), [
            'accion' => 'asignar', 'alcance' => 'seleccion',
            'categorias' => [$this->ids['remeras'], $this->ids['ofertas']],
            'productos' => [$this->ids['lisa'], $this->ids['rayada']],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame([$this->ids['remeras'], $this->ids['ofertas']], $this->categoriasDe('lisa'));
        $this->assertSame([$this->ids['remeras'], $this->ids['ofertas']], $this->categoriasDe('rayada'));
        $this->assertSame([], $this->categoriasDe('pelota'));
    }

    /** @test */
    public function agregar_a_todos_los_del_filtro_usa_el_filtro_y_no_la_pagina()
    {
        $this->como()->post($this->urlTenant('admin/productos/categorias'), [
            'accion' => 'asignar', 'alcance' => 'filtro',
            'categorias' => [$this->ids['ofertas']],
            'filtro' => ['buscar' => 'remera'],
        ])->assertRedirect($this->urlTenant('admin/productos?buscar=remera'));

        $this->assertContains($this->ids['ofertas'], $this->categoriasDe('lisa'));
        $this->assertContains($this->ids['ofertas'], $this->categoriasDe('rayada'));
        $this->assertSame([], $this->categoriasDe('pelota'));
    }

    /** @test */
    public function quitar_saca_solo_la_categoria_marcada()
    {
        $this->enTenant(function () {
            Producto::find($this->ids['lisa'])->categorias()->attach($this->ids['ofertas']);
        });

        $this->como()->post($this->urlTenant('admin/productos/categorias'), [
            'accion' => 'quitar', 'alcance' => 'seleccion',
            'categorias' => [$this->ids['ofertas']],
            'productos' => [$this->ids['lisa'], $this->ids['pelota']],
        ])->assertSessionHas('success', 'Se quitó «Ofertas» de 1 producto.');

        $this->assertSame([$this->ids['remeras']], $this->categoriasDe('lisa'));
    }

    /** @test */
    public function sin_categorias_o_sin_productos_no_hace_nada()
    {
        $this->como()->post($this->urlTenant('admin/productos/categorias'), [
            'accion' => 'asignar', 'alcance' => 'seleccion', 'productos' => [$this->ids['pelota']],
        ])->assertSessionHasErrors('categorias');

        $this->como()->post($this->urlTenant('admin/productos/categorias'), [
            'accion' => 'asignar', 'alcance' => 'seleccion', 'categorias' => [$this->ids['ofertas']],
        ])->assertSessionHasErrors('productos');

        $this->assertSame([], $this->categoriasDe('pelota'));
    }

    /** @test */
    public function el_operador_asigna_pero_no_crea_categorias_desde_el_panel()
    {
        $this->como('Operador')->get($this->urlTenant('admin/productos'))
            ->assertOk()
            ->assertSee('id="masa-barra"', false)
            ->assertDontSee('Crear y elegir');

        $this->como('Operador')->post($this->urlTenant('admin/productos/categorias'), [
            'accion' => 'asignar', 'alcance' => 'seleccion',
            'categorias' => [$this->ids['ofertas']], 'productos' => [$this->ids['pelota']],
        ])->assertSessionHas('success');

        $this->assertSame([$this->ids['ofertas']], $this->categoriasDe('pelota'));
    }

    /** @test */
    public function quien_solo_ve_productos_no_puede_asignar()
    {
        $this->enTenant(function () {
            $perfil = Perfil::create(['nombre' => 'Consulta', 'activo' => true]);
            $perfil->permisos()->attach(Permiso::where('nombre', 'productos.ver')->pluck('id'));
        });

        $this->como('Consulta')->get($this->urlTenant('admin/productos'))
            ->assertOk()
            ->assertDontSee('id="masa-barra"', false);

        $this->como('Consulta')->post($this->urlTenant('admin/productos/categorias'), [
            'accion' => 'asignar', 'alcance' => 'seleccion',
            'categorias' => [$this->ids['ofertas']], 'productos' => [$this->ids['pelota']],
        ])->assertStatus(302)->assertSessionMissing('success');

        $this->assertSame([], $this->categoriasDe('pelota'));
    }
}
