<?php

namespace App\Support\Planilla;

use App\Models\EtiquetaValor;
use App\Services\GaleriaProducto;
use Illuminate\Support\Str;

/**
 * Cómo es la planilla de productos: qué columnas tiene, cómo se llaman y cómo se
 * leen sus valores. La usan la plantilla, la exportación y la lectura de lo que se
 * sube, así las tres siempre coinciden (lo que se exporta se puede volver a subir).
 *
 * Columnas fijas (abajo) y después una por característica, con su nombre (Marca,
 * Sabor…). En la plantilla, las obligatorias llevan " *" al final del título; al
 * leer, el " *" se ignora.
 *
 * Pensada para que la complete cualquiera: precios como se escriben acá
 * ("1.500,50"), "Sí"/"No", categorías como "Bebidas > Gaseosas, Ofertas".
 */
class FormatoDePlanilla
{
    /** clave => [título, ayuda para la hoja "Cómo completarla"] */
    const COLUMNAS = [
        'codigo'       => ['Código', 'Con el código, el producto se actualiza si ya existe. Si lo dejás vacío, se crea uno nuevo cada vez que subas la planilla.'],
        'nombre'       => ['Nombre', 'Obligatorio para productos nuevos. Ej: Gaseosa Coca-Cola 1,5 L.'],
        'proveedor'    => ['Proveedor', 'Quién te lo provee. Si no existe, se crea. Obligatorio para productos nuevos, salvo que tengas un solo proveedor.'],
        'categorias'   => ['Categorías', 'Sus estantes. Uno dentro de otro con ">", varios separados por coma. Ej: Bebidas > Gaseosas, Ofertas. Las que no existen se crean.'],
        'precio'       => ['Precio', 'Sólo el número, como lo escribís normalmente. Ej: 1.500,50. Vacío en un producto nuevo: sin precio.'],
        'moneda'       => ['Moneda', 'El código de la moneda: ARS, USD… Vacía: la moneda de siempre.'],
        'stock'        => ['Stock', 'Cuántas unidades tenés ahora. Si cambia, queda registrado en el historial del producto.'],
        'disponible'   => ['Se muestra en la tienda', 'Sí o No. Vacío en un producto nuevo: Sí.'],
        'por_encargue' => ['Se puede pedir sin stock', 'Sí o No. Vacío en un producto nuevo: No.'],
        'detalle'      => ['Descripción detallada', 'Texto libre que se ve en la ficha del producto.'],
    ];

    /** Prefijo de las columnas de imágenes: "Imagen 1", "Imagen 2"… */
    const IMAGEN = 'Imagen';

    /** Para mostrar un valor sí/no en la planilla. */
    const SI = 'Sí';
    const NO = 'No';

    /**
     * Los títulos de las columnas en orden: las fijas, las imágenes y una por
     * característica.
     *
     * @param array<int, array{nombre: string, obligatoria: bool}> $caracteristicas
     * @return string[]
     */
    public static function titulos(array $caracteristicas, bool $marcarObligatorias = false): array
    {
        $titulos = array_map(function ($c) { return $c[0]; }, array_values(self::COLUMNAS));

        for ($i = 1; $i <= static::cantidadDeImagenes(); $i++) {
            $titulos[] = self::IMAGEN . ' ' . $i;
        }

        foreach ($caracteristicas as $c) {
            $titulos[] = $c['nombre'] . ($marcarObligatorias && $c['obligatoria'] ? ' *' : '');
        }

        return $titulos;
    }

    public static function cantidadDeImagenes(): int
    {
        return GaleriaProducto::maximo();
    }

