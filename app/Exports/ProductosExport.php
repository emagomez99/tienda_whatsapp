<?php

namespace App\Exports;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Producto;
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

        $titulos = (new StyleBuilder())->setFontBold()->build();
        $escritor->addRow(WriterEntityFactory::createRowFromArray($this->titulos(), $titulos));

        $this->consulta
            ->with(['proveedor:id,nombre', 'moneda:id,codigo', 'categorias:categorias.id', 'etiquetas', 'imagenes'])
            ->orderBy('productos.id')
            ->chunk(1000, function ($productos) use ($escritor) {
                foreach ($productos as $producto) {
                    $escritor->addRow(WriterEntityFactory::createRowFromArray($this->fila($producto)));
                }
            });

        $escritor->close();
    }

    public function titulos(): array
    {
        return FormatoDePlanilla::titulos($this->caracteristicas);
    }

    public function fila(Producto $producto): array
    {
        $fila = [
            (string) $producto->id_proveedor,
            $producto->descripcion,
            (string) optional($producto->proveedor)->nombre,
            $producto->categorias->map(function ($c) { return $this->caminos[$c->id] ?? null; })->filter()->implode(', '),
            $producto->precio !== null ? (float) $producto->precio : '',
            (string) optional($producto->moneda)->codigo,
            (int) $producto->stock,
            FormatoDePlanilla::escribirSiNo((bool) $producto->disponible),
            FormatoDePlanilla::escribirSiNo((bool) $producto->por_encargue),
            (string) $producto->detalle,
        ];

        // Las imágenes como direcciones completas: así se pueden volver a importar.
        $imagenes = [];
        if ($producto->url_imagen) {
            $imagenes[] = $producto->imagen_url;
        }
        foreach ($producto->imagenes as $imagen) {
            if ($imagen->url !== $producto->url_imagen) {
                $imagenes[] = $imagen->imagen_url;
            }
        }
        for ($i = 0; $i < $this->imagenes; $i++) {
            $fila[] = $imagenes[$i] ?? '';
        }

        $valores = $producto->etiquetas->mapWithKeys(function ($e) { return [$e->id => $e->pivot->valor]; });
        foreach ($this->caracteristicas as $c) {
            $fila[] = (string) ($valores[$c['id']] ?? '');
        }

        return $fila;
    }
}
