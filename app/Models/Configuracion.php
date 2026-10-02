<?php

namespace App\Models;

use App\Support\ColorHex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Configuracion extends Model
{
    use HasFactory;

    protected $table = 'configuraciones';

    protected $fillable = [
        'clave',
        'valor',
        'descripcion',
    ];

    protected static function cacheKey(string $clave): string
    {
        $prefix = (function_exists('tenancy') && tenancy()->initialized)
            ? 'tenant_' . tenant('id')
            : 'central';
        return $prefix . '_config_' . $clave;
    }

    public static function obtener($clave, $default = null)
    {
        try {
            return Cache::remember(static::cacheKey($clave), 3600, function () use ($clave, $default) {
                $config = self::where('clave', $clave)->first();
                return $config ? $config->valor : $default;
            });
        } catch (\Exception $e) {
            return $default;
        }
    }

    public static function establecer($clave, $valor, $descripcion = null)
    {
        $config = self::updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valor, 'descripcion' => $descripcion]
        );
        Cache::forget(static::cacheKey($clave));
        return $config;
    }

    public static function mostrarPrecios()
    {
        return self::obtener('mostrar_precios', 'true') === 'true';
    }

    public static function mostrarProductosSinStock()
    {
        return self::obtener('mostrar_productos_sin_stock', 'true') === 'true';
    }

    public static function whatsappAdmin()
    {
        return self::obtener('whatsapp_admin', '');
    }

    public static function nombreTienda()
    {
        return self::obtener('nombre_tienda', 'Tienda MC');
    }

    public static function logo()
    {
        return self::obtener('logo', null);
    }

    // Alto del logo en la cabecera de la tienda, en px. El ancho se ajusta solo.
    const LOGO_ALTO_MIN     = 24;
    const LOGO_ALTO_MAX     = 80;
    const LOGO_ALTO_DEFAULT = 40;

    public static function logoAlto()
    {
        $alto = (int) self::obtener('logo_alto', self::LOGO_ALTO_DEFAULT);

        return max(self::LOGO_ALTO_MIN, min(self::LOGO_ALTO_MAX, $alto));
    }

    public static function favicon()
    {
        return self::obtener('favicon', null);
    }

    public static function mostrarNombreTienda()
    {
        return self::obtener('mostrar_nombre_tienda', 'true') === 'true';
    }

    /**
     * Color principal de la tienda. Es libre (cualquier #rrggbb); si todavía no se
     * eligió ninguno se usa el de la paleta predefinida que tenía guardada, así las
     * tiendas que existían antes de liberar el color se siguen viendo igual.
     */
    public static function colorPrimario()
    {
        $color = self::obtener('color_primario', '');

        if (ColorHex::esValido($color)) {
            return ColorHex::desde($color);
        }

        $paleta = self::obtener('paleta', 'azul');

        return ColorHex::desde(self::PALETAS_ANTERIORES[$paleta] ?? self::PALETAS_ANTERIORES['azul']);
    }

    // Paletas cerradas que existían antes de liberar el color. Solo se usan de
    // respaldo en colorPrimario(), para tiendas que todavía no eligieron un color.
    const PALETAS_ANTERIORES = [
        'azul'    => '#0d6efd',
        'verde'   => '#198754',
        'rojo'    => '#dc3545',
        'naranja' => '#fd7e14',
        'morado'  => '#6f42c1',
        'cyan'    => '#0dcaf0',
        'oscuro'  => '#212529',
    ];

    public static function posicionMenu()
    {
        return self::obtener('posicion_menu', 'superior');
    }

    public static function menuEnSidebar()
    {
        return self::posicionMenu() === 'lateral';
    }

    public static function pedirDireccionEnvio()
    {
        return self::obtener('pedir_direccion_envio', 'true') === 'true';
    }

    public static function templateWhatsappDefault()
    {
        return "🛒 *NUEVO PEDIDO #{pedido_id}*\n\n*Cliente:*\nNombre: {nombre} {apellido}\nEmail: {email}\nCelular: {celular}\n\n*Dirección:*\n{direccion}, {localidad}\n{provincia} - CP: {cp}\n\n*Productos:*\n{productos}\n{total}";
    }

    public static function templateWhatsapp()
    {
        return self::obtener('template_whatsapp', self::templateWhatsappDefault());
    }

    // 'ambos' | 'solo_url' | 'solo_archivo'
    public static function modoImagenProducto()
    {
        return self::obtener('modo_imagen_producto', 'solo_url');
    }

    public static function imagenesAdicionalesActivas()
    {
        return self::obtener('imagenes_adicionales_activas', 'true') === 'true';
    }

    public static function maxImagenesAdicionales()
    {
        return (int) self::obtener('max_imagenes_adicionales', '3');
    }

    // La moneda por defecto ya no vive acá: es la marca `es_default` de la propia
    // moneda, y se administra desde el ABM de monedas (ver Moneda::porDefecto).

    public static function mostrarProveedor()
    {
        return self::obtener('mostrar_proveedor', 'false') === 'true';
    }

    // Si "Por encargue" viene prendido al dar de alta un producto. Es sólo el valor
    // inicial del formulario: cada producto lo puede cambiar.
    public static function porEncarguePorDefecto()
    {
        return self::obtener('por_encargue_por_defecto', 'false') === 'true';
    }

    public static function socialInstagram()
    {
        return self::obtener('social_instagram', '');
    }

    public static function socialFacebook()
    {
        return self::obtener('social_facebook', '');
    }

    public static function socialTwitter()
    {
        return self::obtener('social_twitter', '');
    }

    public static function socialTiktok()
    {
        return self::obtener('social_tiktok', '');
    }

    public static function socialYoutube()
    {
        return self::obtener('social_youtube', '');
    }

    public static function socialWhatsapp()
    {
        return self::obtener('social_whatsapp', '');
    }

    // ─── SEO ─────────────────────────────────────────────────────────────────

    public static function seoTituloDefault()
    {
        return self::obtener('seo_titulo_default', '');
    }

    public static function seoDescripcionDefault()
    {
        return self::obtener('seo_descripcion_default', '');
    }

    public static function seoKeywords()
    {
        return self::obtener('seo_keywords', '');
    }

    public static function googleAnalyticsId()
    {
        return self::obtener('google_analytics_id', '');
    }

    public static function googleSiteVerification()
    {
        return self::obtener('google_site_verification', '');
    }

    // Si está en false, toda la tienda se marca como noindex (útil mientras se arma la tienda)
    public static function robotsIndex()
    {
        return self::obtener('robots_index', 'true') === 'true';
    }

    // ─── Ubicación (SEO local) ──────────────────────────────────────────────
    // ubicacion_activa gatilla si se declara la ubicación en el schema.
    // Si está activa, ciudad es obligatoria; provincia/dirección/CP son opcionales.
    // Sin dirección (calle) se entiende "radicada en" sin implicar que haya un
    // local visitable (ver SeoService::organizationSchema).

    public static function ubicacionActiva()
    {
        return self::obtener('ubicacion_activa', 'false') === 'true';
    }

    public static function direccion()
    {
        return self::obtener('direccion', '');
    }

    public static function ciudad()
    {
        return self::obtener('ciudad', '');
    }

    public static function provincia()
    {
        return self::obtener('provincia', '');
    }

    public static function codigoPostal()
    {
        return self::obtener('codigo_postal', '');
    }
}