    /**
     * Qué es cada columna de la planilla subida, a partir de sus títulos.
     *
     * @param array<int, mixed> $titulos los de la primera fila
     * @param array<string, int> $caracteristicasPorNombre nombre normalizado => etiqueta_id
     * @return array{columnas: array<int, array{tipo: string, clave: mixed}>, desconocidas: string[]}
     *         tipo: fija (clave = 'codigo'…), imagen (clave = posición) o caracteristica (clave = etiqueta_id)
     */
    public static function interpretarTitulos(array $titulos, array $caracteristicasPorNombre): array
    {
        $fijas = [];
        foreach (self::COLUMNAS as $clave => $c) {
            $fijas[static::normalizarTitulo($c[0])] = $clave;
        }

        $columnas = [];
        $desconocidas = [];
        foreach ($titulos as $indice => $titulo) {
            $normal = static::normalizarTitulo((string) $titulo);
            if ($normal === '') {
                continue;
            }
            if (isset($fijas[$normal])) {
                $columnas[$indice] = ['tipo' => 'fija', 'clave' => $fijas[$normal]];
            } elseif (preg_match('/^' . strtolower(self::IMAGEN) . '\s*(\d+)$/', $normal, $m)) {
                $columnas[$indice] = ['tipo' => 'imagen', 'clave' => (int) $m[1]];
            } elseif (isset($caracteristicasPorNombre[$normal])) {
                $columnas[$indice] = ['tipo' => 'caracteristica', 'clave' => $caracteristicasPorNombre[$normal]];
            } else {
                $desconocidas[] = trim((string) $titulo);
            }
        }

        return ['columnas' => $columnas, 'desconocidas' => $desconocidas];
    }

    /** Un título comparable: sin " *", sin tildes, minúsculas y espacios simples. */
    public static function normalizarTitulo(string $titulo): string
    {
        $titulo = preg_replace('/\s*\*\s*$/u', '', trim($titulo));

        return Str::ascii(EtiquetaValor::normalizar($titulo));
    }

    /**
     * Un número escrito como se escribe en Argentina o como lo guarda Excel:
     * 1500, 1500.5, "1.500,50", "$ 1.500", "1,5". Null si está vacío; false si no se
     * entiende.
     *
     * @return float|null|false
     */
    public static function leerNumero($valor)
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $texto = preg_replace('/[\s$]|ARS|USD/iu', '', (string) $valor);
        if ($texto === '') {
            return null;
        }

        $coma = strrpos($texto, ',');
        $punto = strrpos($texto, '.');
        if ($coma !== false && $punto !== false) {
            // Los dos: el que va último separa los decimales.
            $decimal = $coma > $punto ? ',' : '.';
            $texto = str_replace($decimal === ',' ? '.' : ',', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } elseif ($coma !== false) {
            $texto = str_replace(',', '.', $texto);
        } elseif ($punto !== false && preg_match('/^\d{1,3}(\.\d{3})+$/', $texto)) {
            // "1.500" o "12.345.678": puntos de miles, como se escribe acá.
            $texto = str_replace('.', '', $texto);
        }

        return is_numeric($texto) ? (float) $texto : false;
    }

    /**
     * Sí/No en sus formas habituales. Null si está vacío; ninguno de los dos (null
     * con $entendido = false) si no se entiende.
     */
    public static function leerSiNo($valor, bool &$entendido = true): ?bool
    {
        $entendido = true;
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_bool($valor)) {
            return $valor;
        }

        $texto = Str::ascii(mb_strtolower(trim((string) $valor), 'UTF-8'));
        if (in_array($texto, ['si', 's', 'x', '1', 'verdadero', 'true', 'yes'], true)) {
            return true;
        }
        if (in_array($texto, ['no', 'n', '0', 'falso', 'false'], true)) {
            return false;
        }

        $entendido = false;
        return null;
    }

    /** "Bebidas > Gaseosas, Ofertas" => ["Bebidas > Gaseosas", "Ofertas"] */
    public static function leerCategorias($valor): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $valor)), 'strlen'));
    }

    /**
     * Separador de los valores de una característica que admite varios: "S; M; L".
     * Punto y coma y no coma: "1,5 L" es un solo valor.
     */
    const SEPARADOR_VALORES = ';';

    /**
     * Los valores de una celda de característica. Si la característica admite uno
     * solo, el punto y coma es parte del texto.
     *
     * @return string[]
     */
    public static function leerValores(string $texto, bool $admiteVarios): array
    {
        if (!$admiteVarios) {
            return [$texto];
        }

        return array_values(array_filter(array_map('trim', explode(self::SEPARADOR_VALORES, $texto)), 'strlen'));
    }

    /** @param string[] $valores */
    public static function escribirValores(array $valores): string
    {
        return implode(self::SEPARADOR_VALORES . ' ', $valores);
    }

    /** Texto de una celda: sin espacios de más; null si está vacía. */
    public static function leerTexto($valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    public static function escribirSiNo(?bool $valor): string
    {
        return $valor ? self::SI : self::NO;
    }
}
