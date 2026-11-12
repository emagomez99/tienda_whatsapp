<?php

namespace App\Imports;

use App\Support\Planilla\FormatoDePlanilla;
use DateTimeInterface;
use OpenSpout\Reader\Common\Creator\ReaderEntityFactory;
use OpenSpout\Reader\CSV\Reader as LectorCsv;

/**
 * Lee la primera hoja de la planilla subida y la deja en filas con el formato de
 * FormatoDePlanilla: cada valor ya interpretado (número, sí/no, categorías) y los
 * errores de escritura de cada fila ("Precio: «mil» no es un precio").
 *
 * Con OpenSpout, que lee de corrido sin cargar el archivo entero: una planilla de
 * 30.000 productos se lee en segundos y con poca memoria (PhpSpreadsheet tardaba
 * minutos y usaba cientos de MB).
 *
 * Formatos: .xlsx, .ods y .csv (el CSV del Excel en castellano viene separado por
 * punto y coma; se detecta solo). El .xls viejo no: se guarda como .xlsx desde Excel.
 */
class LecturaDePlanilla
{
    /** @var array<string, int> nombre normalizado => etiqueta_id */
    private $caracteristicasPorNombre;

    /** @var array<int, bool> etiqueta_id => true, las que admiten varios valores ("S; M; L") */
    private $conVariosValores;

    /** @var array|null qué es cada columna (se arma con la primera fila) */
    private $columnas = null;

    /** @var string[] títulos que no son ni columnas fijas ni características */
    public $columnasDesconocidas = [];

    /** @var array filas leídas */
    public $filas = [];

    /** @param int[] $conVariosValores ids de las características que admiten varios valores */
    public function __construct(array $caracteristicasPorNombre, array $conVariosValores = [])
    {
        $this->caracteristicasPorNombre = $caracteristicasPorNombre;
        $this->conVariosValores = array_fill_keys(array_map('intval', $conVariosValores), true);
    }

    /** Lee el archivo (ruta absoluta, con su extensión). */
    public function leer(string $ruta): void
    {
        $lector = static::abrir($ruta);
        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $numero => $fila) {
                $this->procesar((int) $numero, $fila->toArray());
            }
            break;   // sólo la primera hoja: la segunda de la plantilla es la ayuda
        }
        $lector->close();
    }

    /**
     * El lector de la planilla, ya abierto (hay que cerrarlo). El CSV, con el
     * separador que usa: coma o punto y coma.
     *
     * @return \OpenSpout\Reader\ReaderInterface
     */
    public static function abrir(string $ruta)
    {
        $lector = ReaderEntityFactory::createReaderFromFile($ruta);
        if ($lector instanceof LectorCsv) {
            $lector->setFieldDelimiter(static::separadorCsv($ruta));
        }
        $lector->open($ruta);

        return $lector;
    }

    /** Las columnas reconocidas (para saber, por ejemplo, si vino la columna Código). */
    public function columnas(): array
    {
        return $this->columnas ?: [];
    }

    private function procesar(int $numero, array $valores): void
    {
        if ($this->columnas === null) {
            $interpretacion = FormatoDePlanilla::interpretarTitulos($valores, $this->caracteristicasPorNombre);
            $this->columnas = $interpretacion['columnas'];
            $this->columnasDesconocidas = $interpretacion['desconocidas'];
            return;
        }

        $fila = $this->leerFila($numero, $valores);
        if ($fila !== null) {
            $this->filas[] = $fila;
        }
    }

    /** ";" si la primera línea tiene más punto y coma que comas (CSV del Excel en castellano). */
    private static function separadorCsv(string $ruta): string
    {
        $archivo = fopen($ruta, 'r');
        $primera = (string) fgets($archivo);
        fclose($archivo);

        return substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
    }

    private function leerFila(int $numero, array $celdas): ?array
    {
        $fila = ['fila' => $numero, 'valores' => [], 'imagenes' => [], 'caracteristicas' => [], 'errores' => []];
        $vacia = true;

        foreach ($this->columnas as $indice => $columna) {
            $celda = $celdas[$indice] ?? null;
            if ($celda instanceof DateTimeInterface) {
                $celda = $celda->format('d/m/Y');   // una fecha escrita en una celda de texto
            }
            if ($celda !== null && trim((string) $celda) !== '') {
                $vacia = false;
            }

            if ($columna['tipo'] === 'imagen') {
                $url = FormatoDePlanilla::leerTexto($celda);
                if ($url !== null) {
                    $fila['imagenes'][$columna['clave']] = $url;
                }
            } elseif ($columna['tipo'] === 'caracteristica') {
                // Siempre una lista: varios si la característica los admite ("S; M; L").
                $valor = FormatoDePlanilla::leerTexto($celda);
                if ($valor !== null) {
                    $fila['caracteristicas'][$columna['clave']] = FormatoDePlanilla::leerValores(
                        $valor, isset($this->conVariosValores[(int) $columna['clave']])
                    );
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

            case 'costo':
                $numero = FormatoDePlanilla::leerNumero($celda);
                if ($numero === false || ($numero !== null && $numero < 0)) {
                    $fila['errores'][] = "{$titulo}: «{$celda}» no es un importe. Escribí sólo el número, por ejemplo 1.500,50.";
                } else {
                    $fila['valores']['costo'] = $numero;
                }
                break;

            case 'margen':
                // "35%" escrito como texto vale 35.
                $numero = FormatoDePlanilla::leerNumero(is_string($celda) ? str_replace('%', '', $celda) : $celda);
                if ($numero === false || ($numero !== null && ($numero < 0 || $numero > 99999.99))) {
                    $fila['errores'][] = "{$titulo}: «{$celda}» no es un porcentaje. Escribí sólo el número, por ejemplo 35.";
                } else {
                    $fila['valores']['margen'] = $numero;
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
