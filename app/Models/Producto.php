<?php

namespace App\Models;

use App\Support\PrecioVenta;
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
        'precio_compra',
        'moneda_compra_id',
        'margen_ganancia',
        'modo_precio_venta',
        'disponible',
        'stock',
        'por_encargue',
        'url_imagen',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'precio_compra' => 'decimal:2',
        'margen_ganancia' => 'decimal:2',
        'disponible' => 'boolean',
        'por_encargue' => 'boolean',
        'stock' => 'integer',
    ];

    /**
     * Cuánto cuesta el producto y cuánto se le gana es información interna: no tiene
     * por qué salir de la administración. Ocultarlos acá cubre de una sola vez
     * cualquier serialización del modelo (toJson, response()->json, @json) presente
     * o futura, en vez de depender de que cada call site se acuerde de sacarlos.
     */
    protected $hidden = [
        'precio_compra',
        'moneda_compra_id',
        'margen_ganancia',
        'modo_precio_venta',
    ];

    // ─── Precio de venta ─────────────────────────────────────────────────────
    //
    // El precio de venta se PERSISTE siempre en la columna `precio`, incluso en modo
    // margen, donde es un valor derivado. No es un accessor calculado a propósito:
    // el carrito, los pedidos, la grilla de la tienda, el orderBy('precio'), los
    // filtros por precio, el schema.org y el sitemap leen la columna, y un accessor
    // los dejaría a todos afuera del cálculo o los obligaría a cargar dos monedas por
    // producto para poder ordenar 30.000 filas.
    //
    // El costo de eso es que el valor derivado hay que mantenerlo al día: lo hacen
    // ajustarPrecioSegunModo() al guardar un producto y recalcularPreciosEnMargen()
    // al cambiar una cotización.

    /** Precio fijo: el valor lo carga el usuario y no se deriva de nada. */
    const MODO_PRECIO_MANUAL = 'manual';

    /** El precio de venta sale del costo de compra más un margen. */
    const MODO_PRECIO_MARGEN = 'margen';

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    /** Moneda de VENTA: la del precio que ve el cliente. */
    public function moneda()
    {
        return $this->belongsTo(Moneda::class);
    }

    /** Moneda de COMPRA: la del costo declarado por el proveedor. */
    public function monedaCompra()
    {
        return $this->belongsTo(Moneda::class, 'moneda_compra_id');
    }

    public function esPrecioPorMargen()
    {
        return $this->modo_precio_venta === self::MODO_PRECIO_MARGEN;
    }

    /**
     * Precio de venta que le corresponde al producto según su modo.
     * Indeterminado si está en modo manual o si le falta algún dato de la compra.
     */
    public function precioVentaCalculado()
    {
        if (!$this->esPrecioPorMargen()) {
            return PrecioVenta::indeterminado();
        }

        $compra = $this->monedaCompra;
        $venta  = $this->moneda;

        if (!$compra || !$venta) {
            return PrecioVenta::indeterminado();
        }

        return PrecioVenta::calcular(
            $this->precio_compra,
            $compra->cotizacion,
            $venta->cotizacion,
            $this->margen_ganancia
        );
    }

    /**
     * Deja `precio` consistente con el modo de venta, antes de guardar.
     *
     * En modo margen el precio es derivado y se calcula SIEMPRE en el servidor: la
     * vista previa del formulario es sólo una ayuda visual y el valor que mande el
     * navegador no se usa.
     */
    public function ajustarPrecioSegunModo()
    {
        if (!$this->esPrecioPorMargen()) {
            return $this;
        }

        $calculo = $this->precioVentaCalculado();

        if ($calculo->esCalculable()) {
            $this->precio = $calculo->monto;
        }

        return $this;
    }

    /**
     * Reescribe el precio de todos los productos en modo margen, en un solo UPDATE.
     *
     * Un cambio de cotización afecta a todo el catálogo a la vez; hacerlo producto
     * por producto serían 30.000 SELECT + 30.000 UPDATE por cada vez que se actualiza
     * el dólar. La fórmula la aporta PrecioVenta para que no exista una segunda copia
     * de la cuenta dando vueltas.
     *
     * El `IS DISTINCT FROM` acota el UPDATE a las filas que realmente cambian, así el
     * número que se le informa al usuario es "cuántos precios cambiaron" y no "cuántos
     * productos miré".
     *
     * @param  int|null $monedaId Limita el recálculo a los productos que usan esa
     *                            moneda (de compra o de venta). null = todos.
     * @return int Cantidad de productos cuyo precio cambió.
     */
    public static function recalcularPreciosEnMargen($monedaId = null)
    {
        $precio = PrecioVenta::expresionSql('productos', 'mc', 'mv');

        $sql = 'UPDATE productos SET precio = ' . $precio . ', updated_at = now()'
             . ' FROM monedas mc, monedas mv'
             . ' WHERE productos.modo_precio_venta = ?'
             . '   AND productos.moneda_compra_id = mc.id'
             . '   AND productos.moneda_id = mv.id'
             . '   AND productos.precio_compra IS NOT NULL'
             . '   AND productos.margen_ganancia IS NOT NULL'
             . '   AND productos.precio IS DISTINCT FROM ' . $precio;

        $bindings = [self::MODO_PRECIO_MARGEN];

        if ($monedaId !== null) {
            $sql .= ' AND (productos.moneda_compra_id = ? OR productos.moneda_id = ?)';
            $bindings[] = $monedaId;
            $bindings[] = $monedaId;
        }

        return DB::affectingStatement($sql, $bindings);
    }

    /**
     * Cuántos productos entran en el recálculo de esa moneda.
     *
     * Es el mismo universo que recorre recalcularPreciosEnMargen(), sin el
     * `IS DISTINCT FROM` -- ése sólo se puede evaluar comparando contra el precio
     * nuevo, que todavía no existe. Sirve para avisarle al usuario cuánto se va a
     * mover ANTES de moverlo; si las dos consultas se separan, el aviso miente.
     */
    public static function contarEnMargenPorMoneda($monedaId)
    {
        return static::where('modo_precio_venta', self::MODO_PRECIO_MARGEN)
            ->whereNotNull('precio_compra')
            ->whereNotNull('margen_ganancia')
            ->whereNotNull('moneda_compra_id')
            ->whereNotNull('moneda_id')
            ->where(function ($q) use ($monedaId) {
                $q->where('moneda_compra_id', $monedaId)
                  ->orWhere('moneda_id', $monedaId);
            })
            ->count();
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
