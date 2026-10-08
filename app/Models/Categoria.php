<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Qué es un producto: Ropa › Remeras, Celulares, Deportes › Pádel › Paletas.
 *
 * Árbol de hasta tres niveles. Un producto puede estar en varias categorías, y
 * aparece también en las de arriba sin marcarlas: los productos de "Ropa" son los
 * suyos más los de "Remeras" (se calcula, no se guarda dos veces).
 *
 * Ver docs/plan-categorias-y-caracteristicas.md.
 */
class Categoria extends Model
{
    protected $table = 'categorias';

    /** Último nivel en el que una categoría puede tener subcategorías (0 = primer nivel). */
    const NIVEL_MAXIMO_PADRE = 1;

    /** Último nivel posible: 3 niveles en total (Deportes › Pádel › Paletas). */
    const NIVEL_MAXIMO = 2;

    /** Separador de niveles al escribir un camino ("Ropa > Remeras"), como Tiendanube. */
    const SEPARADOR = '>';

    protected $fillable = [
        'parent_id',
        'nombre',
        'slug',
        'orden',
        'visible',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'visible' => 'boolean',
        'orden'   => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function (Categoria $categoria) {
            $categoria->nombre = EtiquetaValor::limpiar($categoria->nombre);
            $categoria->normalizado = EtiquetaValor::normalizar($categoria->nombre);

            if (!$categoria->slug) {
                $categoria->slug = static::slugUnico($categoria->nombre, $categoria->id);
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(Categoria::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Categoria::class, 'parent_id')->orderBy('orden')->orderBy('id');
    }

    /** Los productos asignados a esta categoría puntual (sin sus subcategorías). */
    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'categoria_producto')->withTimestamps();
    }

    /** La categoría y las de arriba, desde ella hasta la raíz. Corta los ciclos. */
    public function linaje(): array
    {
        $cadena = [];
        $vistos = [];
        $actual = $this;

        while ($actual && !isset($vistos[$actual->id ?: spl_object_id($actual)])) {
            $vistos[$actual->id ?: spl_object_id($actual)] = true;
            $cadena[] = $actual;
            $actual = $actual->parent;
        }

        return $cadena;
    }

    /** 0 para las de primer nivel, 1 para sus subcategorías, 2 para las de abajo. */
    public function nivel(): int
    {
        return count($this->linaje()) - 1;
    }

    /** "Ropa > Remeras": como se muestra en el producto y se escribe en una planilla. */
    public function camino(): string
    {
        return implode(' ' . self::SEPARADOR . ' ', array_map(function (Categoria $c) {
            return $c->nombre;
        }, array_reverse($this->linaje())));
    }

    /**
     * Las características que pide esta categoría ella misma, en su orden. Con las
     * heredadas y las globales: CaracteristicasPorCategoria.
     */
    public function caracteristicas()
    {
        return $this->belongsToMany(Etiqueta::class, 'categoria_caracteristica')
            ->withPivot('obligatoria', 'orden')
            ->withTimestamps()
            ->orderBy('categoria_caracteristica.orden')
            ->orderBy('categoria_caracteristica.id');
    }

    /** La página de la categoría en la tienda. */
    public function url(): string
    {
        return route('tienda.categoria', $this->slug);
    }

    /**
     * Título para Google: el cargado, o "Remeras | Tienda". Como en los menús, el
     * nombre solo no dice de quién es ni qué se vende.
     */
    public function tituloParaBuscadores(): string
    {
        if ($this->meta_title) {
            return $this->meta_title;
        }

        $tienda = trim((string) Configuracion::nombreTienda());

        return $tienda === '' || mb_stripos($this->nombre, $tienda) !== false
            ? $this->nombre
            : $this->nombre . ' | ' . $tienda;
    }

    public function descripcionParaBuscadores(): string
    {
        return (string) ($this->meta_description ?: str_replace(' > ', ' › ', $this->camino()) . ' en ' . Configuracion::nombreTienda() . '.');
    }

    public function admiteSubcategorias(): bool
    {
        return $this->nivel() <= self::NIVEL_MAXIMO_PADRE;
    }

    /**
     * Si se ve en la tienda: ella y todas las de arriba visibles. Al ocultar "Ropa"
     * se ocultan sus subcategorías; al volver a mostrarla, cada una recupera su
     * propio estado, porque no se copió nada.
     */
    public function esVisibleEnTienda(): bool
    {
        return $this->ocultaPor() === null;
    }

    /**
     * La columna visible de esta categoría puntual.
     *
     * Ojo: dentro de un modelo, $this->visible no es la columna sino la propiedad
     * protegida de Eloquent con los campos a serializar (siempre un array). Desde
     * afuera sí se lee la columna; acá adentro hay que pedirla así.
     */
    public function marcadaVisible(): bool
    {
        return (bool) $this->getAttribute('visible');
    }

    /** La categoría más cercana hacia arriba que está oculta, si la hay (para "oculta por «Ropa»"). */
    public function ocultaPor(): ?Categoria
    {
        foreach ($this->linaje() as $nivel) {
            if (!$nivel->marcadaVisible()) {
                return $nivel;
            }
        }

        return null;
    }

