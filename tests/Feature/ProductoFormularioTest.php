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

    public function test_por_encargue_viene_apagado_por_defecto()
    {
        $this->comoAdmin()->get($this->urlTenant('admin/productos/create'))
            ->assertOk()
            ->assertSee('id="por_encargue" name="por_encargue" value="1" >', false);
    }

    public function test_con_el_ajuste_prendido_por_encargue_viene_prendido_en_el_alta()
    {
        $this->enTenant(function () {
            Configuracion::establecer('por_encargue_por_defecto', 'true');
        });

        $this->comoAdmin()->get($this->urlTenant('admin/productos/create'))
            ->assertOk()
            ->assertSee('id="por_encargue" name="por_encargue" value="1" checked>', false);
    }

    public function test_el_ajuste_no_cambia_productos_existentes_ni_su_edicion()
    {
        $this->enTenant(function () {
            Configuracion::establecer('por_encargue_por_defecto', 'true');
        });

        // El producto de prueba no es por encargue: su edición lo sigue mostrando así.
        $this->comoAdmin()->get($this->urlTenant('admin/productos/' . $this->producto->id . '/edit'))
            ->assertOk()
            ->assertDontSee('id="por_encargue" name="por_encargue" value="1" checked', false);
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

    public function test_la_edicion_ofrece_ver_el_producto_en_la_tienda_en_otra_pestana()
    {
        $this->comoAdmin()->get($this->urlTenant('admin/productos/' . $this->producto->id . '/edit'))
            ->assertOk()
            ->assertSee('/producto/producto-con-stock/' . $this->producto->id . '" target="_blank"', false)
            ->assertSee('Ver en la tienda')
            ->assertSee('id="cambios-sin-guardar"', false);
    }

    public function test_la_edicion_muestra_las_especificaciones_guardadas()
    {
        $this->enTenant(function () {
            $this->producto->especificaciones()->create(['clave' => 'Peso', 'valor' => '1,5 kg']);
        });

        $this->comoAdmin()->get($this->urlTenant('admin/productos/' . $this->producto->id . '/edit'))
            ->assertOk()
            ->assertSee('name="especificaciones[0][clave]" value="Peso"', false)
            ->assertSee('name="especificaciones[0][valor]" value="1,5 kg"', false);
    }

    /**
     * Sin otros datos cargados, la sección arranca sin filas (una vacía parecía
     * obligatoria) y con el botón para agregar uno. Los textos ya no dicen
     * "Clave"/"Valor".
     */
    public function test_sin_especificaciones_la_seccion_arranca_sin_filas()
    {
        $this->comoAdmin()->get($this->urlTenant('admin/productos/' . $this->producto->id . '/edit'))
            ->assertOk()
            ->assertDontSee('name="especificaciones[0][clave]"', false)
            ->assertSee('Agregar un dato')
            ->assertSee('placeholder="Dato (ej: Envase)"', false)
            ->assertDontSee('Clave (ej:');
    }

    /**
     * Proveedores tiene su ayuda en la lista y en el formulario, y la ayuda (de
     * cualquier pantalla) se cierra con Escape: el script va una sola vez.
     */
    public function test_proveedores_tiene_ayuda_que_se_cierra_con_escape()
    {
        $proveedor = $this->producto->proveedor_id;

        foreach (['admin/proveedores', 'admin/proveedores/create', 'admin/proveedores/' . $proveedor . '/edit'] as $ruta) {
            $html = $this->comoAdmin()->get($this->urlTenant($ruta))
                ->assertOk()
                ->assertSee('Cómo funcionan los proveedores')
                ->assertSee('Letras para los códigos')
                ->getContent();

            $this->assertSame(1, substr_count($html, "e.key !== 'Escape'"), $ruta);
        }
    }

    /**
     * Listado vacío: si es por el filtro, lo dice y ofrece quitarlo; si la tienda
     * no tiene ningún producto, invita a cargar el primero.
     */
    public function test_el_listado_vacio_explica_por_que()
    {
        $this->comoAdmin()->get($this->urlTenant('admin/productos?buscar=zzzz'))
            ->assertOk()
            ->assertSee('Ningún producto coincide con el filtro')
            ->assertSee('Quitar los filtros y ver todos')
            ->assertDontSee('Cargar el primer producto');

        $this->enTenant(function () { Producto::query()->delete(); });

        $this->comoAdmin()->get($this->urlTenant('admin/productos'))
            ->assertOk()
            ->assertSee('Cargar el primer producto')
            ->assertDontSee('Ningún producto coincide');
    }

    /** Las sugerencias de especificaciones dicen cuántos productos usan cada texto. */
    public function test_las_sugerencias_de_especificaciones_traen_la_cantidad_de_productos()
    {
        $this->enTenant(function () {
            $otro = $this->producto->replicate(['public_id', 'slug']);
            $otro->save();
            $this->producto->especificaciones()->create(['clave' => 'Peso', 'valor' => '1 kg']);
            $otro->especificaciones()->create(['clave' => 'Peso', 'valor' => '1 kg']);
            $otro->especificaciones()->create(['clave' => 'Color', 'valor' => 'Rojo']);
        });

        $this->comoAdmin()->getJson($this->urlTenant('admin/especificaciones/claves'))
            ->assertOk()
            ->assertExactJson([
                ['valor' => 'Color', 'detalle' => '1 producto'],
                ['valor' => 'Peso', 'detalle' => '2 productos'],
            ]);

        $this->comoAdmin()->getJson($this->urlTenant('admin/especificaciones/valores?clave=Peso'))
            ->assertOk()
            ->assertExactJson([['valor' => '1 kg', 'detalle' => '2 productos']]);
    }
}
