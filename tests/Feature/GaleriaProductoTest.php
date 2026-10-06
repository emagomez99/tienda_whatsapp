<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * La galería del producto: una lista en orden cuya primera imagen es la principal.
 * Reordenar, cambiar la principal, agregar y quitar se envían como la lista nueva.
 */
class GaleriaProductoTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testgaleria';
    const TENANT_DOMAIN = 'testgaleria.test';

    /** @var \App\Models\Producto */
    protected $producto;

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            Configuracion::establecer('imagenes_adicionales_activas', 'true');
            Configuracion::establecer('max_imagenes_adicionales', '3');

            $peso      = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true]);
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);

            $this->producto = Producto::create([
                'proveedor_id' => $proveedor->id,
                'descripcion'  => 'Notebook',
                'precio'       => 1500,
                'moneda_id'    => $peso->id,
                'stock'        => 4,
                'disponible'   => true,
                'url_imagen'   => 'https://cdn.test/a.jpg',
            ]);

            $this->ids['b'] = ProductoImagen::create(['producto_id' => $this->producto->id, 'url' => 'https://cdn.test/b.jpg', 'orden' => 0])->id;
            $this->ids['c'] = ProductoImagen::create(['producto_id' => $this->producto->id, 'url' => 'productos/c.jpg', 'orden' => 1])->id;
        });

        Storage::disk('public')->put('productos/c.jpg', 'imagen');
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function guardar(array $galeria, array $archivos = [])
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                ['name' => 'Admin', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]
            );
        });

        return $this->actingAs($admin)->put($this->urlTenant('admin/productos/' . $this->producto->id), [
            'proveedor_id'      => $this->producto->proveedor_id,
            'descripcion'       => 'Notebook',
            'precio'            => 1500,
            'moneda_id'         => $this->producto->moneda_id,
            'modo_precio_venta' => Producto::MODO_PRECIO_MANUAL,
            'disponible'        => 1,
            'galeria_enviada'   => 1,
            'galeria'           => $galeria,
            'galeria_archivos'  => $archivos,
        ]);
    }

    /** @return string[] la principal primero y después las adicionales */
    private function urls(): array
    {
        return $this->enTenant(function () {
            $producto = $this->producto->fresh();

            return array_merge(
                $producto->url_imagen ? [$producto->url_imagen] : [],
                $producto->imagenes()->orderBy('orden')->pluck('url')->all()
            );
        });
    }

    /** @test */
    public function reordenar_cambia_la_principal()
    {
        $this->guardar(['id:' . $this->ids['b'], 'principal', 'id:' . $this->ids['c']])
            ->assertSessionHasNoErrors();

        $this->assertSame(['https://cdn.test/b.jpg', 'https://cdn.test/a.jpg', 'productos/c.jpg'], $this->urls());
    }

    /** @test */
    public function quitar_una_subida_la_borra_del_disco()
    {
        $this->guardar(['principal', 'id:' . $this->ids['b']])->assertSessionHasNoErrors();

        $this->assertSame(['https://cdn.test/a.jpg', 'https://cdn.test/b.jpg'], $this->urls());
        Storage::disk('public')->assertMissing('productos/c.jpg');
    }

    /** @test */
    public function agrega_por_url_y_por_archivo_en_el_lugar_elegido()
    {
        $this->guardar(
            ['archivo:0', 'principal', 'url:https://cdn.test/nueva.jpg'],
            [UploadedFile::fake()->image('foto.jpg')]
        )->assertSessionHasNoErrors();

        $urls = $this->urls();

        $this->assertCount(3, $urls);
        $this->assertStringStartsWith(self::TENANT_ID . '/productos/', $urls[0]);
        Storage::disk('public')->assertExists($urls[0]);
        $this->assertSame(['https://cdn.test/a.jpg', 'https://cdn.test/nueva.jpg'], array_slice($urls, 1));
    }

    /** @test */
    public function sin_imagenes_queda_sin_principal()
    {
        $this->guardar([])->assertSessionHasNoErrors();

        $this->assertSame([], $this->urls());
    }

    /** @test */
    public function no_acepta_mas_imagenes_que_el_maximo()
    {
        // 1 principal + 3 adicionales.
        $this->guardar([
            'principal', 'id:' . $this->ids['b'], 'id:' . $this->ids['c'],
            'url:https://cdn.test/d.jpg', 'url:https://cdn.test/e.jpg',
        ])->assertSessionHasErrors('galeria');

        $this->assertCount(3, $this->urls());
    }

    /** @test */
    public function rechaza_una_url_que_no_es_http()
    {
        $this->guardar(['url:javascript:alert(1)'])->assertSessionHasErrors('galeria.0');
    }

    /** @test */
    public function no_toma_imagenes_de_otro_producto()
    {
        $ajena = $this->enTenant(function () {
            $otro = $this->producto->replicate(['public_id', 'slug']);
            $otro->save();

            return ProductoImagen::create(['producto_id' => $otro->id, 'url' => 'https://cdn.test/ajena.jpg', 'orden' => 0])->id;
        });

        $this->guardar(['principal', 'id:' . $ajena])->assertSessionHasNoErrors();

        $this->assertSame(['https://cdn.test/a.jpg'], $this->urls());
    }

    /**
     * Un pedido sin la galería (no viene del formulario) no borra las imágenes.
     *
     * @test
     */
    public function sin_el_campo_de_galeria_no_toca_las_imagenes()
    {
        $admin = $this->enTenant(function () {
            return User::firstOrCreate(
                ['email' => 'admin@' . self::TENANT_DOMAIN],
                ['name' => 'Admin', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]
            );
        });

        $this->actingAs($admin)->put($this->urlTenant('admin/productos/' . $this->producto->id), [
            'proveedor_id'      => $this->producto->proveedor_id,
            'descripcion'       => 'Notebook',
            'precio'            => 1500,
            'moneda_id'         => $this->producto->moneda_id,
            'modo_precio_venta' => Producto::MODO_PRECIO_MANUAL,
        ])->assertSessionHasNoErrors();

        $this->assertCount(3, $this->urls());
    }
}
