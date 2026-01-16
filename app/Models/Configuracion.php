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
}
