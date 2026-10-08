<?php

namespace Tests\Feature;

use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** El código del producto es único por tienda (D10): es la clave del Excel. */
class CodigoUnicoTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testcodigounico';
    const TENANT_DOMAIN = 'testcodigounico.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor'])->id;
            $moneda = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id;
            $crear = function ($descripcion, $codigo) use ($proveedor, $moneda) {
                return Producto::create(['proveedor_id' => $proveedor, 'moneda_id' => $moneda, 'descripcion' => $descripcion,
                    'id_proveedor' => $codigo, 'precio' => 1, 'disponible' => true, 'stock' => 0])->id;
            };
            $this->ids = ['proveedor' => $proveedor, 'moneda' => $moneda,
                'coca' => $crear('Gaseosa Coca-Cola 1,5 L', 'COCA-15'), 'sinCodigo' => $crear('Fideos', null)];
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function admin()
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(['email' => 'admin@' . self::TENANT_DOMAIN], ['name' => 'Admin', 'password' => bcrypt('x'), 'is_admin' => true, 'activo' => true]);
        });

        return $this->actingAs($admin);
    }

    /** @test */
    public function la_base_no_deja_repetir_un_codigo_aunque_cambien_mayusculas_o_espacios()
    {
        $this->enTenant(function () {
            $this->assertSame($this->ids['coca'], Producto::conCodigo(' coca-15 ')->value('id'));

            // Sin código, se pueden repetir (muchos productos no tienen).
            Producto::create(['proveedor_id' => $this->ids['proveedor'], 'moneda_id' => $this->ids['moneda'], 'descripcion' => 'Arroz',
                'id_proveedor' => null, 'precio' => 1, 'disponible' => true, 'stock' => 0]);

            $this->expectException(QueryException::class);
            Producto::create(['proveedor_id' => $this->ids['proveedor'], 'moneda_id' => $this->ids['moneda'], 'descripcion' => 'Otra',
                'id_proveedor' => 'Coca-15 ', 'precio' => 1, 'disponible' => true, 'stock' => 0]);
        });
    }

    /** @test */
    public function el_formulario_avisa_quien_tiene_el_codigo()
    {
        $datos = ['proveedor_id' => $this->ids['proveedor'], 'moneda_id' => $this->ids['moneda'], 'descripcion' => 'Gaseosa nueva',
            'precio' => 1, 'stock' => 0, 'modo_precio_venta' => Producto::MODO_PRECIO_MANUAL, 'disponible' => 1];

        $this->admin()->post($this->urlTenant('admin/productos'), $datos + ['id_proveedor' => 'coca-15'])
            ->assertSessionHasErrors(['id_proveedor' => 'Ese código ya lo tiene «Gaseosa Coca-Cola 1,5 L». Cada producto necesita un código distinto.']);

        // Al editar, su propio código no cuenta como repetido.
        $this->admin()->put($this->urlTenant('admin/productos/' . $this->ids['coca']), array_merge($datos, ['descripcion' => 'Gaseosa Coca-Cola 1,5 L', 'id_proveedor' => 'COCA-15']))
            ->assertSessionHasNoErrors();
    }
}
