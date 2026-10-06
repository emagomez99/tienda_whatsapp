<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Un valor posible de una etiqueta: "Asus" de Marca, "Hidráulica" de Subcategoría.
 *
 * Existe una sola vez por etiqueta y los productos lo referencian. Lo que se escribe
 * a mano al cargar un producto se resuelve contra esta lista (ver resolver): "asus",
 * "ASUS" o "Asus  " terminan siendo el mismo "Asus", y no tres valores que en el
 * menú y en los filtros se comportan como marcas distintas.
 */
class EtiquetaValor extends Model
{
    protected $table = 'etiqueta_valores';

    protected $fillable = [
        'etiqueta_id',
        'valor',
        'normalizado',
    ];

    public function etiqueta()
    {
        return $this->belongsTo(Etiqueta::class);
    }

    /**
     * El valor existente que corresponde a lo escrito, o uno nuevo si no hay.
     *
     * Si ya existe se respeta cómo estaba escrito: cargar "asus" no le cambia el
     * nombre a "Asus" en el resto de los productos.
     */
    public static function resolver($etiquetaId, $valor): self
    {
        $limpio = static::limpiar($valor);

        if ($limpio === '') {
            throw new InvalidArgumentException('El valor de una etiqueta no puede estar vacío.');
        }

        return static::firstOrCreate(
            ['etiqueta_id' => $etiquetaId, 'normalizado' => static::normalizar($limpio)],
            ['valor' => $limpio]
        );
    }

    /** Sin espacios en los extremos ni repetidos en el medio. */
    public static function limpiar($valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $valor));
    }

    /**
     * Clave de comparación: dos valores son el mismo si coinciden acá.
     *
     * Se calcula en PHP y no con lower() de Postgres porque el resultado de lower()
     * con acentos depende del locale de la base, y no es el mismo en Windows que en
     * producción.
     */
    public static function normalizar($valor): string
    {
        return mb_strtolower(static::limpiar($valor), 'UTF-8');
    }
}
