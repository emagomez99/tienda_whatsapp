<?php

namespace Tests\Feature;

use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * Cada valor de etiqueta existe una sola vez y los productos lo comparten.
 *
 * Lo escrito a mano se unifica con el valor existente sin importar mayúsculas ni
 * espacios, venga de donde venga (attach del importador o sync del formulario).
 */
class EtiquetaValorTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testetiquetavalor';
    const TENANT_DOMAIN = 'testetiquetavalor.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function crearProducto($descripcion)
    {
        $proveedor = Proveedor::firstOrCreate(['nombre' => 'Proveedor de prueba']);
        $moneda    = Moneda::firstOrCreate(
            ['codigo' => 'ARS'],
            ['nombre' => 'Peso', 'simbolo' => '$', 'es_base' => true]
        );

        return Producto::create([
            'proveedor_id' => $proveedor->id,
            'descripcion'  => $descripcion,
            'precio'       => 100,
            'moneda_id'    => $moneda->id,
            'disponible'   => true,
            'stock'        => 5,
        ]);
    }

    /** @test */
    public function lo_escrito_distinto_se_unifica_con_el_valor_existente()
    {
        $this->enTenant(function () {
            $marca = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);

            $this->crearProducto('Uno')->etiquetas()->attach($marca->id, ['valor' => 'Asus']);
            $this->crearProducto('Dos')->etiquetas()->attach($marca->id, ['valor' => 'asus']);
            $this->crearProducto('Tres')->etiquetas()->attach($marca->id, ['valor' => '  ASUS ']);

            $this->assertSame(['Asus'], $marca->valores()->pluck('valor')->all());

            foreach (Producto::with('etiquetas')->get() as $producto) {
                $this->assertSame('Asus', $producto->etiquetas->first()->pivot->valor);
            }
        });
    }

    /** @test */
    public function los_espacios_repetidos_no_crean_otro_valor()
    {
        $this->enTenant(function () {
            $modelo = Etiqueta::create(['nombre' => 'Modelo', 'visible_usuarios' => true]);

            $this->crearProducto('Uno')->etiquetas()->attach($modelo->id, ['valor' => '330C LN']);
            $this->crearProducto('Dos')->etiquetas()->attach($modelo->id, ['valor' => '330C   ln']);

            $this->assertSame(1, $modelo->valores()->count());
        });
    }

    /**
     * El formulario de producto guarda con sync: al editar el valor de una etiqueta
     * también se resuelve contra la lista.
     *
     * @test
     */
    public function editar_el_valor_con_sync_tambien_unifica()
    {
        $this->enTenant(function () {
            $marca = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);

            $this->crearProducto('Uno')->etiquetas()->attach($marca->id, ['valor' => 'Afnan']);

            $dos = $this->crearProducto('Dos');
            $dos->etiquetas()->sync([$marca->id => ['valor' => 'Armaf']]);
            $dos->etiquetas()->sync([$marca->id => ['valor' => 'afnan']]);

            $fila = $dos->etiquetas()->first()->pivot;
            $this->assertSame('Afnan', $fila->valor);
            $this->assertSame(
                EtiquetaValor::where('valor', 'Afnan')->value('id'),
                (int) $fila->etiqueta_valor_id
            );
        });
    }

    /**
     * "Armaf" quedó sin productos en el test anterior: no se sigue sugiriendo.
     *
     * @test
     */
    public function el_autocompletado_solo_sugiere_valores_en_uso()
    {
        $this->enTenant(function () {
            $marca = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);

            $producto = $this->crearProducto('Uno');
            $producto->etiquetas()->sync([$marca->id => ['valor' => 'Armaf']]);
            $producto->etiquetas()->sync([$marca->id => ['valor' => 'Afnan']]);

            $this->assertSame(['Afnan'], $marca->valoresEnUso('', 20)->pluck('valor')->all());
            $this->assertSame(['Afnan'], $marca->valoresEnUso('afn', 20)->pluck('valor')->all());
        });
    }

    /**
     * Lo que se avisa debajo del valor en el formulario de producto.
     *
     * @test
     */
    public function el_estado_de_un_valor_distingue_existente_nuevo_y_parecido()
    {
        $this->enTenant(function () {
            $categoria = Etiqueta::create(['nombre' => 'Categoria', 'visible_usuarios' => true]);

            $this->crearProducto('Uno')->etiquetas()->attach($categoria->id, ['valor' => 'Notebook']);
            $this->crearProducto('Dos')->etiquetas()->attach($categoria->id, ['valor' => 'Notebook']);

            $existente = $categoria->estadoDeValor('  notebook ')->jsonSerialize();
            $this->assertSame('existente', $existente['estado']);
            $this->assertSame('Notebook', $existente['valor']);
            $this->assertSame(2, $existente['productos']);

            // Una letra de menos: error de tipeo, se propone el existente.
            $tipeo = $categoria->estadoDeValor('Notebok')->jsonSerialize();
            $this->assertSame('nuevo', $tipeo['estado']);
            $this->assertSame(['valor' => 'Notebook', 'productos' => 2], $tipeo['parecido']);

            // Otra palabra: nuevo, sin propuesta.
            $nuevo = $categoria->estadoDeValor('Celular')->jsonSerialize();
            $this->assertSame('nuevo', $nuevo['estado']);
            $this->assertNull($nuevo['parecido']);

            $this->assertSame('vacio', $categoria->estadoDeValor('   ')->estado());
        });
    }

    /**
     * Con 3 letras o menos casi todo está "a una letra" de otra cosa: no se propone.
     *
     * @test
     */
    public function no_propone_parecidos_para_textos_muy_cortos()
    {
        $this->enTenant(function () {
            $marca = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);
            $this->crearProducto('Uno')->etiquetas()->attach($marca->id, ['valor' => 'HP']);
            $this->crearProducto('Dos')->etiquetas()->attach($marca->id, ['valor' => 'LG']);

            $this->assertNull($marca->valorParecido('HQ'));
            $this->assertNull($marca->estadoDeValor('LH')->parecido());
        });
    }

    /** @test */
    public function las_sugerencias_dicen_cuantos_productos_usan_cada_valor()
    {
        $this->enTenant(function () {
            $marca = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);
            $this->crearProducto('Uno')->etiquetas()->attach($marca->id, ['valor' => 'Asus']);
            $this->crearProducto('Dos')->etiquetas()->attach($marca->id, ['valor' => 'Asus']);
            $this->crearProducto('Tres')->etiquetas()->attach($marca->id, ['valor' => 'Acer']);

            $this->assertSame(
                [['valor' => 'Acer', 'detalle' => '1 producto'], ['valor' => 'Asus', 'detalle' => '2 productos']],
                $marca->valoresEnUso('', 20)->map->comoSugerencia()->all()
            );
        });
    }

    /** @test */
    public function el_filtro_encuentra_el_valor_aunque_se_escriba_distinto()
    {
        $this->enTenant(function () {
            $marca = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);

            $this->crearProducto('Uno')->etiquetas()->attach($marca->id, ['valor' => 'John Deere']);
            $this->crearProducto('Dos');

            $this->assertSame(1, Producto::conEtiqueta($marca->id, ' john  DEERE')->count());
            $this->assertSame(1, Producto::conEtiqueta($marca->id)->count());
        });
    }
}
