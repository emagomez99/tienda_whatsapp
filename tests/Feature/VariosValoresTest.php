<?php

namespace Tests\Feature;

use App\Exports\ProductosExport;
use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Importacion;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\GuardadoDeProducto;
use App\Services\ImportacionDeProductos;
use App\Support\DatosDeProducto;
use Database\Seeders\PermisoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\Common\Creator\ReaderEntityFactory;
use OpenSpout\Writer\Common\Creator\WriterEntityFactory;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** Etapa 4: una característica puede admitir varios valores en un producto (Talle: S, M, L). */
class VariosValoresTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testvariosvalores';
    const TENANT_DOMAIN = 'testvariosvalores.test';

    /** @var array */
    private $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
        $this->enTenant(function () {
            (new PermisoSeeder())->run();
            $this->ids['proveedor'] = Proveedor::create(['nombre' => 'Textil'])->id;
            $this->ids['moneda'] = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true])->id;
            $this->ids['talle'] = Etiqueta::create(['nombre' => 'Talle', 'visible_usuarios' => true, 'varios_valores' => true])->id;
            $this->ids['marca'] = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true, 'global' => true])->id;

            $remeras = Categoria::resolverCamino('Ropa > Remeras', true);
            $remeras->caracteristicas()->attach($this->ids['talle'], ['obligatoria' => true, 'orden' => 1]);
            $this->ids['remeras'] = $remeras->id;
        });
    }

    protected function tearDown(): void
    {
        \Storage::disk('local')->deleteDirectory('importaciones/' . self::TENANT_ID);
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    private function datos(array $caracteristicas, string $nombre = 'Remera lisa'): DatosDeProducto
    {
        return (new DatosDeProducto([
            'proveedor_id' => $this->ids['proveedor'], 'descripcion' => $nombre, 'precio' => 100,
            'moneda_id' => $this->ids['moneda'], 'disponible' => true,
        ]))->conCategorias([$this->ids['remeras']])->conEtiquetas($caracteristicas);
    }

    private function admin()
    {
        $usuario = $this->enTenant(function () {
            return User::firstOrCreate(['email' => 'duenio@' . self::TENANT_DOMAIN], ['name' => 'Dueño', 'password' => bcrypt('secreto'), 'is_admin' => true, 'activo' => true]);
        });

        return $this->actingAs($usuario);
    }

    /** @test */
    public function guarda_varios_valores_sin_repetir_y_los_reemplaza_sin_tocar_los_que_quedan()
    {
        $this->enTenant(function () {
            $guardado = app(GuardadoDeProducto::class);
            $producto = $guardado->crear($this->datos([
                $this->ids['talle'] => ['S', 'M', 'm ', 'L'],
                // Marca admite uno solo: se queda el primero.
                $this->ids['marca'] => ['Lacoste', 'Otra'],
            ]));

            $this->assertSame([$this->ids['talle'] => ['S', 'M', 'L'], $this->ids['marca'] => ['Lacoste']], $producto->valoresDeCaracteristicas());

            $filaM = DB::table('producto_etiqueta')->where('producto_id', $producto->id)->where('valor', 'M')->value('id');
            $guardado->actualizar($producto, $this->datos([$this->ids['talle'] => ['M', 'XL'], $this->ids['marca'] => 'Lacoste']));

            $this->assertSame([$this->ids['talle'] => ['M', 'XL'], $this->ids['marca'] => ['Lacoste']], $producto->valoresDeCaracteristicas());
            $this->assertSame($filaM, DB::table('producto_etiqueta')->where('producto_id', $producto->id)->where('valor', 'M')->value('id'));
        });
    }

    /** @test */
    public function desde_el_formulario_se_cargan_y_la_tienda_los_muestra_juntos_y_filtra_por_cada_uno()
    {
        $this->admin()->post($this->urlTenant('admin/productos'), [
            'proveedor_id' => $this->ids['proveedor'], 'descripcion' => 'Remera rayada', 'precio' => 100,
            'moneda_id' => $this->ids['moneda'], 'disponible' => 1, 'stock' => 3,
            'categorias' => [$this->ids['remeras']],
            'etiquetas' => [
                ['etiqueta_id' => $this->ids['talle'], 'valor' => 'S'],
                ['etiqueta_id' => $this->ids['talle'], 'valor' => 'M'],
                ['etiqueta_id' => $this->ids['talle'], 'valor' => ''],   // el campo de escribir, vacío
                ['etiqueta_id' => $this->ids['marca'], 'valor' => 'Topper'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $producto = $this->enTenant(function () { return Producto::where('descripcion', 'Remera rayada')->firstOrFail(); });
        $this->enTenant(function () use ($producto) {
            $this->assertSame(['S', 'M'], $producto->valoresDeCaracteristicas()[$this->ids['talle']]);
        });

        $this->get($this->urlTenant('producto/' . $producto->slugUrl() . '/' . $producto->id))->assertOk()->assertSee('Talle: S, M');
        $this->get($this->urlTenant('categoria/remeras'))->assertSee('Talle: S, M');
        $html = $this->getJson($this->urlTenant('productos/ajax?filtros[' . $this->ids['talle'] . ']=M'))->json('html');
        $this->assertStringContainsString('Remera rayada', $html);
        $html = $this->getJson($this->urlTenant('productos/ajax?filtros[' . $this->ids['talle'] . ']=XXL'))->json('html');
        $this->assertStringNotContainsString('Remera rayada', $html);

        // La edición trae los dos valores para el formulario.
        $this->get($this->urlTenant('admin/productos/' . $producto->id . '/edit'))->assertOk()
            ->assertSee('"etiqueta_id":' . $this->ids['talle'] . ',"valor":"S"', false)
            ->assertSee('"etiqueta_id":' . $this->ids['talle'] . ',"valor":"M"', false)
            ->assertSee('"varios":true', false);
    }

    /** @test */
    public function no_se_vuelve_a_un_solo_valor_con_productos_que_tienen_varios()
    {
        $this->enTenant(function () {
            app(GuardadoDeProducto::class)->crear($this->datos([$this->ids['talle'] => ['S', 'M']]));
        });

        $url = $this->urlTenant('admin/etiquetas/' . $this->ids['talle']);
        $this->admin()->put($url, ['nombre' => 'Talle', 'visible_usuarios' => 1, 'varios_valores' => 0])
            ->assertSessionHasErrors(['varios_valores' => 'No se cambió: «Remera lisa» tiene más de un valor. Dejale uno solo y volvé a intentar.']);
        $this->assertTrue($this->enTenant(function () { return Etiqueta::find($this->ids['talle'])->varios_valores; }));

        // Con un valor por producto, sí.
        $this->enTenant(function () {
            DB::table('producto_etiqueta')->where('valor', 'M')->delete();
        });
        $this->put($url, ['nombre' => 'Talle', 'visible_usuarios' => 1, 'varios_valores' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($this->enTenant(function () { return Etiqueta::find($this->ids['talle'])->varios_valores; }));
    }

    /** @test */
    public function unir_dos_valores_que_tiene_el_mismo_producto_deja_uno()
    {
        $this->enTenant(function () {
            $producto = app(GuardadoDeProducto::class)->crear($this->datos([$this->ids['talle'] => ['M', 'Mediano', 'L']]));

            $mediano = EtiquetaValor::where('etiqueta_id', $this->ids['talle'])->where('valor', 'Mediano')->firstOrFail();
            $m = EtiquetaValor::where('etiqueta_id', $this->ids['talle'])->where('valor', 'M')->firstOrFail();
            $mediano->fusionarEn($m);

            $this->assertSame(['M', 'L'], $producto->valoresDeCaracteristicas()[$this->ids['talle']]);
        });
    }

    /** @test */
    public function en_excel_van_separados_con_punto_y_coma_y_se_vuelven_a_leer()
    {
        $this->enTenant(function () {
            $producto = app(GuardadoDeProducto::class)->crear(
                $this->datos([$this->ids['talle'] => ['S', 'M'], $this->ids['marca'] => 'Lacoste'])
            );
            $producto->update(['id_proveedor' => 'REM-1']);

            // Exportar: una celda "S; M".
            $ruta = sys_get_temp_dir() . '/exportado-' . uniqid() . '.xlsx';
            (new ProductosExport(Producto::query()))->escribir($ruta);
            $lector = ReaderEntityFactory::createReaderFromFile($ruta);
            $lector->open($ruta);
            $filas = [];
            foreach ($lector->getSheetIterator() as $hoja) {
                foreach ($hoja->getRowIterator() as $fila) {
                    $filas[] = $fila->toArray();
                }
                break;
            }
            $lector->close();
            $this->assertSame('S; M', $filas[1][array_search('Talle', $filas[0], true)]);

            // Subir: "M; L; XL" reemplaza los talles; en Marca (un solo valor) el ";" es parte del texto.
            $ruta = sys_get_temp_dir() . '/planilla-' . uniqid() . '.xlsx';
            $escritor = WriterEntityFactory::createXLSXWriter();
            $escritor->openToFile($ruta);
            $escritor->addRow(WriterEntityFactory::createRowFromArray(['Código', 'Talle', 'Marca']));
            $escritor->addRow(WriterEntityFactory::createRowFromArray(['REM-1', 'M; L;XL', 'Rip; Curl']));
            $escritor->close();

            $servicio = app(ImportacionDeProductos::class);
            $importacion = $servicio->preparar(new UploadedFile($ruta, 'productos.xlsx', null, null, true), null);
            while (!$importacion->terminada()) {
                $importacion = $servicio->aplicarLote($importacion, null);
            }

            $this->assertSame(0, $importacion->con_error, json_encode($importacion->resumen));
            $this->assertSame(
                [$this->ids['talle'] => ['M', 'L', 'XL'], $this->ids['marca'] => ['Rip; Curl']],
                Producto::find($producto->id)->valoresDeCaracteristicas()
            );
        });
    }
}
