<?php

namespace Tests\Feature;

use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** Todos los listados usan los mismos filtros (components/filtros): globito, fichitas y panel. */
class FiltrosDelPanelTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testfiltrospanel';
    const TENANT_DOMAIN = 'testfiltrospanel.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
        $this->enTenant(function () {
            (new PermisoSeeder())->run();
            $activo = Proveedor::create(['nombre' => 'Distribuidora Activa', 'activo' => true]);
            Proveedor::create(['nombre' => 'Distribuidora Vieja', 'activo' => false]);
            $moneda = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true]);

            $marca = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);
            Etiqueta::create(['nombre' => 'Talle', 'visible_usuarios' => true, 'varios_valores' => true]);
            $p = Producto::create(['proveedor_id' => $activo->id, 'descripcion' => 'Gaseosa', 'precio' => 1, 'moneda_id' => $moneda->id, 'disponible' => true, 'stock' => 1]);
            $p->etiquetas()->attach($marca->id, ['valor' => 'Arcor']);
            $marca->valores()->create(['valor' => 'Sin uso', 'normalizado' => 'sin uso']);
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function dueno()
    {
        $usuario = $this->enTenant(function () {
            return User::create(['name' => 'Dueño', 'email' => 'duenio@' . self::TENANT_DOMAIN, 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]);
        });

        return $this->actingAs($usuario);
    }

    /** @test */
    public function proveedores_usuarios_y_monedas_filtran_con_el_panel()
    {
        $this->dueno()->get($this->urlTenant('admin/proveedores?activo=0'))->assertOk()
            ->assertSee('Filtrar proveedores')
            ->assertSee('Estado: Inactivos')
            ->assertSee('Filtrar (1 puestos)', false)
            ->assertSee('Distribuidora Vieja')
            ->assertDontSee('Distribuidora Activa');

        $this->get($this->urlTenant('admin/usuarios?perfil=sin'))->assertOk()
            ->assertSee('Perfil: Sin perfil (pueden todo)')
            ->assertSee('Dueño');

        $this->get($this->urlTenant('admin/monedas?activa=1'))->assertOk()->assertSee('Estado: Activas');
    }

    /** @test */
    public function caracteristicas_y_sus_valores_filtran_con_el_panel()
    {
        $this->dueno()->get($this->urlTenant('admin/etiquetas?varios=1'))->assertOk()
            ->assertSee('Varios valores: Sí')
            ->assertSee('Talle')
            ->assertDontSee('>Marca<', false);

        $marca = $this->enTenant(function () { return Etiqueta::where('nombre', 'Marca')->value('id'); });
        $this->get($this->urlTenant('admin/etiquetas/' . $marca . '?uso=sin'))->assertOk()
            ->assertSee('Productos: Sin productos')
            ->assertViewHas('valores', function ($valores) {
                return $valores->pluck('valor')->all() === ['Sin uso'];
            });
    }

    /** @test */
    public function pedidos_muestra_las_fechas_como_fichita_y_conserva_la_pestana()
    {
        $this->dueno()->get($this->urlTenant('admin/pedidos'))->assertOk()
            ->assertSee('Fechas: Últimos 30 días')
            ->assertSee('Filtrar pedidos');

        $this->get($this->urlTenant('admin/pedidos?estado=pendiente&desde=2026-10-01&hasta=2026-10-15'))->assertOk()
            ->assertSee('Fechas: Del 01/10/2026 al 15/10/2026')
            ->assertSee('name="estado" value="pendiente"', false);

        $this->get($this->urlTenant('admin/pedidos?desde=&hasta='))->assertOk()
            ->assertDontSee('Fechas: ');
    }
}
