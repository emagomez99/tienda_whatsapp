<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Limpia el HTML de la descripción detallada de un producto antes de mostrarlo en
 * la tienda: deja sólo etiquetas y atributos de una lista conocida.
 *
 * strip_tags no alcanza: conserva los atributos, y <img onerror=…> o
 * <a href="javascript:…"> ejecutan código en el navegador del cliente.
 *
 * - Una etiqueta permitida se queda, sólo con sus atributos permitidos.
 * - Una etiqueta no permitida se saca pero se queda su texto (un <span> o <div>).
 * - Las peligrosas (script, style, iframe…) se sacan con todo lo de adentro.
 * - Los enlaces e imágenes sólo pueden apuntar a http, https, mailto o una ruta.
 * - class sólo con las de Quill (ql-…), que es el editor del panel.
 */
class HtmlSeguro
{
    const ETIQUETAS = [
        'p' => [], 'br' => [], 'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 's' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'blockquote' => [], 'pre' => [], 'code' => [],
        'a'   => ['href', 'title'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];

    /** Se sacan con su contenido: no es texto para mostrar. */
    const DESCARTAR = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'textarea', 'select', 'head', 'title', 'meta', 'link'];

    const ATRIBUTOS_URL = ['href', 'src'];

    public static function limpiar(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument();
        $errores = libxml_use_internal_errors(true);
        // El <meta> le dice a libxml que es UTF-8; el envoltorio permite encontrar el contenido.
        $doc->loadHTML('<html><head><meta charset="utf-8"></head><body><div id="html-seguro">' . $html . '</div></body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($errores);

        $raiz = $doc->getElementById('html-seguro');
        if (!$raiz) {
            return '';
        }

        self::limpiarHijos($raiz);

        $salida = '';
        foreach ($raiz->childNodes as $hijo) {
            $salida .= $doc->saveHTML($hijo);
        }

        return trim($salida);
    }

    private static function limpiarHijos(DOMNode $nodo): void
    {
        // Se copia la lista: se modifica mientras se recorre.
        foreach (iterator_to_array($nodo->childNodes) as $hijo) {
            if ($hijo instanceof DOMElement) {
                self::limpiarElemento($hijo);
            } elseif ($hijo->nodeType !== XML_TEXT_NODE) {
                $nodo->removeChild($hijo);   // comentarios, CDATA, instrucciones
            }
        }
    }

    private static function limpiarElemento(DOMElement $elemento): void
    {
        $nombre = strtolower($elemento->nodeName);
        $padre = $elemento->parentNode;

        if (in_array($nombre, self::DESCARTAR, true)) {
            $padre->removeChild($elemento);

            return;
        }

        self::limpiarHijos($elemento);

        if (!array_key_exists($nombre, self::ETIQUETAS)) {
            while ($elemento->firstChild) {
                $padre->insertBefore($elemento->firstChild, $elemento);
            }
            $padre->removeChild($elemento);

            return;
        }

        foreach (iterator_to_array($elemento->attributes) as $atributo) {
            if (!self::atributoPermitido($nombre, $atributo->name, $atributo->value)) {
                $elemento->removeAttribute($atributo->name);
            }
        }

        if ($nombre === 'a' && $elemento->hasAttribute('href')) {
            $elemento->setAttribute('rel', 'nofollow noopener');
        }
    }

    private static function atributoPermitido(string $etiqueta, string $atributo, string $valor): bool
    {
        $atributo = strtolower($atributo);

        if ($atributo === 'class') {
            return (bool) preg_match('/^(ql-[a-z0-9-]+)(\s+ql-[a-z0-9-]+)*$/', trim($valor));
        }

        if (!in_array($atributo, self::ETIQUETAS[$etiqueta], true)) {
            return false;
        }

        if (in_array($atributo, self::ATRIBUTOS_URL, true)) {
            return self::urlSegura($valor);
        }

        if (in_array($atributo, ['width', 'height', 'colspan', 'rowspan'], true)) {
            return ctype_digit(trim($valor));
        }

        return true;
    }

    /** http, https, mailto o una ruta del mismo sitio; nada de javascript:, data:, vbscript:… */
    private static function urlSegura(string $url): bool
    {
        // Los navegadores ignoran espacios y caracteres de control dentro del esquema ("java\tscript:").
        $url = preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (!preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $m)) {
            return !preg_match('#^\\\\|^/\\\\#', $url);   // ruta relativa, pero no "\\otro-sitio"
        }

        return in_array(strtolower($m[1]), ['http', 'https', 'mailto'], true);
    }
}
