<?php

namespace Tests\Feature;

use App\Exports\PlantillaDeProductos;
use App\Exports\ProductosExport;
use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Importacion;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\StockMovimiento;
use App\Services\ImportacionDeProductos;
use Illuminate\Http\UploadedFile;
use OpenSpout\Reader\Common\Creator\ReaderEntityFactory;
use OpenSpout\Writer\Common\Creator\WriterEntityFactory;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** Importar productos desde Excel: vista previa, aplicar por partes, exportar y volver a subir. */
class ImportacionDeProductosTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testimportacion';
    const TENANT_DOMAIN = 'testimportacion.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();
        $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Distribuidora del Sur']);
            Proveedor::create(['nombre' => 'Otro proveedor']);
            Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$', 'es_base' => true, 'es_default' => true, 'activa' => true]);
            Moneda::create(['nombre' => 'Dólar', 'codigo' => 'USD', 'simbolo' => 'U$S', 'activa' => true]);

            $sabor = Etiqueta::create(['nombre' => 'Sabor', 'visible_usuarios' => true]);
            $marca = Etiqueta::create(['nombre' => 'Marca', 'visible_usuarios' => true]);
            $gaseosas = Categoria::resolverCamino('Bebidas > Gaseosas', true);
            $gaseosas->caracteristicas()->sync([$sabor->id => ['obligatoria' => true, 'orden' => 0]]);

            $existente = Producto::create(['proveedor_id' => $proveedor->id, 'descripcion' => 'Gaseosa Cola 1,5 L', 'id_proveedor' => 'COCA-15',
                'precio' => 1000, 'moneda_id' => Moneda::where('codigo', 'ARS')->value('id'), 'disponible' => true, 'stock' => 10]);
            $existente->categorias()->attach($gaseosas->id);
            $existente->etiquetas()->attach([$sabor->id => ['valor' => 'Cola'], $marca->id => ['valor' => 'Arcor']]);

            $this->ids = ['existente' => $existente->id, 'sabor' => $sabor->id, 'marca' => $marca->id, 'gaseosas' => $gaseosas->id];
        });
    }

    protected function tearDown(): void
    {
        // Las planillas que dejan los tests (las subidas y las exportadas).
        \Storage::disk('local')->deleteDirectory('importaciones/' . self::TENANT_ID);
        \Storage::disk('local')->deleteDirectory('pruebas');
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    /** Una planilla .xlsx con estas filas (la primera, los títulos). */
    private function planilla(array $filas): UploadedFile
    {
        $ruta = sys_get_temp_dir() . '/planilla-' . uniqid() . '.xlsx';
        $escritor = WriterEntityFactory::createXLSXWriter();
        $escritor->openToFile($ruta);
        foreach ($filas as $fila) {
            $escritor->addRow(WriterEntityFactory::createRowFromArray($fila));
        }
        $escritor->close();

        return new UploadedFile($ruta, 'productos.xlsx', null, null, true);
    }

    /** Las filas de cada hoja de un .xlsx. */
    private function leerHojas(string $ruta): array
    {
        $lector = ReaderEntityFactory::createReaderFromFile($ruta);
        $lector->open($ruta);
        $hojas = [];
        foreach ($lector->getSheetIterator() as $hoja) {
            $filas = [];
            foreach ($hoja->getRowIterator() as $fila) {
                $filas[] = $fila->toArray();
            }
            $hojas[] = $filas;
        }
        $lector->close();

        return $hojas;
    }

    private function servicio(): ImportacionDeProductos
    {
        return app(ImportacionDeProductos::class);
    }

    private function aplicarTodo(Importacion $importacion): Importacion
    {
        while (!$importacion->terminada()) {
            $importacion = $this->servicio()->aplicarLote($importacion, null);
        }
        return $importacion;
    }

    /** @test */
    public function la_vista_previa_dice_que_pasaria_sin_cambiar_nada()
    {
        $this->enTenant(function () {
            $importacion = $this->servicio()->preparar($this->planilla([
                ['Código', 'Nombre', 'Proveedor', 'Categorías', 'Precio', 'Stock', 'Sabor *', 'Marca', 'Color'],
                ['COCA-15', '', '', '', '1.250,50', '12', '', '', ''],
                ['', 'Gaseosa Naranja 2 L', 'Distribuidora del Sur', 'Bebidas > Gaseosas', '900', '5', 'Naranja', 'Arcorr', ''],
                ['', 'Agua 2 L', 'Aguas Claras', 'Bebidas > Aguas', 'mil', '', '', '', ''],
                ['', 'Sin sabor', 'Distribuidora del Sur', 'Bebidas > Gaseosas', '10', '', '', '', ''],
                ['', '', '', '', '', '', '', '', ''],
            ]), null);

            $r = $importacion->resumen;
            $this->assertSame(4, $importacion->total);          // la fila en blanco no cuenta
            $this->assertSame(1, $r['actualizar']);
            $this->assertSame(1, $r['crear']);
            $this->assertSame(2, $r['con_error']);
            $mensajes = array_column($r['errores'], 'mensaje');
            $this->assertContains('Precio: «mil» no es un precio. Escribí sólo el número, por ejemplo 1.500,50.', $mensajes);
            $this->assertContains('Falta Sabor, obligatoria en Gaseosas.', $mensajes);
            $this->assertSame(['Aguas Claras'], $r['proveedores_nuevos']);
            $this->assertSame(['Bebidas › Aguas'], $r['categorias_nuevas']);
            $this->assertSame('Arcor', $r['parecidos'][0]['existente']);
            $this->assertStringContainsString('Color', implode(' ', $r['avisos']));

            // Nada cambió todavía.
            $this->assertSame(1, Producto::count());
            $this->assertSame(10, (int) Producto::find($this->ids['existente'])->stock);
        });
    }

    /** @test */
    public function aplicar_crea_actualiza_y_deja_lo_que_no_vino_como_estaba()
    {
        $this->enTenant(function () {
            $importacion = $this->servicio()->preparar($this->planilla([
                ['Código', 'Nombre', 'Proveedor', 'Categorías', 'Precio', 'Moneda', 'Stock', 'Se muestra en la tienda', 'Sabor', 'Imagen 1'],
                ['coca-15', '', '', '', '1.250,50', '', '12', '', '', ''],
                ['NAR-2', 'Gaseosa Naranja 2 L', 'Distribuidora del Sur', 'Bebidas > Gaseosas, Ofertas', '900', 'USD', '5', 'No', 'Naranja', 'https://ejemplo.com/naranja.jpg'],
            ]), null);
            $importacion = $this->aplicarTodo($importacion);

            $this->assertSame(Importacion::TERMINADA, $importacion->estado);
            $this->assertSame(1, $importacion->creados);
            $this->assertSame(1, $importacion->actualizados);

            // Actualizado: precio y stock (como movimiento); el resto, intacto.
            $coca = Producto::find($this->ids['existente']);
            $this->assertSame('1250.50', $coca->precio);
            $this->assertSame(12, (int) $coca->stock);
            $this->assertSame('Gaseosa Cola 1,5 L', $coca->descripcion);
            $this->assertSame(['Bebidas > Gaseosas'], $coca->categorias->map->camino()->all());
            $this->assertSame('Arcor', $coca->etiquetas()->where('etiquetas.id', $this->ids['marca'])->first()->pivot->valor);
            $this->assertSame(1, StockMovimiento::where('producto_id', $coca->id)->where('tipo', StockMovimiento::TIPO_IMPORTACION)->count());

            // Creado: con todo lo que trajo, y la categoría nueva creada.
            $naranja = Producto::conCodigo('nar-2')->first();
            $this->assertSame('Gaseosa Naranja 2 L', $naranja->descripcion);
            $this->assertSame('USD', $naranja->moneda->codigo);
            $this->assertFalse($naranja->disponible);
            $this->assertSame(5, (int) $naranja->stock);
            $this->assertSame('https://ejemplo.com/naranja.jpg', $naranja->url_imagen);
            $this->assertEqualsCanonicalizing(['Bebidas > Gaseosas', 'Ofertas'], $naranja->categorias->map->camino()->all());
            $this->assertSame('Naranja', $naranja->etiquetas->first()->pivot->valor);
        });
    }

    /** @test */
    public function exportar_y_volver_a_subir_no_cambia_nada()
    {
        $this->enTenant(function () {
            $ruta = sys_get_temp_dir() . '/exportado-' . uniqid() . '.xlsx';
            (new ProductosExport(Producto::query()))->escribir($ruta);
            $archivo = new UploadedFile($ruta, 'exportado.xlsx', null, null, true);

            $importacion = $this->aplicarTodo($this->servicio()->preparar($archivo, null));

            $this->assertSame(0, $importacion->con_error, json_encode($importacion->resumen));
            $this->assertSame(1, $importacion->actualizados);
            $this->assertSame(1, Producto::count());
            $coca = Producto::find($this->ids['existente']);
            $this->assertSame('1000.00', $coca->precio);
            $this->assertSame(10, (int) $coca->stock);
            $this->assertSame(0, StockMovimiento::where('producto_id', $coca->id)->where('tipo', StockMovimiento::TIPO_IMPORTACION)->count());
        });
    }

    /** @test */
    public function la_plantilla_de_una_categoria_trae_sus_datos_y_marca_los_obligatorios()
    {
        $this->enTenant(function () {
            $ruta = sys_get_temp_dir() . '/plantilla-' . uniqid() . '.xlsx';
            (new PlantillaDeProductos(Categoria::find($this->ids['gaseosas'])))->escribir($ruta);

            $hojas = $this->leerHojas($ruta);
            $this->assertContains('Sabor *', $hojas[0][0]);
            $this->assertNotContains('Marca', $hojas[0][0]);     // Marca no la pide Gaseosas
            $this->assertSame('Columna', $hojas[1][0][0]);       // la hoja "Cómo completarla"
        });
    }

    private function como(string $perfil = null)
    {
        $usuario = $this->enTenant(function () use ($perfil) {
            (new \Database\Seeders\PermisoSeeder())->run();
            return \App\Models\User::firstOrCreate(['email' => ($perfil ?: 'duenio') . '@' . self::TENANT_DOMAIN], [
                'name' => $perfil ?: 'Dueño', 'password' => bcrypt('x'), 'is_admin' => true, 'activo' => true,
                'perfil_id' => $perfil ? \App\Models\Perfil::where('nombre', $perfil)->value('id') : null,
            ]);
        });

        return $this->actingAs($usuario);
    }

    /** @test */
    public function desde_la_pantalla_se_revisa_se_aplica_por_partes_y_queda_registrado()
    {
        $this->como()->get($this->urlTenant('admin/productos/importar'))->assertOk()->assertSee('Bajá la plantilla');

        $respuesta = $this->como()->post($this->urlTenant('admin/productos/importar'), ['planilla' => $this->planilla([
            ['Código', 'Nombre', 'Proveedor', 'Precio'],
            ['AGUA-2', 'Agua 2 L', 'Distribuidora del Sur', '500'],
        ])]);
        $importacion = $this->enTenant(function () { return Importacion::latest('id')->first(); });
        $respuesta->assertRedirect($this->urlTenant('admin/importaciones/' . $importacion->id));

        $this->como()->get($this->urlTenant('admin/importaciones/' . $importacion->id))
            ->assertOk()->assertSee('Se van a crear')->assertSee('Aplicar la planilla');

        $this->como()->postJson($this->urlTenant('admin/importaciones/' . $importacion->id . '/aplicar'))
            ->assertOk()->assertJson(['estado' => 'terminada', 'creados' => 1, 'porcentaje' => 100]);

        $this->enTenant(function () use ($importacion) {
            $this->assertSame('Agua 2 L', Producto::conCodigo('agua-2')->value('descripcion'));
            $this->assertNotNull(Importacion::find($importacion->id)->user_id);
        });
        $this->como()->get($this->urlTenant('admin/productos/importar'))->assertSee('productos.xlsx')->assertSee('Aplicada');
    }

    /** @test */
    public function no_aplicar_no_cambia_nada()
    {
        $this->como()->post($this->urlTenant('admin/productos/importar'), ['planilla' => $this->planilla([
            ['Código', 'Nombre', 'Proveedor'], ['X-1', 'Algo', 'Distribuidora del Sur'],
        ])]);
        $id = $this->enTenant(function () { return Importacion::latest('id')->value('id'); });

        $this->como()->post($this->urlTenant('admin/importaciones/' . $id . '/cancelar'))->assertRedirect();
        $this->como()->postJson($this->urlTenant('admin/importaciones/' . $id . '/aplicar'))->assertStatus(422);

        $this->enTenant(function () {
            $this->assertSame(0, Producto::conCodigo('x-1')->count());
        });
    }

    /** @test */
    public function bajar_la_plantilla_y_exportar_dan_un_excel()
    {
        $this->como()->get($this->urlTenant('admin/productos/plantilla?categoria=' . $this->ids['gaseosas']))
            ->assertOk()->assertHeader('content-disposition', 'attachment; filename=plantilla-productos-gaseosas.xlsx');
        $this->como()->get($this->urlTenant('admin/productos/exportar?buscar=cola'))
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function el_operador_no_importa_ni_ve_el_boton()
    {
        $this->como('Operador')->get($this->urlTenant('admin/productos/importar'))->assertRedirect();
        $this->como('Operador')->get($this->urlTenant('admin/productos'))->assertOk()->assertDontSee('Importar desde Excel');
        $this->como()->get($this->urlTenant('admin/productos'))->assertSee('Importar desde Excel');
    }

    /** @test */
    public function sin_codigo_no_duplica_un_producto_que_ya_existe()
    {
        $this->enTenant(function () {
            $importacion = $this->servicio()->preparar($this->planilla([
                ['Código', 'Nombre', 'Proveedor', 'Sabor'],
                ['', 'Gaseosa Cola 1,5 L', 'Distribuidora del Sur', 'Cola'],      // ya existe (con otro código)
                ['', 'Agua saborizada', 'Distribuidora del Sur', ''],
                ['', 'agua saborizada', 'Distribuidora del Sur', ''],              // repetida en la planilla
            ]), null);

            $mensajes = array_column($importacion->resumen['errores'], 'mensaje');
            $this->assertContains('Ya tenés un producto «Gaseosa Cola 1,5 L». Si es ese, poné su código para actualizarlo; si es otro, cambiale el nombre.', $mensajes);
            $this->assertContains('«agua saborizada» ya aparece sin código en la fila 3: se crearía dos veces. Poneles código.', $mensajes);
            $this->assertSame(1, $importacion->resumen['crear']);
        });
    }

    /** @test */
    public function lee_el_csv_del_excel_en_castellano_separado_por_punto_y_coma()
    {
        $this->enTenant(function () {
            $ruta = sys_get_temp_dir() . '/planilla-' . uniqid() . '.csv';
            file_put_contents($ruta, "Código;Nombre;Proveedor;Precio\nCSV-1;Fideos tirabuzón 500 g;Distribuidora del Sur;1.234,50\n");

            $importacion = $this->servicio()->preparar(new UploadedFile($ruta, 'productos.csv', null, null, true), null);
            $this->aplicarTodo($importacion);

            $this->assertSame('1234.50', Producto::conCodigo('csv-1')->value('precio'));
        });
    }
}
