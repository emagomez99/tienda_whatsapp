<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * Alta y edición de producto: la vista previa de cómo queda en la tienda y la barra
 * de guardar al pie.
 */
class ProductoFormularioTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testprodform';
    const TENANT_DOMAIN = 'testprodform.test';

    /** @var \App\Models\Producto */
    protected $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->producto = $this->enTenant(function () {
            $peso      = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true]);
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);
            Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);
            Etiqueta::create(['nombre' => 'Interna', 'visible_usuarios' => false]);

            return Producto::create([
                'proveedor_id' => $proveedor->id,
                'descripcion'  => 'Producto con stock',
                'precio'       => 1500,
                'moneda_id'    => $peso->id,
                'stock'        => 4,
                'disponible'   => true,
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    protected function comoAdmin()
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                ['name' => 'Admin', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]
            );
        });

        return $this->actingAs($admin);
    }

    public function test_el_alta_muestra_la_vista_previa_y_la_barra_de_guardar()
    {
        $this->comoAdmin()->get($this->urlTenant('admin/productos/create'))
            ->assertOk()
            ->assertSee('id="vista-previa-producto"', false)
            ->assertSee('Crear producto')
            ->assertSee('stockGuardado: null', false);
    }

    public function test_la_edicion_le_pasa_a_la_vista_previa_lo_que_no_esta_en_el_formulario()
    {
        $html = $this->comoAdmin()->get($this->urlTenant('admin/productos/' . $this->producto->id . '/edit'))
            ->assertOk()
            ->assertSee('id="vista-previa-producto"', false)
            ->assertSee('Guardar cambios')
            ->getContent();

        // El stock se ajusta por movimientos, no es un campo: viaja como dato.
        $this->assertStringContainsString('stockGuardado: 4', $html);

        // Solo las etiquetas visibles para el cliente aparecen en la card.
        $visibles = $this->enTenant(function () {
            return Etiqueta::where('visible_usuarios', true)->pluck('id')->map(function ($id) { return (string) $id; })->values()->all();
        });
        $this->assertStringContainsString('etiquetasVisibles: ' . json_encode($visibles), $html);
    }

    public function test_con_precios_ocultos_la_vista_previa_lo_avisa()
    {
        $this->enTenant(function () {
            Configuracion::establecer('mostrar_precios', 'false');
        });

        $this->comoAdmin()->get($this->urlTenant('admin/productos/create'))
            ->assertOk()
            ->assertSee('Los precios están ocultos en la tienda.')
            ->assertDontSee('id="vp-precio"', false);
    }
}
