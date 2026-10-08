<?php

namespace App\Imports;

use App\Support\Planilla\FormatoDePlanilla;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;

/**
 * Lee la primera hoja de la planilla subida y la deja en filas con el formato de
 * FormatoDePlanilla: cada valor ya interpretado (número, sí/no, categorías) y los
 * errores de escritura de cada fila ("Precio: «mil» no es un precio").
 *
 * Por partes de 1.000 filas: cargar una planilla de 30.000 productos entera en
 * memoria necesitaría cientos de MB.
 */
class LecturaDePlanilla implements OnEachRow, WithChunkReading, WithCalculatedFormulas
{
    /** @var array<string, int> nombre normalizado => etiqueta_id */
    private $caracteristicasPorNombre;

    /** @var array|null qué es cada columna (se arma con la primera fila) */
    private $columnas = null;

    /** @var string[] títulos que no son ni columnas fijas ni características */
    public $columnasDesconocidas = [];

    /** @var array filas leídas */
    public $filas = [];

    public function __construct(array $caracteristicasPorNombre)
    {
        $this->caracteristicasPorNombre = $caracteristicasPorNombre;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function onRow(Row $row)
    {
        $valores = $row->toArray();

        if ($this->columnas === null) {
            $interpretacion = FormatoDePlanilla::interpretarTitulos($valores, $this->caracteristicasPorNombre);
            $this->columnas = $interpretacion['columnas'];
            $this->columnasDesconocidas = $interpretacion['desconocidas'];
            return;
        }

        $fila = $this->leerFila($row->getIndex(), $valores);
        if ($fila !== null) {
            $this->filas[] = $fila;
        }
    }

    /** Las columnas reconocidas (para saber, por ejemplo, si vino la columna Código). */
    public function columnas(): array
    {
        return $this->columnas ?: [];
    }

    private function leerFila(int $numero, array $celdas): ?array
    {
        $fila = ['fila' => $numero, 'valores' => [], 'imagenes' => [], 'caracteristicas' => [], 'errores' => []];
        $vacia = true;

        foreach ($this->columnas as $indice => $columna) {
            $celda = $celdas[$indice] ?? null;
            if ($celda !== null && trim((string) $celda) !== '') {
                $vacia = false;
            }

            if ($columna['tipo'] === 'imagen') {
                $url = FormatoDePlanilla::leerTexto($celda);
                if ($url !== null) {
                    $fila['imagenes'][$columna['clave']] = $url;
                }
            } elseif ($columna['tipo'] === 'caracteristica') {
                $valor = FormatoDePlanilla::leerTexto($celda);
                if ($valor !== null) {
                    $fila['caracteristicas'][$columna['clave']] = $valor;
                }
            } else {
                $this->leerFija($fila, $columna['clave'], $celda);
            }
        }

        if ($vacia) {
            return null;   // filas en blanco entre medio: se ignoran
        }
        ksort($fila['imagenes']);
        $fila['imagenes'] = array_values($fila['imagenes']);

        return $fila;
    }

    private function leerFija(array &$fila, string $clave, $celda): void
    {
        $titulo = FormatoDePlanilla::COLUMNAS[$clave][0];

        switch ($clave) {
            case 'precio':
                $numero = FormatoDePlanilla::leerNumero($celda);
                if ($numero === false || ($numero !== null && $numero < 0)) {
                    $fila['errores'][] = "{$titulo}: «{$celda}» no es un precio. Escribí sólo el número, por ejemplo 1.500,50.";
                } else {
                    $fila['valores']['precio'] = $numero;
                }
                break;

            case 'stock':
                $numero = FormatoDePlanilla::leerNumero($celda);
                if ($numero === false || ($numero !== null && ($numero < 0 || floor($numero) != $numero))) {
                    $fila['errores'][] = "{$titulo}: «{$celda}» no es una cantidad. Escribí un número entero, por ejemplo 10.";
                } else {
                    $fila['valores']['stock'] = $numero === null ? null : (int) $numero;
                }
                break;

            case 'disponible':
            case 'por_encargue':
                $entendido = true;
                $valor = FormatoDePlanilla::leerSiNo($celda, $entendido);
                if (!$entendido) {
                    $fila['errores'][] = "{$titulo}: «{$celda}» tiene que ser Sí o No.";
                } else {
                    $fila['valores'][$clave] = $valor;
                }
                break;

            case 'categorias':
                $texto = FormatoDePlanilla::leerTexto($celda);
                $fila['valores']['categorias'] = $texto === null ? null : FormatoDePlanilla::leerCategorias($texto);
                break;

            default:
                $fila['valores'][$clave] = FormatoDePlanilla::leerTexto($celda);
        }
    }
}
