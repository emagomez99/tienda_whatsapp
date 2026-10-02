<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\User;
use Carbon\Carbon;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * Listado de pedidos filtrado por fecha, con los contadores de cada estado dentro
 * del filtro.
 */
class PedidosFiltroFechaTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testpedfecha';
    const TENANT_DOMAIN = 'testpedfecha.test';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 15, 12));
        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $this->pedido('Ana',    '2026-09-01 08:00:00', 'confirmado');
            $this->pedido('Bruno',  '2026-09-30 23:45:00', 'pendiente');
            $this->pedido('Carla',  '2026-09-15 10:00:00', 'cancelado');
            $this->pedido('Dario',  '2026-10-01 00:05:00', 'confirmado');
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function pedido($nombre, $fecha, $estado)
    {
        $pedido = new Pedido([
            'nombre' => $nombre, 'apellido' => 'Prueba', 'email' => strtolower($nombre) . '@test.local',
            'celular' => '123', 'estado' => $estado,
        ]);
        $pedido->created_at = $fecha;
        $pedido->updated_at = $fecha;
        $pedido->save();
    }

    private function listado(array $parametros = [])
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                ['name' => 'Admin', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]
            );
        });

        return $this->actingAs($admin)->get($this->urlTenant('admin/pedidos?' . http_build_query($parametros)));
    }

    private function nombres($respuesta)
    {
        return $respuesta->viewData('pedidos')->pluck('nombre')->sort()->values()->all();
    }

    public function test_al_entrar_muestra_los_ultimos_30_dias()
    {
        $respuesta = $this->listado()->assertOk();

        // Hoy es 15/10: entran del 16/9 en adelante.
        $this->assertSame(['Bruno', 'Dario'], $this->nombres($respuesta));
        $this->assertSame('2026-09-16', $respuesta->viewData('rango')->desdeTexto());
        $this->assertSame('2026-10-15', $respuesta->viewData('rango')->hastaTexto());
    }

    public function test_fechas_vacias_es_todas_las_fechas()
    {
        $respuesta = $this->listado(['desde' => '', 'hasta' => ''])->assertOk();

        $this->assertSame(['Ana', 'Bruno', 'Carla', 'Dario'], $this->nombres($respuesta));
        // Las pestañas conservan "todas las fechas" en vez de volver a los 30 días.
        $respuesta->assertSee('admin/pedidos?desde=&amp;hasta=&amp;estado=pendiente', false);
    }

    public function test_el_rango_incluye_los_dos_extremos_completos()
    {
        $respuesta = $this->listado(['desde' => '2026-09-01', 'hasta' => '2026-09-30'])->assertOk();

        // Bruno (23:45 del último día) entra; Dario (00:05 del día siguiente) no.
        $this->assertSame(['Ana', 'Bruno', 'Carla'], $this->nombres($respuesta));
    }

    public function test_los_contadores_respetan_las_fechas_y_no_el_estado()
    {
        $respuesta = $this->listado(['desde' => '2026-09-01', 'hasta' => '2026-09-30', 'estado' => 'confirmado'])->assertOk();

        $this->assertSame(['Ana'], $this->nombres($respuesta));

        $porEstado = $respuesta->viewData('porEstado');
        $this->assertSame(1, (int) $porEstado['confirmado']);
        $this->assertSame(1, (int) $porEstado['pendiente']);
        $this->assertSame(1, (int) $porEstado['cancelado']);
    }

    public function test_fecha_y_busqueda_se_combinan()
    {
        $respuesta = $this->listado(['desde' => '2026-09-01', 'buscar' => 'dario'])->assertOk();

        $this->assertSame(['Dario'], $this->nombres($respuesta));
    }

    public function test_un_estado_desconocido_se_ignora()
    {
        $this->assertCount(4, $this->listado(['estado' => 'cualquiera', 'desde' => '', 'hasta' => ''])->assertOk()->viewData('pedidos'));
    }

    public function test_el_dashboard_enlaza_a_pedidos_filtrados_por_el_mes()
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                ['name' => 'Admin', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]
            );
        });

        $this->actingAs($admin)->get($this->urlTenant('admin?mes=2026-09'))
            ->assertOk()
            ->assertSee('admin/pedidos?estado=confirmado&amp;desde=2026-09-01&amp;hasta=2026-09-30', false);
    }

    public function test_sin_fechas_el_campo_dice_todas_las_fechas()
    {
        $this->listado(['desde' => '', 'hasta' => ''])->assertOk()
            ->assertSee('placeholder="Todas las fechas"', false)
            ->assertSee('name="desde" id="desde" value=""', false);
    }

    public function test_con_fechas_las_pasa_al_campo_de_rango()
    {
        $this->listado(['desde' => '2026-09-01', 'hasta' => '2026-09-30'])->assertOk()
            ->assertSee('name="desde" id="desde" value="2026-09-01"', false)
            ->assertSee('name="hasta" id="hasta" value="2026-09-30"', false);
    }
}
