<?php

namespace Tests\Feature;

use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\GuardadoDeProducto;
use App\Support\DatosDeProducto;
use App\Support\FiltroDeProductos;
use Database\Seeders\PermisoSeeder;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** El panel "Filtrar productos": los filtros nuevos, el globito y las fichitas. */
class FiltrosDeProductosTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testfiltrosproductos';
    const TENANT_DOMAIN = 'testfiltrosproductos.test';

    /** @var array */
    private $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
        $this->enTenant(function () {
            (new PermisoSeeder())->run();
            $proveedor = Proveedor::create(['nombre' => 'Distribuidora'])->id;
            $moneda = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id;
            $this->ids['marca'] = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true])->id;

            $crear = function ($nombre, $precio, $stock, array $extra = []) use ($proveedor, $moneda) {
                return Producto::create($extra + ['proveedor_id' => $proveedor, 'descripcion' => $nombre, 'precio' => $precio,
                    'moneda_id' => $moneda, 'disponible' => true, 'stock' => $stock]);
            };
            $coca = $crear('Coca-Cola', 2000, 3, ['url_imagen' => 'https://ejemplo.com/coca.jpg']);
            $coca->etiquetas()->attach($this->ids['marca'], ['valor' => 'Coca-Cola']);
            $crear('Agua', 500, 40);
            $crear('Lavandina', 900, 0, ['por_encargue' => true]);

            // Una remera con talles (variantes).
            app(GuardadoDeProducto::class)->crear((new DatosDeProducto([
                'proveedor_id' => $proveedor, 'descripcion' => 'Remera', 'precio' => 5000, 'moneda_id' => $moneda, 'disponible' => true,
            ]))->conVariantes([['valores' => ['Talle' => 'M'], 'stock' => 2]]));
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function nombres(array $filtros): array
    {
        return $this->enTenant(function () use ($filtros) {
            return (new FiltroDeProductos($filtros))->consulta()->orderBy('descripcion')->pluck('descripcion')->all();
        });
    }

    /** @test */
    public function cada_filtro_nuevo_trae_lo_que_dice()
    {
        $this->assertSame(['Coca-Cola', 'Lavandina'], $this->nombres(['precio_desde' => '800', 'precio_hasta' => '2000']));
        $this->assertSame(['Coca-Cola', 'Remera'], $this->nombres(['stock' => 'poco']));
        $this->assertSame(['Remera'], $this->nombres(['variantes' => 'con']));
        $this->assertSame(['Coca-Cola'], $this->nombres(['foto' => 'con']));
        $this->assertSame(['Lavandina'], $this->nombres(['encargue' => '1']));
        $this->assertSame(['Coca-Cola'], $this->nombres(['caracteristica' => (string) $this->ids['marca'], 'valor' => 'coca-cola']));
        $this->assertSame(['Coca-Cola'], $this->nombres(['caracteristica' => (string) $this->ids['marca']]));
        // Combinados.
        $this->assertSame([], $this->nombres(['variantes' => 'con', 'foto' => 'con']));
    }

    /** @test */
    public function el_listado_muestra_el_globito_y_una_fichita_por_filtro()
    {
        $usuario = $this->enTenant(function () {
            return User::create(['name' => 'Dueño', 'email' => 'duenio@' . self::TENANT_DOMAIN, 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]);
        });

        $this->actingAs($usuario)->get($this->urlTenant('admin/productos?buscar=a&stock=poco&foto=con'))->assertOk()
            ->assertSee('Filtrar productos')
            ->assertSee('Filtrar (2 puestos)', false)        // el buscador no cuenta
            ->assertSee('Poco stock')
            ->assertSee('Con foto')
            ->assertSee('Quitar todos los filtros')
            ->assertSee('Coca-Cola');

        $this->get($this->urlTenant('admin/productos'))->assertOk()
            ->assertDontSee('Quitar todos los filtros');
    }
}
