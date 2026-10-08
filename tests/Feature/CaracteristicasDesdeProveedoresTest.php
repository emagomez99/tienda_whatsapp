<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\CaracteristicasDesdeProveedores;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** El servicio que pasa las reglas por proveedor a las categorías, y la migración que lo usa y limpia lo viejo. */
class CaracteristicasDesdeProveedoresTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testcardesdeproveedores';
    const TENANT_DOMAIN = 'testcardesdeproveedores.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $moneda = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id;
            $e = function ($nombre) { return Etiqueta::create(['nombre' => $nombre, 'visible_usuarios' => true])->id; };
            $ids = ['marca' => $e('Marca'), 'talle' => $e('Talle'), 'color' => $e('Color'), 'categoria' => $e('Categoria'), 'sabor' => $e('Sabor')];

            $ropaSA = Proveedor::create(['nombre' => 'Ropa SA']);
            $otro = Proveedor::create(['nombre' => 'Otro']);
            $sinProductos = Proveedor::create(['nombre' => 'Golosinas']);
            // La tabla vieja ya no existe en una tienda nueva (la borra la migración de
            // limpieza): se vuelve a crear vacía con su down(), como en una base anterior.
            static::migracionDeLimpieza()->down();

            // Las reglas viejas, como quedaron en la tabla (el código ya no las usa).
            $regla = function ($proveedor, $etiqueta, $obligatoria) {
                \DB::table('proveedor_etiqueta')->insert(['proveedor_id' => $proveedor->id, 'etiqueta_id' => $etiqueta, 'obligatoria' => $obligatoria]);
            };
            $regla($ropaSA, $ids['marca'], true);
            $regla($ropaSA, $ids['talle'], true);
            $regla($ropaSA, $ids['color'], false);
            $regla($ropaSA, $ids['categoria'], true);
            $regla($otro, $ids['marca'], true);
            $regla($otro, $ids['talle'], false);
            $regla($sinProductos, $ids['sabor'], true);

            $remeras = Categoria::resolverCamino('Ropa > Remeras', true);
            $buzos = Categoria::resolverCamino('Ropa > Buzos', true);
            $medias = Categoria::resolverCamino('Medias', true);

            $crear = function ($proveedor, Categoria $categoria, array $etiquetas) use ($moneda, $ids) {
                $p = Producto::create(['proveedor_id' => $proveedor->id, 'descripcion' => uniqid(), 'precio' => 1,
                    'moneda_id' => $moneda, 'disponible' => true, 'stock' => 1]);
                $p->categorias()->attach($categoria->id);
                foreach ($etiquetas as $nombre => $valor) {
                    $p->etiquetas()->attach($ids[$nombre], ['valor' => $valor]);
                }
            };
            $crear($ropaSA, $remeras, ['marca' => 'A', 'talle' => 'M', 'color' => 'Rojo', 'categoria' => 'Ropa']);
            $crear($ropaSA, $buzos, ['marca' => 'A', 'talle' => 'L', 'color' => 'Azul']);
            // Medias tiene un producto de cada proveedor: Talle es obligatoria sólo para uno.
            $crear($ropaSA, $medias, ['marca' => 'A', 'talle' => 'S']);
            $crear($otro, $medias, ['marca' => 'B', 'talle' => 'S']);

            $this->ids = $ids + ['ropa' => $remeras->parent_id, 'remeras' => $remeras->id, 'medias' => $medias->id];
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function pide(string $categoria): array
    {
        return $this->enTenant(function () use ($categoria) {
            return Categoria::find($this->ids[$categoria])->caracteristicas->map(function ($e) {
                return $e->nombre . ($e->pivot->obligatoria ? '*' : '');
            })->all();
        });
    }

    /** La migración que usa este servicio (y la que borra la tabla vieja). */
    public static function migracionDeLimpieza()
    {
        return require base_path('database/migrations/tenant/2026_10_10_000001_migrar_etiquetas_viejas_y_limpiar.php');
    }

    private function pasar(): array
    {
        return $this->enTenant(function () {
            return (new CaracteristicasDesdeProveedores())->ejecutar();
        });
    }

    /** @test */
    public function pasa_las_reglas_a_las_categorias_y_sube_lo_comun_a_la_madre()
    {
        $informe = $this->pasar();
        $this->assertContains('Golosinas: Sabor', $informe['sinDestino']);

        $this->enTenant(function () {
            // La exigen los dos proveedores con productos: global. Categoria no pasa: ahora es la categoría.
            $this->assertTrue(Etiqueta::find($this->ids['marca'])->global);
            $this->assertTrue(Etiqueta::find($this->ids['marca'])->obligatoria_global);
            $this->assertFalse(Etiqueta::find($this->ids['categoria'])->global);
        });

        // Remeras y Buzos piden lo mismo: lo pide Ropa.
        $this->assertSame(['Talle*', 'Color'], $this->pide('ropa'));
        $this->assertSame([], $this->pide('remeras'));
        // Un proveedor no la exigía: opcional.
        $this->assertSame(['Talle'], $this->pide('medias'));
    }

    /** @test */
    public function correrlo_dos_veces_da_lo_mismo()
    {
        $this->pasar();
        $this->pasar();
        $this->assertSame(['Talle*', 'Color'], $this->pide('ropa'));
    }

    /**
     * La migración entera, como en una copia de la base de un cliente: pasa las
     * etiquetas y las reglas, y después borra lo viejo.
     *
     * @test
     */
    public function la_migracion_pasa_todo_y_despues_borra_lo_viejo()
    {
        $this->enTenant(function () {
            static::migracionDeLimpieza()->up();

            // Las reglas quedaron en las categorías…
            $this->assertTrue(Etiqueta::find($this->ids['marca'])->obligatoria_global);
            // …y lo viejo ya no está: ni la etiqueta Categoria (con sus valores) ni la tabla.
            $this->assertNull(Etiqueta::find($this->ids['categoria']));
            $this->assertSame(0, \DB::table('etiqueta_valores')->where('etiqueta_id', $this->ids['categoria'])->count());
            $this->assertFalse(\Schema::hasTable('proveedor_etiqueta'));

            // Correrla de nuevo (ya sin la tabla) no rompe ni cambia nada.
            static::migracionDeLimpieza()->up();
        });

        $this->assertSame(['Talle*', 'Color'], $this->pide('ropa'));
    }
}
