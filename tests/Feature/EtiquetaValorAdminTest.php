<?php

namespace Tests\Feature;

use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Menu;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * El árbol de etiquetas del panel: ocultar, renombrar, unir y borrar valores, y
 * cómo eso se ve en la tienda.
 */
class EtiquetaValorAdminTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testetiquetaadmin';
    const TENANT_DOMAIN = 'testetiquetaadmin.test';

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

            $crear = function ($descripcion, $valorMarca) use ($proveedor, $moneda, $marca) {
                $producto = Producto::create([
                    'proveedor_id' => $proveedor->id,
                    'descripcion'  => $descripcion,
                    'precio'       => 100,
                    'moneda_id'    => $moneda->id,
                    'disponible'   => true,
                    'stock'        => 5,
                ]);
                $producto->etiquetas()->attach($marca->id, ['valor' => $valorMarca]);

                return $producto;
            };

            $crear('Perfume Uno', 'Afnan');
            $crear('Perfume Dos', 'Afnan');
            $crear('Perfume Tres', 'Afnann');

            Menu::create([
                'nombre' => 'Afnann', 'slug' => 'afnann', 'tipo_enlace' => 'etiqueta',
                'enlace_id' => $marca->id, 'enlace_valor' => 'afnann', 'activo' => true,
            ]);

            $this->ids = [
                'marca'  => $marca->id,
                'afnan'  => EtiquetaValor::where('valor', 'Afnan')->value('id'),
                'afnann' => EtiquetaValor::where('valor', 'Afnann')->value('id'),
            ];
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function comoAdmin()
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                [
                    'name'     => 'Admin de prueba',
                    'password' => bcrypt('secreto'),
                    'is_admin' => true,
                    'activo'   => true,
                ]
            );
        });

        return $this->actingAs($admin);
    }

    /** @test */
    public function el_arbol_muestra_las_etiquetas_con_sus_valores()
    {
        $this->comoAdmin()
            ->get($this->urlTenant('admin/etiquetas'))
            ->assertStatus(200)
            ->assertSee('Marca')
            ->assertSee('Afnan')
            ->assertSee('Afnann');
    }

    /** @test */
    public function la_busqueda_muestra_solo_los_valores_que_coinciden()
    {
        $html = $this->comoAdmin()
            ->get($this->urlTenant('admin/etiquetas?buscar=nann'))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('Afnann', $html);
        $this->assertStringNotContainsString('>Afnan<', $html);
    }

    /** @test */
    public function un_valor_oculto_no_se_muestra_en_la_tienda_pero_el_menu_lo_sigue_usando()
    {
        $this->comoAdmin()
            ->patch($this->urlTenant('admin/etiqueta-valores/' . $this->ids['afnann'] . '/visibilidad'))
            ->assertRedirect();

        $respuesta = $this->get($this->urlTenant('catalogo/afnann'))->assertStatus(200);

        $this->assertSame(1, $respuesta->viewData('productos')->total());
        $this->assertStringNotContainsString('Marca: Afnann', $respuesta->getContent());

        $this->assertStringContainsString(
            'Marca: Afnan',
            $this->get($this->urlTenant('/'))->getContent()
        );
    }

    /**
     * Un valor oculto es un dato interno: buscarlo no tiene que traer productos.
     *
     * @test
     */
    public function el_buscador_no_encuentra_productos_por_un_valor_oculto()
    {
        $total = function () {
            return $this->get($this->urlTenant('/?buscar=afnann'))
                ->assertStatus(200)
                ->viewData('productos')
                ->total();
        };

        $this->assertSame(1, $total());

        $this->comoAdmin()
            ->patch($this->urlTenant('admin/etiqueta-valores/' . $this->ids['afnann'] . '/visibilidad'));

        $this->assertSame(0, $total());
    }

    /** @test */
    public function renombrar_actualiza_productos_y_menus()
    {
        $this->comoAdmin()
            ->put($this->urlTenant('admin/etiqueta-valores/' . $this->ids['afnann']), ['valor' => 'Afnan Perfumes'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->enTenant(function () {
            $this->assertSame('Afnan Perfumes', Producto::where('descripcion', 'Perfume Tres')->first()->etiquetas->first()->pivot->valor);
            $this->assertSame('Afnan Perfumes', Menu::where('slug', 'afnann')->value('enlace_valor'));
        });
    }

    /**
     * Renombrar a un valor que ya existe no une nada por sí solo: vuelve proponiendo
     * la unión.
     *
     * @test
     */
    public function renombrar_a_un_valor_existente_propone_unirlos_sin_tocar_nada()
    {
        $this->comoAdmin()
            ->put($this->urlTenant('admin/etiqueta-valores/' . $this->ids['afnann']), ['valor' => 'afnan'])
            ->assertRedirect()
            ->assertSessionHas('fusion_propuesta', [
                'origen_id'  => $this->ids['afnann'],
                'destino_id' => $this->ids['afnan'],
            ]);

        $this->enTenant(function () {
            $this->assertSame(2, EtiquetaValor::count());
        });
    }

    /** @test */
    public function unir_pasa_productos_y_menus_al_otro_valor()
    {
        $this->comoAdmin()
            ->post($this->urlTenant('admin/etiqueta-valores/' . $this->ids['afnann'] . '/fusionar'), ['destino_id' => $this->ids['afnan']])
            ->assertRedirect();

        $this->enTenant(function () {
            $this->assertSame(['Afnan'], EtiquetaValor::pluck('valor')->all());
            $this->assertSame(3, Producto::conEtiqueta($this->ids['marca'], 'Afnan')->count());
            $this->assertSame('Afnan', Menu::where('slug', 'afnann')->value('enlace_valor'));
        });
    }

    /** @test */
    public function no_se_borra_un_valor_que_usan_productos()
    {
        $this->comoAdmin()
            ->delete($this->urlTenant('admin/etiqueta-valores/' . $this->ids['afnan']))
            ->assertSessionHas('error');

        $this->enTenant(function () {
            $this->assertSame(2, EtiquetaValor::count());
        });
    }

    /** @test */
    public function un_valor_sin_productos_se_puede_borrar()
    {
        $this->enTenant(function () {
            EtiquetaValor::create(['etiqueta_id' => $this->ids['marca'], 'valor' => 'Huerfano', 'normalizado' => 'huerfano']);
        });
        $id = $this->enTenant(function () {
            return EtiquetaValor::where('valor', 'Huerfano')->value('id');
        });

        $this->comoAdmin()
            ->delete($this->urlTenant('admin/etiqueta-valores/' . $id))
            ->assertSessionHas('success');

        $this->enTenant(function () {
            $this->assertNull(EtiquetaValor::where('valor', 'Huerfano')->first());
        });
    }
}
