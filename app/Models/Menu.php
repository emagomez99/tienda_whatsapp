<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'parent_id',
        'tipo_enlace',
        'enlace_id',
        'enlace_valor',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    const TIPO_NINGUNO = 'ninguno';
    const TIPO_PROVEEDOR = 'proveedor';
    const TIPO_CATEGORIA = 'categoria';
    const TIPO_ESPECIFICACION = 'especificacion';

    /**
     * Relación: Menú padre
     */
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /**
     * Relación: Submenús hijos
     */
    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('orden');
    }

    /**
     * Relación recursiva: Todos los hijos activos ordenados
     */
    public function childrenActivos()
    {
        return $this->hasMany(Menu::class, 'parent_id')
            ->where('activo', true)
            ->orderBy('orden')
            ->with('childrenActivos');
    }

    /**
     * Relación polimórfica: Proveedor asociado
     */
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'enlace_id');
    }

    /**
     * Relación polimórfica: Categoría (Etiqueta) asociada
     */
    public function categoria()
    {
        return $this->belongsTo(Etiqueta::class, 'enlace_id');
    }

    /**
     * Obtener el elemento relacionado según el tipo
     */
    public function getEnlaceAttribute()
    {
        if ($this->tipo_enlace === self::TIPO_PROVEEDOR) {
            return $this->proveedor;
        } elseif ($this->tipo_enlace === self::TIPO_CATEGORIA) {
            return $this->categoria;
        }
        return null;
    }

    /**
     * Generar la URL del menú según su tipo
     */
    public function getUrlAttribute(): string
    {
        if ($this->tipo_enlace === self::TIPO_PROVEEDOR) {
            return route('tienda.index', ['proveedor' => $this->enlace_id]);
        } elseif ($this->tipo_enlace === self::TIPO_CATEGORIA) {
            return route('tienda.index', ['categoria' => $this->enlace_id, 'categoria_valor' => $this->enlace_valor]);
        } elseif ($this->tipo_enlace === self::TIPO_ESPECIFICACION) {
            return route('tienda.index', ['especificacion' => $this->enlace_valor]);
        }
        return '#';
    }

    /**
     * Verificar si el menú tiene hijos
     */
    public function tieneHijos(): bool
    {
        return $this->children()->where('activo', true)->exists();
    }

    /**
     * Verificar si es un contenedor (sin enlace)
     */
    public function esContenedor(): bool
    {
        return $this->tipo_enlace === self::TIPO_NINGUNO;
    }

    /**
     * Scope: Solo menús raíz (sin padre)
     */
    public function scopeRaiz($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: Solo menús activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Obtener el árbol completo del menú con caché
     */
    public static function getArbolMenu()
    {
        return Cache::remember('menu_arbol', 3600, function () {
            return self::raiz()
                ->activos()
                ->orderBy('orden')
                ->with('childrenActivos')
                ->get();
        });
    }

    /**
     * Limpiar caché del menú
     */
    public static function limpiarCache()
    {
        Cache::forget('menu_arbol');
    }

    /**
     * Obtener descripción del tipo de enlace
     */
    public function getTipoEnlaceDescripcionAttribute(): string
    {
        if ($this->tipo_enlace === self::TIPO_PROVEEDOR) {
            return 'Proveedor';
        } elseif ($this->tipo_enlace === self::TIPO_CATEGORIA) {
            return 'Categoría';
        } elseif ($this->tipo_enlace === self::TIPO_ESPECIFICACION) {
            return 'Especificación';
        }
        return 'Contenedor';
    }

    /**
     * Obtener nombre del elemento enlazado
     */
    public function getNombreEnlaceAttribute(): ?string
    {
        if ($this->tipo_enlace === self::TIPO_PROVEEDOR) {
            return $this->proveedor ? $this->proveedor->nombre : null;
        } elseif ($this->tipo_enlace === self::TIPO_CATEGORIA) {
            $nombre = $this->categoria ? $this->categoria->nombre : '';
            return $nombre . ($this->enlace_valor ? ": {$this->enlace_valor}" : '');
        } elseif ($this->tipo_enlace === self::TIPO_ESPECIFICACION) {
            return $this->enlace_valor;
        }
        return null;
    }

    /**
     * Boot del modelo para limpiar caché automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            self::limpiarCache();
        });

        static::deleted(function () {
            self::limpiarCache();
        });
    }
}
