<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Producto;
use App\Support\SeoMeta;
use Illuminate\Support\Str;

/**
 * Centraliza el armado de metadatos SEO (meta tags, Open Graph, JSON-LD) con
 * un esquema de fallback: valor específico de la página > default global de
 * la tienda (Configuracion) > nombre de la tienda.
 */
class SeoService
{
    /**
     * Resuelve los meta tags para el <head> de una página.
     *
     * @param array $overrides Claves opcionales: title, description, keywords, image, type, noindex, canonical
     */
    public static function metaTags(array $overrides = [])
    {
        $title = isset($overrides['title']) ? trim((string) $overrides['title']) : '';
        if ($title === '') {
            $title = Configuracion::seoTituloDefault();
        }
        if ($title === '') {
            $title = Configuracion::nombreTienda();
        }

        $description = isset($overrides['description']) ? trim((string) $overrides['description']) : '';
        if ($description === '') {
            $description = Configuracion::seoDescripcionDefault();
        }

        $keywords = isset($overrides['keywords']) ? trim((string) $overrides['keywords']) : '';
        if ($keywords === '') {
            $keywords = Configuracion::seoKeywords();
        }

        $image = isset($overrides['image']) && $overrides['image'] ? $overrides['image'] : self::imagenPorDefecto();
        $type = isset($overrides['type']) && $overrides['type'] ? $overrides['type'] : 'website';
        $noindex = isset($overrides['noindex']) ? (bool) $overrides['noindex'] : !Configuracion::robotsIndex();

        // La página puede declarar su URL canónica explícita (ver tienda/show.blade.php).
        // Sin eso se cae a la URL pedida, que sólo es canónica si nadie puede llegar a
        // esta página por otra dirección.
        $canonical = isset($overrides['canonical']) ? trim((string) $overrides['canonical']) : '';
        if ($canonical === '') {
            $canonical = url()->current();
        }

        return new SeoMeta($title, $description, $keywords, $image, $type, $noindex, $canonical);
    }

    public static function imagenPorDefecto()
    {
        $logo = Configuracion::logo();
        return $logo ? url('storage/' . $logo) : url('/img/no-image.svg');
    }

    /**
     * JSON-LD de la tienda como entidad (se emite en todas las páginas).
     */
    public static function organizationSchema()
    {
        $sameAs = array_values(array_filter([
            Configuracion::socialInstagram(),
            Configuracion::socialFacebook(),
            Configuracion::socialTwitter(),
            Configuracion::socialTiktok(),
            Configuracion::socialYoutube(),
        ]));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => Configuracion::nombreTienda(),
            'url' => url('/'),
            'image' => self::imagenPorDefecto(),
        ];

        if (!empty($sameAs)) {
            $schema['sameAs'] = $sameAs;
        }

        $address = self::addressSchema();
        if ($address) {
            $schema['address'] = $address;
        }

        return $schema;
    }

    /**
     * PostalAddress a partir de la ubicación cargada en Configuracion.
     * Si "ubicación activa" está apagado (o no hay ciudad), no se declara address.
     * Sin calle, representa "radicada en" esa ciudad sin implicar que haya un
     * local físico visitable.
     */
    private static function addressSchema()
    {
        if (!Configuracion::ubicacionActiva()) {
            return null;
        }

        $ciudad = Configuracion::ciudad();
        if ($ciudad === '') {
            return null;
        }

        $address = [
            '@type' => 'PostalAddress',
            'addressLocality' => $ciudad,
            'addressCountry' => 'AR',
        ];

        if (Configuracion::provincia() !== '') {
            $address['addressRegion'] = Configuracion::provincia();
        }

        if (Configuracion::direccion() !== '') {
            $address['streetAddress'] = Configuracion::direccion();
        }

        if (Configuracion::codigoPostal() !== '') {
            $address['postalCode'] = Configuracion::codigoPostal();
        }

        return $address;
    }

    /**
     * JSON-LD Product para la ficha de un producto.
     */
    public static function productSchema(Producto $producto)
    {
        $texto = $producto->detalle ? strip_tags($producto->detalle) : $producto->descripcion;
        $texto = trim(preg_replace('/\s+/', ' ', $texto));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $producto->descripcion,
            'description' => Str::limit($texto, 300 - 3),
            'image' => $producto->imagen_url ?: self::imagenPorDefecto(),
        ];

        if ($producto->id_proveedor) {
            $schema['sku'] = $producto->id_proveedor;
        }

        // precio tiene cast 'decimal:2', Eloquent lo devuelve como string ("0.00" incluido),
        // y en PHP "0.00" es truthy -- por eso la comparación numérica explícita en vez de un
        // simple if ($producto->precio). Google rechaza price=0 en merchant listings.
        if (Configuracion::mostrarPrecios() && (float) $producto->precio > 0) {
            $schema['offers'] = [
                '@type' => 'Offer',
                // URL canónica del producto, no la pedida: /producto/{slug}-{id} resuelve
                // por el id, así que se puede llegar acá con cualquier slug.
                'url' => $producto->url(),
                'priceCurrency' => $producto->moneda ? $producto->moneda->codigo : 'ARS',
                'price' => number_format((float) $producto->precio, 2, '.', ''),
                'availability' => $producto->estaDisponible()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ];
        }

        return $schema;
    }

    /**
     * Serializa un schema para incrustarlo dentro de un <script type="application/ld+json">.
     *
     * A propósito NO usa JSON_UNESCAPED_SLASHES: sin esa flag, json_encode escapa
     * "/" como "\/", lo que evita que un valor con "</script>" (ej. una descripción
     * de producto importada de un proveedor externo) cierre el tag e inyecte HTML/JS.
     */
    public static function jsonLd(array $schema)
    {
        return json_encode($schema, JSON_UNESCAPED_UNICODE);
    }

    /**
     * JSON-LD BreadcrumbList a partir de una lista de ['name' => ..., 'url' => ...].
     */
    public static function breadcrumbSchema(array $items)
    {
        $listItems = [];
        foreach ($items as $i => $item) {
            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems,
        ];
    }
}
