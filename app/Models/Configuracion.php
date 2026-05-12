<?php

namespace App\Models;

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
        return Cache::remember(static::cacheKey($clave), 3600, function () use ($clave, $default) {
            $config = self::where('clave', $clave)->first();
            return $config ? $config->valor : $default;
        });
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

    public static function favicon()
    {
        return self::obtener('favicon', null);
    }

    public static function mostrarNombreTienda()
    {
        return self::obtener('mostrar_nombre_tienda', 'true') === 'true';
    }

    public static function paleta()
    {
        return self::obtener('paleta', 'azul');
    }

    public static function paletas()
    {
        return [
            'azul' => [
                'nombre' => 'Azul (Por defecto)',
                'primary' => '#0d6efd',
                'navbar' => 'bg-primary',
                'navbar_admin' => 'bg-dark',
            ],
            'verde' => [
                'nombre' => 'Verde',
                'primary' => '#198754',
                'navbar' => 'bg-success',
                'navbar_admin' => 'bg-dark',
            ],
            'rojo' => [
                'nombre' => 'Rojo',
                'primary' => '#dc3545',
                'navbar' => 'bg-danger',
                'navbar_admin' => 'bg-dark',
            ],
            'naranja' => [
                'nombre' => 'Naranja',
                'primary' => '#fd7e14',
                'navbar' => 'bg-warning',
                'navbar_admin' => 'bg-dark',
            ],
            'morado' => [
                'nombre' => 'Morado',
                'primary' => '#6f42c1',
                'navbar' => 'bg-purple',
                'navbar_admin' => 'bg-dark',
            ],
            'cyan' => [
                'nombre' => 'Cyan',
                'primary' => '#0dcaf0',
                'navbar' => 'bg-info',
                'navbar_admin' => 'bg-dark',
            ],
            'oscuro' => [
                'nombre' => 'Oscuro',
                'primary' => '#212529',
                'navbar' => 'bg-dark',
                'navbar_admin' => 'bg-secondary',
            ],
        ];
    }

    public static function getPaletaActual()
    {
        $paletas = self::paletas();
        $paletaSeleccionada = self::paleta();
        return $paletas[$paletaSeleccionada] ?? $paletas['azul'];
    }

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

    public static function monedaDefaultId()
    {
        $valor = self::obtener('moneda_default', null);
        return $valor ? (int) $valor : null;
    }

    public static function mostrarProveedor()
    {
        return self::obtener('mostrar_proveedor', 'false') === 'true';
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
}
