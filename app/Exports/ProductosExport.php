<?php

namespace App\Exports;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Producto;
use App\Support\Planilla\FormatoDePlanilla;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Los productos en una planilla con el formato de FormatoDePlanilla: lo que se
 * exporta se puede editar en Excel y volver a subir (se actualiza por el Código).
 *
 * Una columna por característica de la tienda: primero las que se piden en todos
 * los productos (Marca), después por nombre. FromQuery la arma por partes, así
 * exportar los 30.000 productos de oleomc no necesita tenerlos todos en memoria.
 */
class ProductosExport implements FromQuery, WithHeadings, WithMapping, WithTitle, WithColumnWidths, WithEvents
{
    /** @var Builder */
    private $consulta;

    /** @var array<int, array{id: int, nombre: string, obligatoria: bool}> */
    private $caracteristicas;

    /** @var array<int, string> categoria_id => camino ("Bebidas > Gaseosas") */
    private $caminos;

    public function __construct(Builder $consulta)
    {
        $this->consulta = $consulta;
        $this->caracteristicas = Etiqueta::orderByDesc('global')->orderBy('nombre')->get()
            ->map(function (Etiqueta $e) { return ['id' => $e->id, 'nombre' => $e->nombre, 'obligatoria' => false]; })
            ->all();
        $this->caminos = Categoria::enOrdenDeArbol()->mapWithKeys(function (Categoria $c) {
            return [$c->id => $c->camino()];
        })->all();
    }

    public function query()
    {
        return $this->consulta->with(['proveedor:id,nombre', 'moneda:id,codigo', 'categorias:categorias.id', 'etiquetas', 'imagenes'])
            ->orderBy('productos.id');
    }

    public function title(): string
    {
        return 'Productos';
    }

    public function headings(): array
    {
        return FormatoDePlanilla::titulos($this->caracteristicas);
    }

    /** @param Producto $producto */
    public function map($producto): array
    {
        $fila = [
            $producto->id_proveedor,
            $producto->descripcion,
            optional($producto->proveedor)->nombre,
            $producto->categorias->map(function ($c) { return $this->caminos[$c->id] ?? null; })->filter()->implode(', '),
            $producto->precio !== null ? (float) $producto->precio : null,
            optional($producto->moneda)->codigo,
            (int) $producto->stock,
            FormatoDePlanilla::escribirSiNo((bool) $producto->disponible),
            FormatoDePlanilla::escribirSiNo((bool) $producto->por_encargue),
            $producto->detalle,
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
        for ($i = 0; $i < FormatoDePlanilla::cantidadDeImagenes(); $i++) {
            $fila[] = $imagenes[$i] ?? null;
        }

        $valores = $producto->etiquetas->mapWithKeys(function ($e) { return [$e->id => $e->pivot->valor]; });
        foreach ($this->caracteristicas as $c) {
            $fila[] = $valores[$c['id']] ?? null;
        }

        return $fila;
    }

    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 40, 'C' => 20, 'D' => 32, 'E' => 12, 'F' => 9, 'G' => 9, 'H' => 12, 'I' => 14, 'J' => 40];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $evento) {
                $hoja = $evento->sheet->getDelegate();
                $hoja->freezePane('C2');   // títulos y código/nombre siempre a la vista
                $hoja->getStyle('1:1')->getFont()->setBold(true);
            },
        ];
    }
}
