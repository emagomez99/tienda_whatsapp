<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Moneda;
use App\Models\Perfil;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\GuardadoDeProducto;
use App\Support\DatosDeProducto;
use Database\Seeders\PermisoSeeder;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** Categorías desde el formulario de producto y el alta rápida del panel. */
class ProductoCategoriasTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testprodcategorias';
    const TENANT_DOMAIN = 'testprodcategorias.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            (new PermisoSeeder())->run();
            $this->ids = [
                'proveedor' => Proveedor::create(['nombre' => 'Proveedor'])->id,
                'moneda'    => Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id,
                'remeras'   => Categoria::resolverCamino('Ropa > Remeras', true)->id,
                'ofertas'   => Categoria::resolverCamino('Ofertas', true)->id,
            ];
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function como(string $perfil = 'Administrador')
    {
        $usuario = $this->enTenant(function () use ($perfil) {
            return User::create([
                'name' => $perfil, 'email' => uniqid() . '@' . self::TENANT_DOMAIN, 'password' => bcrypt('secreto'),
                'is_admin' => true, 'activo' => true, 'perfil_id' => Perfil::where('nombre', $perfil)->value('id'),
            ]);
        });

        return $this->actingAs($usuario);
    }

    private function formulario(array $extra = []): array
    {
        return $extra + [
            'proveedor_id' => $this->ids['proveedor'], 'descripcion' => 'Remera lisa', 'stock' => 0,
            'precio' => 100, 'moneda_id' => $this->ids['moneda'], 'modo_precio_venta' => Producto::MODO_PRECIO_MANUAL,
            'disponible' => 1,
        ];
    }

    /** @test */
    public function el_alta_guarda_las_categorias_elegidas()
    {
        $this->como()->post($this->urlTenant('admin/productos'), $this->formulario([
            'categorias' => [$this->ids['remeras'], $this->ids['ofertas']],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->enTenant(function () {
            $producto = Producto::where('descripcion', 'Remera lisa')->firstOrFail();
            $this->assertEqualsCanonicalizing([$this->ids['remeras'], $this->ids['ofertas']], $producto->categorias()->pluck('categorias.id')->all());
            // Aparece también en "Ropa" sin haberla marcado.
            $this->assertSame(1, Producto::enCategoria(Categoria::resolverCamino('Ropa'))->count());
        });
    }

    /** @test */
    public function la_edicion_muestra_las_elegidas_y_quitarlas_todas_las_borra()
    {
        $id = $this->enTenant(function () {
            $producto = app(GuardadoDeProducto::class)->crear((new DatosDeProducto([
                'proveedor_id' => $this->ids['proveedor'], 'descripcion' => 'Remera lisa', 'precio' => 100,
                'moneda_id' => $this->ids['moneda'], 'modo_precio_venta' => Producto::MODO_PRECIO_MANUAL,
            ]))->conCategorias([$this->ids['remeras']]));

            return $producto->id;
        });

        $this->como()->get($this->urlTenant('admin/productos/' . $id . '/edit'))
            ->assertOk()
            ->assertSee('id="panel-categorias"', false)
            ->assertSee('var elegidas = [' . $this->ids['remeras'] . ']', false);

        // El formulario sin ninguna categoría: se quitaron todas.
        $this->como()->put($this->urlTenant('admin/productos/' . $id), $this->formulario())->assertSessionHasNoErrors();

        $this->enTenant(function () use ($id) {
            $this->assertSame(0, Producto::find($id)->categorias()->count());
        });
    }

    /** @test */
    public function una_planilla_sin_categorias_no_las_toca()
    {
        $this->enTenant(function () {
            $servicio = app(GuardadoDeProducto::class);
            $datos = new DatosDeProducto([
                'proveedor_id' => $this->ids['proveedor'], 'descripcion' => 'Remera', 'precio' => 1,
                'moneda_id' => $this->ids['moneda'], 'modo_precio_venta' => Producto::MODO_PRECIO_MANUAL,
            ]);
            $producto = $servicio->crear($datos->conCategorias([$this->ids['remeras']]));

            $servicio->actualizar($producto, $datos);   // sin conCategorias

            $this->assertSame(1, $producto->categorias()->count());
        });
    }

    /** @test */
    public function crear_una_categoria_desde_el_producto()
    {
        $ropa = $this->enTenant(function () { return Categoria::resolverCamino('Ropa')->id; });

        $this->como()->postJson($this->urlTenant('admin/categorias/rapida'), ['nombre' => 'Pantalones', 'parent_id' => $ropa])->assertCreated()->assertJson(['nombre' => 'Pantalones', 'camino' => 'Ropa > Pantalones', 'nivel' => 1]);

        $this->como()->postJson($this->urlTenant('admin/categorias/rapida'), ['nombre' => 'ofertas'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    /** @test */
    public function el_operador_elige_categorias_pero_no_las_crea()
    {
        $this->como('Operador')->get($this->urlTenant('admin/productos/create'))
            ->assertOk()
            ->assertSee('id="panel-categorias"', false)
            ->assertDontSee('Crear y elegir');
    }

    /** @test */
    public function el_formulario_pide_lo_que_exigen_sus_categorias()
    {
        $talle = $this->enTenant(function () {
            $talle = \App\Models\Etiqueta::create(['nombre' => 'Talle', 'visible_usuarios' => true]);
            Categoria::resolverCamino('Ropa')->caracteristicas()->sync([$talle->id => ['obligatoria' => true, 'orden' => 0]]);
            return $talle->id;
        });

        // El formulario recibe qué pide cada categoría (Remeras hereda Talle de Ropa).
        $this->como()->get($this->urlTenant('admin/productos/create'))
            ->assertOk()
            ->assertSee('"porCategoria"', false)
            ->assertSee('"origen":"Ropa"', false);

        $this->como()->post($this->urlTenant('admin/productos'), $this->formulario(['categorias' => [$this->ids['remeras']]]))
            ->assertSessionHasErrors(['etiquetas' => 'Falta una característica obligatoria: Talle.']);

        // En Ofertas no se pide: se guarda.
        $this->como()->post($this->urlTenant('admin/productos'), $this->formulario(['categorias' => [$this->ids['ofertas']]]))
            ->assertSessionHasNoErrors();

        $this->como()->post($this->urlTenant('admin/productos'), $this->formulario([
            'descripcion' => 'Remera con talle', 'categorias' => [$this->ids['remeras']],
            'etiquetas' => [['etiqueta_id' => $talle, 'valor' => 'M']],
        ]))->assertSessionHasNoErrors();
    }
}
