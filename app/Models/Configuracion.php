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

    public static function obtener($clave, $default = null)
    {
        return Cache::remember("config_{$clave}", 3600, function () use ($clave, $default) {
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
        Cache::forget("config_{$clave}");
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
}
