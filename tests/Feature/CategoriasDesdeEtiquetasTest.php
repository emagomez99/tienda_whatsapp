<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Menu;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\CategoriasDesdeEtiquetas;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** El servicio que pasa las etiquetas Categoria/Subcategoria a categorías (lo usa la migración de limpieza). */
class CategoriasDesdeEtiquetasTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testcatdesdeetiquetas';
    const TENANT_DOMAIN = 'testcatdesdeetiquetas.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $mc = Proveedor::create(['nombre' => 'MC'])->id;
            $hercules = Proveedor::create(['nombre' => 'Hercules'])->id;
            $moneda = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id;
            $categoria = Etiqueta::create(['nombre' => 'Categoria', 'visible_usuarios' => true]);
            $subcategoria = Etiqueta::create(['nombre' => 'Subcategoría', 'visible_usuarios' => true]);

            // Una que el usuario ya armó a mano: "Notebooks" tiene que ir ahí.
            Categoria::resolverCamino('Producto > Notebooks', true);

            $crear = function ($descripcion, $proveedor, array $etiquetas = []) use ($moneda) {
                $p = Producto::create(['proveedor_id' => $proveedor, 'descripcion' => $descripcion, 'precio' => 1,
                    'moneda_id' => $moneda, 'disponible' => true, 'stock' => 1]);
                $p->etiquetas()->attach($etiquetas);
                return $p->id;
            };
            $c = $categoria->id;
            $s = $subcategoria->id;

            $this->ids = [
                'valvula'   => $crear('Válvula', $mc, [$c => ['valor' => 'Accesorios'], $s => ['valor' => 'Hidráulica']]),
                'compresor' => $crear('Compresor', $mc, [$c => ['valor' => 'Industrial'], $s => ['valor' => 'Hidráulica']]),
                'notebook'  => $crear('Notebook', $mc, [$c => ['valor' => 'notebooks']]),
                'kit'       => $crear('Kit JCB', $hercules),
                'suelto'    => $crear('Suelto', $mc),
            ];

            $accesorios = Menu::create(['nombre' => 'Accesorios', 'tipo_enlace' => 'ninguno', 'activo' => true, 'orden' => 0]);
            $this->ids['menuHidraulica'] = Menu::create(['nombre' => 'Hidráulica', 'tipo_enlace' => 'etiqueta', 'enlace_id' => $s,
                'enlace_valor' => 'Hidráulica', 'parent_id' => $accesorios->id, 'activo' => true, 'orden' => 0])->id;
            $this->ids['menuNotebooks'] = Menu::create(['nombre' => 'Notebooks', 'tipo_enlace' => 'etiqueta', 'enlace_id' => $c,
                'enlace_valor' => 'Notebooks', 'activo' => true, 'orden' => 1])->id;
            $this->ids['menuSinValor'] = Menu::create(['nombre' => 'Con categoría', 'tipo_enlace' => 'etiqueta', 'enlace_id' => $c,
                'activo' => true, 'orden' => 2])->id;
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    /** Corre el servicio, como lo hace la migración de limpieza. @return array el informe */
    private function migrar(array $porProveedor = ['Hercules' => 'Kits hidráulicos']): array
    {
        return $this->enTenant(function () use ($porProveedor) {
            return (new CategoriasDesdeEtiquetas())->ejecutar($porProveedor);
        });
    }

    private function caminosDe(string $producto): array
    {
        return $this->enTenant(function () use ($producto) {
            return Producto::find($this->ids[$producto])->categorias->map(function (Categoria $c) { return $c->camino(); })->sort()->values()->all();
        });
    }

    /** @test */
    public function el_informe_dice_que_creo_y_si_un_menu_cambia_lo_que_muestra()
    {
        $informe = $this->migrar();

        $this->assertContains('Accesorios > Hidráulica', $informe['creadas']);
        $this->assertContains('Producto > Notebooks', $informe['usadas']);
        $this->assertSame('Accesorios > Hidráulica (CAMBIA: mostraba 2, va a mostrar 1)', $informe['menus']['Hidráulica']);
        $this->assertSame('Producto > Notebooks (el mismo producto)', $informe['menus']['Notebooks']);
    }

    /** @test */
    public function asigna_usando_las_existentes_y_crea_las_que_faltan()
    {
        $this->migrar();

        $this->assertSame(['Accesorios > Hidráulica'], $this->caminosDe('valvula'));
        $this->assertSame(['Industrial > Hidráulica'], $this->caminosDe('compresor'));
        $this->assertSame(['Producto > Notebooks'], $this->caminosDe('notebook'));
        $this->assertSame(['Kits hidráulicos'], $this->caminosDe('kit'));
        $this->assertSame([], $this->caminosDe('suelto'));
    }

    /** @test */
    public function convierte_los_menus_y_deja_los_que_no_puede_resolver()
    {
        $this->migrar();

        $this->enTenant(function () {
            $hidraulica = Menu::find($this->ids['menuHidraulica']);
            $this->assertSame(Menu::TIPO_CATEGORIA, $hidraulica->tipo_enlace);
            // Dentro del menú "Accesorios": la de Accesorios, no la de Industrial.
            $this->assertSame('Accesorios > Hidráulica', $hidraulica->categoria->camino());
            $this->assertNull($hidraulica->enlace_valor);

            $this->assertSame('Producto > Notebooks', Menu::find($this->ids['menuNotebooks'])->categoria->camino());
            $this->assertSame(Menu::TIPO_ETIQUETA, Menu::find($this->ids['menuSinValor'])->tipo_enlace);
        });
    }

    /** @test */
    public function correrlo_dos_veces_da_lo_mismo()
    {
        $this->migrar();
        $this->migrar();

        $this->enTenant(function () {
            $this->assertSame(7, Categoria::count());
            $this->assertSame(4, \DB::table('categoria_producto')->count());
        });
    }

    /** @test */
    public function un_proveedor_que_no_existe_no_cambia_nada()
    {
        try {
            $this->migrar(['Nadie' => 'Algo']);
            $this->fail('Tenía que avisar que el proveedor no existe.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Nadie', $e->getMessage());
        }

        $this->enTenant(function () {
            $this->assertSame(0, \DB::table('categoria_producto')->count());
        });
    }
}
