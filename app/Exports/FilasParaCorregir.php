<?php

namespace App\Exports;

use App\Imports\LecturaDePlanilla;
use App\Models\Importacion;
use App\Support\Planilla\FormatoDePlanilla;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Writer\Common\Creator\Style\StyleBuilder;
use OpenSpout\Writer\Common\Creator\WriterEntityFactory;

/**
 * Las filas de una planilla subida que no se cargaron, en una planilla nueva: tal
 * como se escribieron, con una primera columna "Qué corregir" que dice qué pasa con
 * cada una. Se corrigen ahí mismo y se vuelve a subir sólo eso (la columna de más se
 * ignora: ver FormatoDePlanilla::QUE_CORREGIR), en vez de buscar la fila 8.412 en
 * la planilla original.
 */
class FilasParaCorregir
{
    /** @var Importacion */
    private $importacion;

    public function __construct(Importacion $importacion)
    {
        $this->importacion = $importacion;
    }

    /** Si todavía está la planilla que se subió (sin ella no hay qué copiar). */
    public function sePuede(): bool
    {
        return Storage::disk('local')->exists($this->importacion->ruta);
    }

    /** Escribe la planilla en $ruta (.xlsx). Devuelve cuántas filas quedaron. */
    public function escribir(string $ruta): int
    {
        $motivos = $this->motivos();
        $negrita = (new StyleBuilder())->setFontBold()->build();

        $escritor = WriterEntityFactory::createXLSXWriter();
        $escritor->setDefaultColumnWidth(18);
        $escritor->openToFile($ruta);
        $escritor->getCurrentSheet()->setName('Para corregir');
        $escritor->setColumnWidth(60, 1);
        AnchosDeColumna::fijarTitulos($escritor);

        $escritas = 0;
        $lector = LecturaDePlanilla::abrir(Storage::disk('local')->path($this->importacion->ruta));
        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $numero => $fila) {
                $celdas = $this->sinLaColumnaDeMotivos($this->comoTexto($fila->toArray()), (int) $numero === 1);
                if ((int) $numero === 1) {
                    $escritor->addRow(WriterEntityFactory::createRowFromArray(array_merge([FormatoDePlanilla::QUE_CORREGIR], $celdas), $negrita));
                } elseif (isset($motivos[(int) $numero])) {
                    $escritor->addRow(WriterEntityFactory::createRowFromArray(array_merge([implode(' ', $motivos[(int) $numero])], $celdas)));
                    $escritas++;
                }
            }
            break;   // sólo la primera hoja, como al leerla
        }
        $lector->close();
        $escritor->close();

        return $escritas;
    }

    /**
     * Por qué no se cargó cada fila: lo que encontró el análisis y lo que falló al aplicar.
     *
     * @return array<int, string[]> número de fila => mensajes
     */
    private function motivos(): array
    {
        $motivos = [];
        foreach ($this->importacion->filas() as $fila) {
            if (!empty($fila['errores'])) {
                $motivos[(int) $fila['fila']] = $fila['errores'];
            }
        }
        foreach (($this->importacion->resumen ?: [])['errores_al_aplicar'] ?? [] as $error) {
            $motivos[(int) $error['fila']][] = $error['mensaje'];
        }

        return $motivos;
    }

    /** @var int|null dónde estaba "Qué corregir" en la planilla subida, si ya venía de acá */
    private $columnaDeMotivos = null;

    /** Una planilla que ya se había bajado de acá trae la columna: no se repite. */
    private function sinLaColumnaDeMotivos(array $celdas, bool $sonLosTitulos): array
    {
        if ($sonLosTitulos) {
            $buscado = FormatoDePlanilla::normalizarTitulo(FormatoDePlanilla::QUE_CORREGIR);
            foreach ($celdas as $indice => $titulo) {
                if (FormatoDePlanilla::normalizarTitulo((string) $titulo) === $buscado) {
                    $this->columnaDeMotivos = $indice;
                }
            }
        }
        if ($this->columnaDeMotivos !== null) {
            unset($celdas[$this->columnaDeMotivos]);
        }

        return array_values($celdas);
    }

    /** Las fechas, como las lee la importación; el resto de las celdas, tal cual. */
    private function comoTexto(array $celdas): array
    {
        return array_map(function ($celda) {
            return $celda instanceof DateTimeInterface ? $celda->format('d/m/Y') : $celda;
        }, $celdas);
    }
}
