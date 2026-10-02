<?php

namespace Tests\Feature;

use App\Models\Moneda;
use App\Models\Pedido;
use App\Models\PedidoTotal;
use App\Models\User;
use Carbon\Carbon;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * El dashboard muestra las ventas (confirmados y facturado) del mes elegido; por
 * defecto, el actual.
 */
class DashboardMesTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testdashmes';
    const TENANT_DOMAIN = 'testdashmes.test';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 15, 12));
        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $peso = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true]);

            $this->pedido('2026-09-03 10:00:00', 'confirmado', 1000, $peso);
            $this->pedido('2026-09-30 23:30:00', 'confirmado', 2500, $peso);
            $this->pedido('2026-09-20 10:00:00', 'cancelado', 9999, $peso);
            $this->pedido('2026-10-01 00:10:00', 'confirmado', 700, $peso);
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function pedido($fecha, $estado, $total, Moneda $moneda)
    {
        $pedido = new Pedido([
            'nombre' => 'Cliente', 'apellido' => 'Prueba', 'email' => 'c@test.local', 'celular' => '123',
            'estado' => $estado,
        ]);
        $pedido->created_at = $fecha;
        $pedido->updated_at = $fecha;
        $pedido->save();

        PedidoTotal::create(['pedido_id' => $pedido->id, 'moneda_id' => $moneda->id, 'total' => $total]);
    }

    private function dashboard($mes = null)
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                ['name' => 'Admin', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]
            );
        });

        return $this->actingAs($admin)->get($this->urlTenant('admin' . ($mes ? '?mes=' . $mes : '')));
    }

    public function test_por_defecto_muestra_el_mes_actual()
    {
        $this->dashboard()->assertOk()
            ->assertViewHas('mes', function ($mes) { return $mes->parametro() === '2026-10'; })
            ->assertViewHas('pedidosStats', function ($stats) {
                return $stats['confirmados'] === 1 && (float) $stats['totales_mes']->first()->total === 700.0;
            })
            ->assertSee('Facturado oct');
    }

    public function test_un_mes_pasado_muestra_sus_ventas()
    {
        $this->dashboard('2026-09')->assertOk()
            ->assertViewHas('pedidosStats', function ($stats) {
                // Los dos confirmados de septiembre, incluido el de las 23:30 del último día;
                // el cancelado no suma.
                return $stats['confirmados'] === 2 && (float) $stats['totales_mes']->first()->total === 3500.0;
            })
            ->assertSee('Facturado sep')
            ->assertSee('Pedidos de septiembre 2026');
    }

    public function test_se_ofrecen_al_menos_los_ultimos_12_meses()
    {
        $this->dashboard()->assertViewHas('meses', function ($meses) {
            $parametros = array_map(function ($mes) { return $mes->parametro(); }, $meses);

            return count($parametros) === 12 && $parametros[0] === '2026-10' && end($parametros) === '2025-11';
        });
    }

    public function test_con_pedidos_mas_viejos_se_ofrece_desde_el_primero()
    {
        $this->enTenant(function () {
            $this->pedido('2025-03-10 10:00:00', 'confirmado', 100, Moneda::first());
        });

        $this->dashboard()->assertViewHas('meses', function ($meses) {
            return end($meses)->parametro() === '2025-03';
        });
    }

    public function test_un_mes_anterior_a_los_ofrecidos_se_acota()
    {
        $this->dashboard('2024-01')->assertViewHas('mes', function ($mes) {
            return $mes->parametro() === '2025-11';
        });
    }

    public function test_el_panel_cuenta_los_estados_del_mes_y_la_tarjeta_todos_los_pendientes()
    {
        $this->enTenant(function () {
            $this->pedido('2026-08-05 10:00:00', 'pendiente', 50, Moneda::first());
        });

        $this->dashboard('2026-09')->assertViewHas('pedidosStats', function ($stats) {
            return $stats['pendientes'] === 1        // el de agosto sigue pendiente
                && $stats['pendientes_mes'] === 0    // pero no es de septiembre
                && $stats['confirmados'] === 2
                && $stats['cancelados'] === 1;
        })->assertSee('Pedidos de septiembre 2026');
    }
}
