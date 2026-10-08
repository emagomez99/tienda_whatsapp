<?php

namespace Tests\Feature;

use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\SeoService;
use Database\Seeders\PermisoSeeder;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * Lo que encontró la revisión de seguridad: cada caso es algo que antes se podía hacer.
 */
class SeguridadTiendaTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testseguridad';
    const TENANT_DOMAIN = 'testseguridad.test';

    /** @var Producto */
    private $visible;

    /** @var Producto */
    private $oculto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
        $this->enTenant(function () {
            (new PermisoSeeder())->run();
            $proveedor = Proveedor::create(['nombre' => 'Droguería'])->id;
            $moneda = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id;
            $this->visible = Producto::create(['proveedor_id' => $proveedor, 'descripcion' => 'Alcohol en gel', 'precio' => 10, 'moneda_id' => $moneda, 'disponible' => true, 'stock' => 5]);
            $this->oculto = Producto::create(['proveedor_id' => $proveedor, 'descripcion' => 'Jarabe retirado', 'precio' => 10, 'moneda_id' => $moneda, 'disponible' => false, 'stock' => 5]);
        });
    }

    protected function tearDown(): void
    {
        config(['plataforma.usuario' => '', 'plataforma.clave' => '']);
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function urlDe(Producto $producto): string
    {
        return $this->enTenant(function () use ($producto) {
            return $this->urlTenant('producto/' . $producto->slugUrl() . '/' . $producto->id);
        });
    }

    /** @test */
    public function un_producto_oculto_no_se_ve_ni_se_puede_pedir()
    {
        $this->get($this->urlDe($this->visible))->assertOk();
        $this->get($this->urlDe($this->oculto))->assertNotFound();

        $this->postJson($this->urlTenant('carrito/agregar/' . $this->oculto->id), ['cantidad' => 1], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertJson(['success' => false]);
        $this->assertArrayNotHasKey($this->oculto->id, session('carrito', []));

        // Si se ocultó con el producto ya en el carrito, sale antes de mandar el pedido.
        $this->withSession(['carrito' => [$this->oculto->id => 2, $this->visible->id => 1]])
            ->get($this->urlTenant('carrito/checkout'))->assertOk()->assertSee('ya no está a la venta');
        $this->assertSame([$this->visible->id => 1], session('carrito'));
    }

    /** @test */
    public function quien_edita_productos_si_ve_el_oculto_para_revisarlo()
    {
        $admin = $this->enTenant(function () {
            return User::create(['name' => 'Dueño', 'email' => 'duenio@' . self::TENANT_DOMAIN, 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]);
        });

        $this->actingAs($admin)->get($this->urlDe($this->oculto))->assertOk();
    }

    /** @test */
    public function la_cantidad_tiene_que_ser_un_numero_entero_positivo()
    {
        $url = $this->urlTenant('carrito/agregar/' . $this->visible->id);

        foreach (['abc', '0', '-3', '1.5'] as $cantidad) {
            $this->postJson($url, ['cantidad' => $cantidad])->assertStatus(422);
        }
        $this->assertEmpty(session('carrito', []));

        $this->postJson($url, ['cantidad' => '2'], ['X-Requested-With' => 'XMLHttpRequest'])->assertJson(['success' => true]);
        $this->assertSame([$this->visible->id => 2], session('carrito'));
    }

    /** @test */
    public function la_descripcion_detallada_no_ejecuta_codigo_ni_la_guardada_antes()
    {
        // Guardada directo en la base, como quedó lo que se cargó antes de esta corrección.
        $this->enTenant(function () {
            $this->visible->forceFill(['detalle' => '<p>Uso externo</p><img src="x" onerror="alert(1)"><script>alert(2)</script>'])->save();
        });

        $this->get($this->urlDe($this->visible))->assertOk()
            ->assertSee('<p>Uso externo</p>', false)
            ->assertDontSee('alert(1)', false)
            ->assertDontSee('alert(2)', false);
    }

    /** @test */
    public function un_nombre_con_script_no_rompe_el_json_ld()
    {
        $json = SeoService::jsonLd(['name' => 'Crema </script><script>alert(1)</script>']);

        $this->assertStringNotContainsString('</script>', $json);
        $this->assertSame('Crema </script><script>alert(1)</script>', json_decode($json, true)['name']);
    }

    /** @test */
    public function las_respuestas_llevan_cabeceras_de_seguridad()
    {
        $this->get($this->urlTenant('/'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('X-Powered-By');
    }

    /** @test */
    public function el_panel_de_la_plataforma_pide_usuario_y_clave()
    {
        $central = 'http://' . config('tenancy.central_domains')[0] . '/tenants';

        // Desde el dominio de una tienda, no existe.
        $this->get($this->urlTenant('tenants'))->assertNotFound();

        // Sin usuario y clave configurados, cerrado.
        config(['plataforma.usuario' => '', 'plataforma.clave' => '']);
        $this->get($central)->assertForbidden();

        config(['plataforma.usuario' => 'plataforma', 'plataforma.clave' => 'una-clave-larga']);
        $this->get($central)->assertStatus(401)->assertHeader('WWW-Authenticate');
        $this->get($central, ['PHP_AUTH_USER' => 'plataforma', 'PHP_AUTH_PW' => 'otra'])->assertStatus(401);
        $this->delete(str_replace('/tenants', '/tenants/' . self::TENANT_ID, $central))->assertStatus(401);
        $this->assertNotNull(\App\Models\Tenant::find(self::TENANT_ID));

        $this->get($central, ['PHP_AUTH_USER' => 'plataforma', 'PHP_AUTH_PW' => 'una-clave-larga'])->assertOk();
    }

    /** @test */
    public function rutas_internas_del_formulario_piden_permiso()
    {
        $sinPermisos = $this->enTenant(function () {
            $perfil = \App\Models\Perfil::create(['nombre' => 'Mirón', 'activo' => true]);

            return User::create(['name' => 'Mirón', 'email' => 'miron@' . self::TENANT_DOMAIN, 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true, 'perfil_id' => $perfil->id]);
        });

        $this->actingAs($sinPermisos)->getJson($this->urlTenant('admin/productos/preview-slug?descripcion=x'))->assertStatus(403);
    }
}
