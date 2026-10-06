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

            $this->assertSame(['Afnan'], $marca->valoresEnUso('', 20)->all());
            $this->assertSame(['Afnan'], $marca->valoresEnUso('afn', 20)->all());
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
