<?php

namespace App\Models;

use App\Support\StockResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Producto extends Model
{
    use HasFactory;

    /**
     * TODO(public_id): la columna public_id quedó sin uso funcional.
     *
     * Era la route key (UUID, para que un bot no pudiera enumerar el catálogo con
     * ids secuenciales), pero hoy la URL es /producto/{slug}/{id} y el sitemap
     * publica 30.000+ URLs con sus ids, así que ya no oculta nada. Sobrevive sólo
     * para que sigan resolviendo los links viejos /producto/{uuid}.
     *
     * Para eliminarla: `grep -rn "TODO(public_id)"` lista los puntos a tocar
     * (este boot, $fillable, la ruta legacy + su controlador y su test, y los ids
     * del DOM en carrito/index.blade.php), y después una migración que dropee la
     * columna. Los tres primeros van juntos o el carrito se rompe en silencio.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($modelo) {
            if (empty($modelo->public_id)) {
                $modelo->public_id = Str::uuid()->toString();
            }
            if (empty($modelo->slug)) {
                $modelo->slug = static::generarSlug($modelo->descripcion);
            }
        });
    }

    protected $fillable = [
        'public_id',
        'proveedor_id',
        'id_proveedor',
        'descripcion',
        'slug',
        'detalle',
        'meta_title',
        'meta_description',
        'precio',
        'moneda_id',
        'disponible',
        'stock',
        'por_encargue',
        'url_imagen',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'disponible' => 'boolean',
        'por_encargue' => 'boolean',
        'stock' => 'integer',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class);
    }

    public function etiquetas()
    {
        return $this->belongsToMany(Etiqueta::class, 'producto_etiqueta')
                    ->withPivot('valor')
                    ->withTimestamps();
    }

    public function especificaciones()
    {
        return $this->hasMany(ProductoEspecificacion::class);
    }

    // Imágenes adicionales (la principal vive en url_imagen del producto)
    public function imagenes()
    {
        return $this->hasMany(ProductoImagen::class)->orderBy('orden')->orderBy('id');
    }

    // Todas las imágenes para el carrusel: principal primero, luego las adicionales
    public function galeria()
    {
        $imgs = collect();
        if ($this->imagen_url) {
            $imgs->push((object) ['imagen_url' => $this->imagen_url]);
        }
        foreach ($this->imagenes as $img) {
            $imgs->push((object) ['imagen_url' => $img->imagen_url]);
        }
        return $imgs;
    }

    public function movimientos()
    {
        return $this->hasMany(StockMovimiento::class);
    }

    /**
     * Registra un movimiento de stock y actualiza el campo `stock` atómicamente.
     * Usa lock pesimista para evitar condiciones de carrera concurrentes.
     *
     * @throws \UnderflowException si el movimiento resultaría en stock negativo
     */
    public function registrarMovimiento($variacion, $tipo, $descripcion = null, $pedidoId = null, $userId = null)
    {
        return DB::transaction(function () use ($variacion, $tipo, $descripcion, $pedidoId, $userId) {
            $stockActual     = static::where('id', $this->id)->lockForUpdate()->value('stock');
            $stockResultante = $stockActual + $variacion;

            if ($stockResultante < 0) {
                throw new \UnderflowException(
                    "El movimiento resultaría en stock negativo para \"{$this->descripcion}\" (actual: {$stockActual}, variación: {$variacion})."
                );
            }

            static::where('id', $this->id)->update(['stock' => $stockResultante]);
            $this->stock = $stockResultante;

            return StockMovimiento::create([
                'producto_id'      => $this->id,
                'tipo'             => $tipo,
                'variacion'        => $variacion,
                'stock_resultante' => $stockResultante,
                'descripcion'      => $descripcion,
                'pedido_id'        => $pedidoId,
                'user_id'          => $userId,
            ]);
        });
    }

    // Accessor para obtener el precio formateado con símbolo de moneda
    public function getPrecioConMonedaAttribute()
    {
        $simbolo = $this->moneda ? $this->moneda->simbolo : '$';
        return $simbolo . number_format($this->precio, 2);
    }

    public function estaDisponible()
    {
        return $this->disponible && ($this->stock > 0 || $this->por_encargue);
    }

    /**
     * Retorna el límite máximo de unidades que se pueden pedir.
     * null = sin límite (por encargue o sin control de stock).
     */
    public function stockMaximo()
    {
        if ($this->por_encargue || $this->stock === null) {
            return null;
        }
        return (int) $this->stock;
    }

    /**
     * Evalúa si la cantidad solicitada es satisfacible con el stock actual.
     * Retorna un StockResult con la cantidad permitida y, si corresponde, el motivo del recorte.
     */
    public function evaluarCantidad($solicitada)
    {
        $maximo = $this->stockMaximo();

        if ($maximo === null) {
            return StockResult::ok($solicitada);
        }

        if ($maximo <= 0) {
            return StockResult::insuficiente(0, 'Sin stock disponible para "' . $this->descripcion . '".');
        }

        if ($solicitada > $maximo) {
            return StockResult::insuficiente($maximo, 'Stock insuficiente para "' . $this->descripcion . '". Disponible: ' . $maximo . '.');
        }

        return StockResult::ok($solicitada);
    }

    /**
     * URL completa de la imagen principal.
     * Usa url() en vez de Storage::url() para respetar el dominio del tenant en multi-tenant.
     */
    public function getImagenUrlAttribute()
    {
        if (!$this->url_imagen) {
            return null;
        }
        if (strpos($this->url_imagen, 'http') === 0) {
            return $this->url_imagen;
        }
        return url('storage/' . $this->url_imagen);
    }

    public function esImagenExterna()
    {
        return $this->url_imagen && strpos($this->url_imagen, 'http') === 0;
    }

    /**
     * Meta title SEO. Si no se cargó uno específico, se arma con la descripción más
     * los valores de las etiquetas visibles.
     *
     * Existe porque en catálogos importados la descripción suele ser sólo un código
     * de pieza que se repite: en oleomc hay 185 productos llamados "CTC-1140760".
     * Para Google eso son 185 páginas con el mismo título -- contenido duplicado, e
     * indexa una sola. Sumando fabricante, aplicación y modelo pasan a ser 45 títulos
     * distintos, y encima con los términos que la gente busca de verdad
     * ("Caterpillar 330C" se busca; el código de pieza no).
     *
     * Sin recorte a propósito: Google recorta la *visualización* alrededor de los 60
     * caracteres pero indexa el título completo, así que limitarlo sólo lograría dejar
     * afuera el dato que distingue un producto de otro.
     */
    public function getMetaTitleAttribute($value)
    {
        if ($value) {
            return $value;
        }

        $partes = [trim((string) $this->descripcion)];

        foreach ($this->etiquetas as $etiqueta) {
            $valor = trim((string) $etiqueta->pivot->valor);

            if ($etiqueta->visible_usuarios && $valor !== '') {
                $partes[] = $valor;
            }
        }

        return implode(' · ', $partes);
    }

    /**
     * Meta description SEO. Si no se cargó una específica, se arma a partir del detalle
     * (sin HTML) o, en su defecto, de la descripción.
     */
    public function getMetaDescriptionAttribute($value)
    {
        if ($value) {
            return $value;
        }

        $texto = $this->detalle ? strip_tags($this->detalle) : $this->descripcion;
        $texto = trim(preg_replace('/\s+/', ' ', $texto));

        // Str::limit() agrega "..." DESPUÉS del límite indicado, así que hay que
        // restarle el largo del sufijo para que el total no supere 160 caracteres.
        return Str::limit($texto, 160 - 3);
    }

    public function scopeDisponibles($query)
    {
        return $query->where('disponible', true)
                     ->where(function ($q) {
                         $q->where('stock', '>', 0)
                           ->orWhere('por_encargue', true);
                     });
    }

    /**
     * Productos visibles en la tienda pública: habilitados y, si la configuración
     * no permite mostrar productos sin stock, con stock disponible o por encargue.
     * Es el mismo criterio que usa TiendaController para listar productos, y el
     * que debe usarse para decidir qué productos entran al sitemap.
     */
    public function scopeVisiblesEnTienda($query)
    {
        $query->where('disponible', true);

        if (!Configuracion::mostrarProductosSinStock()) {
            $query->where(function ($q) {
                $q->where('stock', '>', 0)->orWhere('por_encargue', true);
            });
        }

        return $query;
    }

    // ─── URL pública: /producto/{slug}/{id} ──────────────────────────────────
    //
    // El id identifica al producto; el slug que lo precede es decorativo (mismo
    // esquema que MercadoLibre o Amazon). Gracias a eso el slug no necesita ser
    // único ni estable: se puede editar la descripción las veces que haga falta y
    // ninguna URL publicada deja de resolver -- si el slug no es el actual,
    // TiendaController::show() responde 301 hacia la forma canónica.
    //
    // El id va en un SEGMENTO PROPIO y no pegado con guión. Con la forma vieja
    // (/producto/{slug}-{id}) había que adivinar por regex dónde terminaba el slug
    // y empezaba el id, y con slugs que terminan en número la adivinanza fallaba:
    // /producto/jcb-991-00131 servía el id 131 -- un producto distinto -- en lugar
    // de un 404. Afectaba a 1.651 productos del catálogo de oleomc.

    /** Slug usado en la URL cuando el producto no tiene uno propio. */
    const SLUG_POR_DEFECTO = 'producto';

    /**
     * Slug decorativo a partir de la descripción. Puede repetirse entre productos.
     * Devuelve null si la descripción no deja nada slugificable (ej. "!!!").
     */
    public static function generarSlug($descripcion)
    {
        // rtrim: Str::limit puede cortar sobre un guión y dejarlo colgando.
        $slug = rtrim(Str::limit(Str::slug((string) $descripcion), 100, ''), '-');

        if ($slug === '') {
            return null;
        }

        // Un slug de puros dígitos (descripción "12345") sería indistinguible de un id
        // en la ruta corta /producto/{id}, y volvería a abrir la ambigüedad que este
        // esquema elimina: pedir el slug suelto serviría el producto con ese id.
        if (ctype_digit($slug)) {
            $slug = self::SLUG_POR_DEFECTO . '-' . $slug;
        }

        return $slug;
    }

    /** Slug tal como aparece en la URL (nunca vacío, para no dejar un segmento hueco). */
    public function slugUrl()
    {
        return $this->slug ? $this->slug : self::SLUG_POR_DEFECTO;
    }

    /**
     * URL pública canónica. Único lugar donde se arma, para que los call sites no
     * tengan que saber que la ruta lleva dos parámetros.
     */
    public function url()
    {
        return route('tienda.show', [$this->slugUrl(), $this->id]);
    }

    /**
     * El id es la route key en todas las rutas que reciben un {producto}
     * (carrito, admin): son internas y no necesitan el slug decorativo.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        // Guarda: sin esto, un valor no numérico llega como texto a una columna
        // integer de Postgres y revienta con un 500 en vez de un 404.
        if (!ctype_digit((string) $value)) {
            abort(404);
        }

        return $this->where('id', (int) $value)->firstOrFail();
    }
}
