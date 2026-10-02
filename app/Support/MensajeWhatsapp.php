<?php

namespace App\Support;

/**
 * Arma el mensaje de WhatsApp de un pedido a partir de la plantilla configurable.
 *
 * Además de reemplazar las variables ({nombre}, {direccion}…), limpia lo que queda
 * colgando cuando un dato viene vacío, para que el mensaje no llegue con restos:
 *
 *   - Una línea cuyas variables quedaron todas vacías se borra entera.
 *   - En una línea con algunas vacías, cada vacía se lleva la etiqueta que la
 *     presenta ("CP: ") y el separador que la une al resto (", " o " - ").
 *   - Un bloque (párrafo entre renglones en blanco) que tenía variables y se quedó
 *     sin ninguna pierde sus títulos ("*Dirección:*"); el texto suelto se conserva.
 *
 * Lo usan el envío del pedido y la vista previa de Ajustes, así la vista previa es
 * exactamente lo que se manda.
 */
class MensajeWhatsapp
{
    /** Variables disponibles en la plantilla => qué contienen. */
    const VARIABLES = [
        'pedido_id'          => 'Número de pedido',
        'nombre'             => 'Nombre del cliente',
        'apellido'           => 'Apellido del cliente',
        'email'              => 'Email del cliente',
        'celular'            => 'Celular del cliente',
        'direccion'          => 'Dirección',
        'localidad'          => 'Localidad',
        'provincia'          => 'Provincia',
        'cp'                 => 'Código postal',
        'productos'          => 'Productos: nombre, código y cantidad',
        'productos+detalles' => 'Productos con etiquetas e info técnica',
        'total'              => 'Total del pedido (si los precios están visibles)',
    ];

    // Marca interna de "acá había una variable vacía" mientras se limpia la línea. Es
    // un carácter Unicode de uso privado: no aparece en textos reales, y a diferencia
    // del byte nulo, PCRE lo acepta dentro de un patrón.
    const VACIA = "\u{E000}";

    // Separadores que quedan colgando al vaciarse una variable: , ; · | / – -
    const SEPARADOR = '[,;·|\/–-]';

    /**
     * @param string $plantilla
     * @param array  $valores   nombre de variable (sin llaves) => valor
     * @return string
     */
    public static function armar($plantilla, array $valores)
    {
        $plantilla = str_replace(["\r\n", "\r"], "\n", (string) $plantilla);
        $bloques   = preg_split('/\n[ \t]*\n/', trim($plantilla, "\n"));
        $salida    = [];

        foreach ($bloques as $bloque) {
            $lineas          = [];
            $teniaVariables  = false;
            $quedoAlgunDato  = false;

            foreach (explode("\n", $bloque) as $linea) {
                $variables = self::variablesEn($linea);

                if (! $variables) {
                    $lineas[] = $linea;
                    continue;
                }

                $teniaVariables = true;

                $conDato = array_filter($variables, function ($variable) use ($valores) {
                    return trim((string) ($valores[$variable] ?? '')) !== '';
                });

                if (! $conDato) {
                    continue;
                }

                $quedoAlgunDato = true;
                $lineas[] = self::completar($linea, $valores);
            }

            // Un bloque que se quedó sin datos pierde sus títulos ("*Dirección:*"): sin
            // nada debajo no presentan nada. El texto suelto ("Gracias!") se conserva.
            if ($teniaVariables && ! $quedoAlgunDato) {
                $lineas = array_filter($lineas, function ($linea) {
                    return ! self::esTitulo($linea);
                });
            }

            if ($lineas) {
                $salida[] = implode("\n", $lineas);
            }
        }

        return implode("\n\n", $salida);
    }

    /**
     * Un pedido de ejemplo, para la vista previa de Ajustes.
     */
    public static function valoresDeEjemplo($conDireccion, $conPrecios)
    {
        $productos = "• Perfume Importado 100ml (PRF-100) x2" . ($conPrecios ? ' - $90,000.00' : '')
                   . "\n• Crema Hidratante (CRM-050) x1" . ($conPrecios ? ' - $12,500.00' : '');

        $detalles  = "• Perfume Importado 100ml (PRF-100) x2" . ($conPrecios ? ' - $90,000.00' : '')
                   . "\n  Etiquetas: Marca=Lattafa"
                   . "\n  Info: Volumen=100 ml"
                   . "\n• Crema Hidratante (CRM-050) x1" . ($conPrecios ? ' - $12,500.00' : '');

        return [
            'pedido_id'          => '123',
            'nombre'             => 'Juan',
            'apellido'           => 'Pérez',
            'email'              => 'juan.perez@email.com',
            'celular'            => '+54 9 291 555-1234',
            'direccion'          => $conDireccion ? 'Alsina 250' : '',
            'localidad'          => $conDireccion ? 'Bahía Blanca' : '',
            'provincia'          => $conDireccion ? 'Buenos Aires' : '',
            'cp'                 => $conDireccion ? 'B8000' : '',
            'productos'          => $productos,
            'productos+detalles' => $detalles,
            'total'              => $conPrecios ? '*Total $: 102,500.00*' : '',
        ];
    }

    /** "Dirección:", "*Dirección:*", "_Envío:_" */
    private static function esTitulo($linea)
    {
        return preg_match('/:[*_~]*\s*$/u', $linea) === 1;
    }

    /** Variables conocidas que aparecen en la línea. */
    private static function variablesEn($linea)
    {
        preg_match_all('/\{([a-z_+]+)\}/', $linea, $coincidencias);

        return array_values(array_intersect($coincidencias[1], array_keys(self::VARIABLES)));
    }

    private static function completar($linea, array $valores)
    {
        preg_match('/^\s*/', $linea, $sangria);

        $linea = preg_replace_callback('/\{([a-z_+]+)\}/', function ($m) use ($valores) {
            if (! array_key_exists($m[1], self::VARIABLES)) {
                return $m[0];
            }
            $valor = (string) ($valores[$m[1]] ?? '');

            return trim($valor) === '' ? self::VACIA : $valor;
        }, $linea);

        // Cada vacía se lleva el separador previo y la etiqueta que la presenta:
        // "Bahía Blanca - CP: ␀" → "Bahía Blanca", "Alsina 250, ␀" → "Alsina 250".
        $linea = preg_replace(
            '/\s*' . self::SEPARADOR . '?\s*(?:[\p{L}\p{N}.*_]+:[*_]?\s*)?' . self::VACIA . '/u',
            '',
            $linea
        );

        // Si la vacía era la primera, el separador quedó al principio: "␀, Bahía" → ", Bahía".
        $linea = preg_replace('/^\s*' . self::SEPARADOR . '\s*/u', '', $linea);

        return $sangria[0] . trim($linea);
    }
}
