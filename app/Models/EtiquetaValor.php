<?php

namespace App\Models;

use App\Exceptions\ValorDeEtiquetaDuplicado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
        'visible',
    ];

    protected $casts = [
        'visible' => 'boolean',
    ];

    public function etiqueta()
    {
        return $this->belongsTo(Etiqueta::class);
    }

    /** Filas de producto_etiqueta que usan este valor: una por producto. */
    public function asignaciones()
    {
        return $this->hasMany(ProductoEtiqueta::class, 'etiqueta_valor_id');
    }

    /**
     * Cómo se ofrece en los autocompletados: el valor y cuántos productos lo usan,
     * para distinguir el "oficial" de uno cargado una vez por error.
     *
     * @return array{valor: string, detalle: string}
     */
    public function comoSugerencia(): array
    {
        $productos = (int) $this->asignaciones_count;

        return [
            'valor'   => $this->valor,
            'detalle' => $productos . ($productos === 1 ? ' producto' : ' productos'),
        ];
    }

    public function scopeBuscar($query, string $buscar)
    {
        $buscar = trim($buscar);

        if ($buscar !== '') {
            $query->where('valor', 'ilike', '%' . $buscar . '%');
        }

        return $query;
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

    /**
     * Cambia el nombre del valor en todos los productos que lo usan.
     *
     * Cambiar sólo mayúsculas o espacios ("asus" → "Asus") es un renombre común. Si el
     * nombre nuevo es otro valor que ya existe, no se renombra: eso es una fusión y
     * tiene que pedirse explícitamente con fusionarEn.
     *
     * @throws ValorDeEtiquetaDuplicado
     */
    public function renombrar(string $nuevo): void
    {
        $limpio = static::limpiar($nuevo);

        if ($limpio === '') {
            throw new InvalidArgumentException('El valor de una etiqueta no puede estar vacío.');
        }

        $existente = static::where('etiqueta_id', $this->etiqueta_id)
            ->where('normalizado', static::normalizar($limpio))
            ->where('id', '!=', $this->id)
            ->first();

        if ($existente) {
            throw new ValorDeEtiquetaDuplicado($existente);
        }

        DB::transaction(function () use ($limpio) {
            $anterior = $this->valor;

            $this->update(['valor' => $limpio, 'normalizado' => static::normalizar($limpio)]);

            $this->asignaciones()->update(['valor' => $limpio]);
            $this->actualizarMenus($anterior, $limpio);
        });
    }

    /**
     * Pasa los productos de este valor a $destino y borra este.
     *
     * Ningún producto puede quedar con los dos: producto_etiqueta admite un solo valor
     * por producto y etiqueta, así que mover las filas no choca con nada.
     */
    public function fusionarEn(EtiquetaValor $destino): void
    {
        if ((int) $destino->etiqueta_id !== (int) $this->etiqueta_id) {
            throw new InvalidArgumentException('Sólo se pueden unir valores de la misma etiqueta.');
        }

        if ((int) $destino->id === (int) $this->id) {
            return;
        }

        DB::transaction(function () use ($destino) {
            $this->asignaciones()->update([
                'etiqueta_valor_id' => $destino->id,
                'valor'             => $destino->valor,
            ]);
            $this->actualizarMenus($this->valor, $destino->valor);

            $this->delete();
        });
    }

    /**
     * Los menús de tipo etiqueta guardan el valor como texto: si no se actualizan, al
     * renombrar el valor el menú se queda vacío.
     */
    private function actualizarMenus(string $anterior, string $nuevo): void
    {
        $normalizado = static::normalizar($anterior);

        Menu::where('tipo_enlace', Menu::TIPO_ETIQUETA)
            ->where('enlace_id', $this->etiqueta_id)
            ->whereNotNull('enlace_valor')
            ->get()
            ->filter(function (Menu $menu) use ($normalizado) {
                return static::normalizar($menu->enlace_valor) === $normalizado;
            })
            ->each(function (Menu $menu) use ($nuevo) {
                $menu->update(['enlace_valor' => $nuevo]);
            });
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
