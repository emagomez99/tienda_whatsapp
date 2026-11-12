<?php

namespace App\Exports;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Producto;
use App\Services\GaleriaProducto;
use App\Support\Planilla\FormatoDePlanilla;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Writer\Common\Creator\Style\StyleBuilder;
use OpenSpout\Writer\Common\Creator\WriterEntityFactory;

/**
 * Los productos en una planilla con el formato de FormatoDePlanilla: lo que se
 * exporta se puede editar en Excel y volver a subir (se actualiza por el Código).
 *
 * Una columna por característica de la tienda: primero las que se piden en todos
 * los productos (Marca), después por nombre. Se escribe de a 1.000 productos con
 * OpenSpout, de corrido: los 30.000 de oleomc no necesitan estar en memoria.
 */
class ProductosExport
{
    /** @var Builder */
    private $consulta;

    /** @var array<int, array{id: int, nombre: string, obligatoria: bool}> */
    private $caracteristicas;

    /** @var array<int, string> categoria_id => camino ("Bebidas > Gaseosas") */
    private $caminos;

    /** @var int columnas de imágenes (se calcula una vez: lee la configuración) */
    private $imagenes;

    public function __construct(Builder $consulta)
    {
        $this->consulta = $consulta;
        $this->caracteristicas = Etiqueta::orderByDesc('global')->orderBy('nombre')->get()
            ->map(function (Etiqueta $e) { return ['id' => $e->id, 'nombre' => $e->nombre, 'obligatoria' => false]; })
            ->all();
        $this->caminos = Categoria::enOrdenDeArbol()->mapWithKeys(function (Categoria $c) {
            return [$c->id => $c->camino()];
        })->all();
        $this->imagenes = FormatoDePlanilla::cantidadDeImagenes();
    }

    /** Escribe la planilla en $ruta (.xlsx). */
    public function escribir(string $ruta): void
    {
        $escritor = WriterEntityFactory::createXLSXWriter();
        AnchosDeColumna::antesDeAbrir($escritor);
        $escritor->openToFile($ruta);
        $escritor->getCurrentSheet()->setName('Productos');
        AnchosDeColumna::aplicar($escritor);
        AnchosDeColumna::fijarTitulos($escritor);

        $titulos = (new StyleBuilder())->setFontBold()->build();
        $escritor->addRow(WriterEntityFactory::createRowFromArray($this->titulos(), $titulos));

        $this->consulta
            ->with(['proveedor:id,nombre', 'moneda:id,codigo', 'monedaCompra:id,codigo', 'categorias:categorias.id', 'etiquetas', 'imagenes'])
            ->orderBy('productos.id')
            ->chunk(1000, function ($productos) use ($escritor) {
                foreach ($productos as $producto) {
                    $escritor->addRow(WriterEntityFactory::createRowFromArray($this->fila($producto)));
                }
            });

        HojasDeReferencia::agregar($escritor);
        $escritor->close();
    }

    public function titulos(): array
    {
        return FormatoDePlanilla::titulos($this->caracteristicas);
    }

    public function fila(Producto $producto): array
    {
        $valores = [
            'codigo'       => (string) $producto->id_proveedor,
            'nombre'       => $producto->descripcion,
            'proveedor'    => (string) optional($producto->proveedor)->nombre,
            'categorias'   => $producto->categorias->map(function ($c) { return $this->caminos[$c->id] ?? null; })->filter()->implode(', '),
            'precio'       => $producto->precio !== null ? (float) $producto->precio : '',
            'moneda'       => (string) optional($producto->moneda)->codigo,
            'costo'        => $producto->precio_compra !== null ? (float) $producto->precio_compra : '',
            'moneda_costo' => (string) optional($producto->monedaCompra)->codigo,
            'margen'       => $producto->margen_ganancia !== null ? (float) $producto->margen_ganancia : '',
            'stock'        => (int) $producto->stock,
            'disponible'   => FormatoDePlanilla::escribirSiNo((bool) $producto->disponible),
            'por_encargue' => FormatoDePlanilla::escribirSiNo((bool) $producto->por_encargue),
            'detalle'      => (string) $producto->detalle,
        ];

        // En el orden de las columnas, sea cual sea: si se suma una, no se corre nada.
        $fila = [];
        foreach (FormatoDePlanilla::COLUMNAS as $clave => $columna) {
            $fila[] = $valores[$clave];
        }

        // Las imágenes como direcciones completas: así se pueden volver a importar.
        $imagenes = GaleriaProducto::urlsPublicas($producto->url_imagen, $producto->imagenes->pluck('url')->all());
        for ($i = 0; $i < $this->imagenes; $i++) {
            $fila[] = $imagenes[$i] ?? '';
        }

        // Varios valores en una celda: "S; M; L" (se leen igual al volver a subirla).
        $deCaracteristicas = [];
        foreach ($producto->etiquetas as $e) {
            $deCaracteristicas[$e->id][] = $e->pivot->valor;
        }
        foreach ($this->caracteristicas as $c) {
            $fila[] = FormatoDePlanilla::escribirValores($deCaracteristicas[$c['id']] ?? []);
        }

        return $fila;
    }
}
