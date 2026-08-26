<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'slug',
        'meta_title',
        'meta_description',
        'parent_id',
        'tipo_enlace',
        'enlace_id',
        'enlace_valor',
        'orden',
        'activo',
        'filtros_etiquetas',
        'filtros_requeridos',
        'filtros_config',
        'filtro_stock',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
        'filtros_etiquetas' => 'array',
        'filtros_requeridos' => 'boolean',
        'filtros_config' => 'array',
    ];

    const TIPO_NINGUNO = 'ninguno';
    const TIPO_PROVEEDOR = 'proveedor';
    const TIPO_ETIQUETA = 'etiqueta';
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
     * Relación: Etiqueta asociada
     */
    public function etiqueta()
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
        } elseif ($this->tipo_enlace === self::TIPO_ETIQUETA) {
            return $this->etiqueta;
        }
        return null;
    }

    /**
     * Generar la URL del menú según su tipo
     */
    public function getUrlAttribute(): string
    {
        if ($this->tipo_enlace === self::TIPO_NINGUNO) {
            return '#';
        }

        if ($this->slug) {
            return route('tienda.catalogo', $this->slug);
        }

        // Fallback para menús sin slug (no debería ocurrir en producción)
        $params = [];
        if ($this->tieneFiltros() || $this->filtro_stock !== 'todos') {
            $params['menu'] = $this->id;
        }
        if ($this->tipo_enlace === self::TIPO_PROVEEDOR) {
            $params['proveedor'] = $this->enlace_id;
            return route('tienda.index', $params);
        } elseif ($this->tipo_enlace === self::TIPO_ETIQUETA) {
            $params['etiqueta'] = $this->enlace_id;
            $params['etiqueta_valor'] = $this->enlace_valor;
            return route('tienda.index', $params);
        } elseif ($this->tipo_enlace === self::TIPO_ESPECIFICACION) {
            $params['especificacion'] = $this->enlace_valor;
            return route('tienda.index', $params);
        }
        return '#';
    }

    /**
     * Meta title SEO. Si no se cargó uno específico, usa el nombre del menú.
     */
    public function getMetaTitleAttribute($value)
    {
        return $value ?: $this->nombre;
    }

    public static function generarSlugUnico(string $nombre, $exceptId = null): string
    {
        $base = Str::slug($nombre) ?: 'menu';
        $slug = $base;
        $i = 2;
        while (true) {
            $query = static::where('slug', $slug);
            if ($exceptId) {
                $query->where('id', '!=', $exceptId);
            }
            if (!$query->exists()) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
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
     * Verificar si tiene filtros configurados
     * Solo aplica para menús con enlace (no contenedores)
     */
    public function tieneFiltros(): bool
    {
        // Los contenedores no pueden tener filtros porque no tienen base de productos
        if ($this->tipo_enlace === self::TIPO_NINGUNO) {
            return false;
        }

        return !empty($this->filtros_etiquetas);
    }

    /**
     * Obtener las etiquetas configuradas como filtros
     */
    public function getEtiquetasFiltro()
    {
        if (empty($this->filtros_etiquetas)) {
            return collect();
        }
        $ids = $this->filtros_etiquetas;
        return Etiqueta::whereIn('id', $ids)
            ->get()
            ->sortBy(function ($etiqueta) use ($ids) {
                return array_search($etiqueta->id, $ids);
            })
            ->values();
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
     * Obtener el árbol completo del menú sin caché (para evitar problemas)
     */
    public static function getArbolMenu()
    {
        try {
            return self::raiz()
                ->activos()
                ->orderBy('orden')
                ->with('childrenActivos')
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Limpiar caché del menú (mantenido por compatibilidad)
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
        } elseif ($this->tipo_enlace === self::TIPO_ETIQUETA) {
            return 'Etiqueta';
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
        } elseif ($this->tipo_enlace === self::TIPO_ETIQUETA) {
            $nombre = $this->etiqueta ? $this->etiqueta->nombre : '';
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

        static::creating(function ($menu) {
            if (!$menu->slug && $menu->nombre) {
                $menu->slug = static::generarSlugUnico($menu->nombre);
            }
        });

        static::saved(function () {
            self::limpiarCache();
        });

        static::deleted(function () {
            self::limpiarCache();
        });
    }
}