    /**
     * Las categorías que tienen algo para mostrar: un producto visible en la tienda
     * en ellas o en alguna de las de adentro. Las demás no van al menú automático ni
     * al sitemap: el cliente entraría a un estante vacío.
     *
     * Una consulta para todas (las categorías con productos propios) y el resto, la
     * subida a las madres, en memoria con el árbol ya cargado.
     *
     * @param \Illuminate\Support\Collection|null $arbol Categoria::enOrdenDeArbol(), si ya se tiene
     * @return array<int, true>
     */
    public static function idsConProductos($arbol = null): array
    {
        $arbol = $arbol ?: static::enOrdenDeArbol();
        $porId = $arbol->keyBy('id');

        $conProductos = [];
        $directas = DB::table('categoria_producto')
            ->whereIn('producto_id', Producto::visiblesEnTienda()->select('productos.id'))
            ->distinct()
            ->pluck('categoria_id');

        foreach ($directas as $id) {
            // Ella y todas las de arriba (Gaseosas con productos hace que Bebidas tenga).
            $categoria = $porId->get($id);
            while ($categoria && !isset($conProductos[$categoria->id])) {
                $conProductos[$categoria->id] = true;
                $categoria = $categoria->parent_id ? $porId->get($categoria->parent_id) : null;
            }
        }

        return $conProductos;
    }

    /**
     * Los ids de estas categorías y de todas sus subcategorías, a cualquier
     * profundidad. Una sola consulta recursiva.
     *
     * @param int[] $ids
     * @return int[]
     */
    public static function idsConDescendientes(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($ids), '?'));

        $filas = DB::select("
            WITH RECURSIVE arbol AS (
                SELECT id FROM categorias WHERE id IN ({$marcas})
                UNION
                SELECT c.id FROM categorias c JOIN arbol a ON c.parent_id = a.id
            )
            SELECT id FROM arbol
        ", $ids);

        return array_map(function ($fila) { return (int) $fila->id; }, $filas);
    }

    /**
     * Busca una categoría por su camino ("Ropa > Remeras"), sin distinguir
     * mayúsculas ni espacios de más. Con $crear, crea lo que falte del camino.
     *
     * Es lo que usa la importación desde planilla y el "Crear categoría" del
     * producto.
     *
     * @throws InvalidArgumentException si el camino está vacío o pasa de 3 niveles
     */
    public static function resolverCamino(string $camino, bool $crear = false): ?Categoria
    {
        $partes = array_values(array_filter(array_map(function ($parte) {
            return EtiquetaValor::limpiar($parte);
        }, explode(self::SEPARADOR, $camino)), function ($parte) {
            return $parte !== '';
        }));

        if (empty($partes)) {
            throw new InvalidArgumentException('El camino de la categoría está vacío.');
        }
        if (count($partes) > self::NIVEL_MAXIMO + 1) {
            throw new InvalidArgumentException('«' . $camino . '» tiene más de ' . (self::NIVEL_MAXIMO + 1) . ' niveles.');
        }

        $padre = null;
        foreach ($partes as $nombre) {
            $categoria = static::where('parent_id', $padre ? $padre->id : null)
                ->where('normalizado', EtiquetaValor::normalizar($nombre))
                ->first();

            if (!$categoria) {
                if (!$crear) {
                    return null;
                }
                $categoria = static::create([
                    'nombre'    => $nombre,
                    'parent_id' => $padre ? $padre->id : null,
                    'orden'     => (int) static::where('parent_id', $padre ? $padre->id : null)->max('orden') + 1,
                ]);
            }

            $padre = $categoria;
        }

        return $padre;
    }

    /**
     * Todas las categorías en el orden del árbol (cada madre seguida de sus hijas),
     * con su camino y su nivel: lo que necesita un selector con búsqueda.
     *
     * @return \Illuminate\Support\Collection de Categoria
     */
    public static function enOrdenDeArbol()
    {
        $todas = static::orderBy('orden')->orderBy('id')->get();
        $porMadre = $todas->groupBy(function (Categoria $c) { return $c->parent_id ?: 0; });
        $porId = $todas->keyBy('id');
        $resultado = collect();

        $agregar = function ($madreId) use (&$agregar, $porMadre, $porId, $resultado) {
            foreach ($porMadre->get($madreId, collect()) as $categoria) {
                // Las madres ya están cargadas: camino() y nivel() no hacen consultas.
                if ($categoria->parent_id) {
                    $categoria->setRelation('parent', $porId->get($categoria->parent_id));
                }
                $resultado->push($categoria);
                $agregar($categoria->id);
            }
        };
        $agregar(0);

        return $resultado;
    }

    public static function slugUnico(string $nombre, $exceptoId = null): string
    {
        $base = Str::slug($nombre) ?: 'categoria';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($exceptoId, function ($q) use ($exceptoId) {
            $q->where('id', '!=', $exceptoId);
        })->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
