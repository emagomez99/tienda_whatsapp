<?php

namespace Tests\Feature;

use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\BusquedaDeTexto;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** El buscador de la tienda: sin tildes, sin ñ y palabra por palabra (lo pidió la prueba con una farmacia). */
class BusquedaTiendaTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testbusqueda';
    const TENANT_DOMAIN = 'testbusqueda.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Droguería'])->id;
            $moneda = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id;
            $marca = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);
            $interna = Etiqueta::create(['nombre' => 'Código interno', 'visible_usuarios' => false]);

            $crear = function ($descripcion, array $etiquetas = []) use ($proveedor, $moneda) {
                $p = Producto::create(['proveedor_id' => $proveedor, 'descripcion' => $descripcion, 'precio' => 1,
                    'moneda_id' => $moneda, 'disponible' => true, 'stock' => 1]);
                $p->etiquetas()->attach($etiquetas);
            };
            $crear('Termómetro digital');
            $crear('Pañales talle G x 36');
            $crear('Shampoo anticaspa 400 ml', [$marca->id => ['valor' => 'Sedal'], $interna->id => ['valor' => 'SECRETO']]);
            $crear('Crema 100% natural');
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function buscar(string $texto): array
    {
        $html = $this->getJson($this->urlTenant('productos/ajax?' . http_build_query(['buscar' => $texto])))->assertOk()->json('html');

        return array_values(array_filter(['Termómetro digital', 'Pañales talle G x 36', 'Shampoo anticaspa 400 ml', 'Crema 100% natural'],
            function ($nombre) use ($html) { return strpos($html, e($nombre)) !== false; }));
    }

    /** @test */
    public function encuentra_sin_tildes_ni_enie_y_en_cualquier_orden()
    {
        $this->assertSame(['Termómetro digital'], $this->buscar('termometro'));
        $this->assertSame(['Termómetro digital'], $this->buscar('TERMÓMETRO'));
        $this->assertSame(['Pañales talle G x 36'], $this->buscar('panales'));
        $this->assertSame(['Shampoo anticaspa 400 ml'], $this->buscar('caspa shampoo'));
        // Por un dato visible (la marca) sí; por uno oculto no.
        $this->assertSame(['Shampoo anticaspa 400 ml'], $this->buscar('sedal'));
        $this->assertSame([], $this->buscar('secreto'));
        // Todas las palabras tienen que estar.
        $this->assertSame([], $this->buscar('shampoo termometro'));
    }

    /** @test */
    public function los_comodines_que_escribe_el_cliente_son_texto()
    {
        $this->assertSame(['Crema 100% natural'], $this->buscar('100%'));
        $this->assertSame([], $this->buscar('_'));
        $this->assertSame('%100\\%%', BusquedaDeTexto::patron('100%'));
    }

    /** @test */
    public function el_listado_del_panel_busca_igual()
    {
        $admin = $this->enTenant(function () {
            return \App\Models\User::create(['name' => 'Admin', 'email' => 'admin@' . self::TENANT_DOMAIN, 'password' => bcrypt('x'), 'is_admin' => true, 'activo' => true]);
        });

        $this->actingAs($admin)->get($this->urlTenant('admin/productos?buscar=panales'))
            ->assertSee('Pañales talle G x 36')
            ->assertDontSee('Termómetro digital');
    }
}
