<?php

namespace App\Exports;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Services\CaracteristicasPorCategoria;
use App\Support\Caracteristica;
use App\Support\Planilla\FormatoDePlanilla;
use Illuminate\Support\Facades\DB;
use OpenSpout\Writer\Common\Creator\Style\StyleBuilder;
use OpenSpout\Writer\Common\Creator\WriterEntityFactory;

/**
 * La planilla vacía para cargar productos: la hoja "Productos" con los títulos (las
 * obligatorias con " *") y la hoja "Cómo completarla", que explica cada columna con
 * un ejemplo. Así nadie tiene que adivinar qué poner.
 *
 * Para una categoría, las columnas de características son las que pide esa
 * categoría (con lo heredado y lo que piden todos los productos); sin categoría,
 * todas las características de la tienda.
 */
class PlantillaDeProductos
{
    /** @var Categoria|null */
    private $categoria;

    public function __construct(?Categoria $categoria)
    {
        $this->categoria = $categoria;
    }

    /** Escribe la plantilla en $ruta (.xlsx). */
    public function escribir(string $ruta): void
    {
        $caracteristicas = $this->caracteristicas();
        $negrita = (new StyleBuilder())->setFontBold()->build();

        $escritor = WriterEntityFactory::createXLSXWriter();
        AnchosDeColumna::antesDeAbrir($escritor);
        $escritor->openToFile($ruta);

        // Sólo los títulos: una fila de ejemplo se importaría como producto.
        $escritor->getCurrentSheet()->setName('Productos');
        AnchosDeColumna::aplicar($escritor);
        $escritor->addRow(WriterEntityFactory::createRowFromArray(FormatoDePlanilla::titulos($caracteristicas, true), $negrita));

        $escritor->addNewSheetAndMakeItCurrent()->setName('Cómo completarla');
        AnchosDeColumna::aplicar($escritor, AnchosDeColumna::INSTRUCCIONES);
        $ajustado = (new StyleBuilder())->setShouldWrapText()->build();
        foreach ($this->instrucciones($caracteristicas) as $i => $fila) {
            $escritor->addRow(WriterEntityFactory::createRowFromArray($fila, $i === 0 ? $negrita : $ajustado));
        }

        $escritor->close();
    }

    /** @return array<int, array{id: int, nombre: string, obligatoria: bool, origen: ?string}> */
    public function caracteristicas(): array
    {
        if ($this->categoria) {
            return array_map(function (Caracteristica $c) {
                return ['id' => $c->etiquetaId(), 'nombre' => $c->nombre(), 'obligatoria' => $c->obligatoria(), 'origen' => $c->origen()];
            }, (new CaracteristicasPorCategoria())->deCategoria($this->categoria->id));
        }

        return Etiqueta::orderByDesc('global')->orderBy('nombre')->get()->map(function (Etiqueta $e) {
            return ['id' => $e->id, 'nombre' => $e->nombre, 'obligatoria' => (bool) ($e->global && $e->obligatoria_global), 'origen' => null];
        })->all();
    }

    /** Una fila por columna: qué poner y un ejemplo. */
    private function instrucciones(array $caracteristicas): array
    {
        $ejemplos = [
            'codigo' => 'COCA-15', 'nombre' => 'Gaseosa Coca-Cola 1,5 L', 'proveedor' => 'Distribuidora del Sur',
            'categorias' => 'Bebidas > Gaseosas, Ofertas', 'precio' => '1.500,50', 'moneda' => 'ARS', 'stock' => '24',
            'disponible' => 'Sí', 'por_encargue' => 'No', 'detalle' => 'Botella retornable.',
        ];

        $filas = [['Columna', 'Qué poner', 'Ejemplo']];
        if ($this->categoria) {
            $filas[] = ['', 'Planilla para «' . str_replace(' > ', ' › ', $this->categoria->camino()) . '». En Categorías escribí ' . $this->categoria->camino() . ' (o una de adentro).', ''];
        }
        foreach (FormatoDePlanilla::COLUMNAS as $clave => [$titulo, $ayuda]) {
            $filas[] = [$titulo, $ayuda, $ejemplos[$clave] ?? ''];
        }
        $filas[] = [FormatoDePlanilla::IMAGEN . ' 1, 2…', 'La dirección web de cada foto. La primera es la principal. Si ponés fotos, reemplazan a las que tenía el producto.', 'https://…/foto.jpg'];

        foreach ($caracteristicas as $c) {
            $quien = $c['obligatoria']
                ? ($c['origen'] ? "Obligatoria (la pide {$c['origen']})." : 'Obligatoria en todos los productos.')
                : 'Opcional.';
            $filas[] = [$c['nombre'] . ($c['obligatoria'] ? ' *' : ''), "Característica. {$quien} Si ya usaste valores, conviene escribirlos igual.", $this->ejemploDeValor($c['id'])];
        }

        $filas[] = ['', '', ''];
        $filas[] = ['Importante', 'Una celda vacía no cambia nada: para actualizar sólo los precios, alcanza con las columnas Código y Precio. Los productos que no están en la planilla no se tocan.', ''];

        return $filas;
    }

    /** Los valores más usados de una característica, para que se escriban igual. */
    private function ejemploDeValor(int $etiquetaId): string
    {
        return DB::table('producto_etiqueta')->where('etiqueta_id', $etiquetaId)
            ->select('valor', DB::raw('count(*) as n'))->groupBy('valor')->orderByDesc('n')->limit(3)
            ->pluck('valor')->implode(', ');
    }
}
