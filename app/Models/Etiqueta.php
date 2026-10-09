<?php

namespace App\Models;

use App\Support\EstadoValorEtiqueta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Etiqueta extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'visible_usuarios',
        'global',
        'obligatoria_global',
        'varios_valores',
    ];

    protected $casts = [
        'visible_usuarios' => 'boolean',
        'global' => 'boolean',
        'obligatoria_global' => 'boolean',
        'varios_valores' => 'boolean',
    ];

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'producto_etiqueta')
                    ->using(ProductoEtiqueta::class)
                    ->withPivot('valor', 'etiqueta_valor_id')
                    ->withTimestamps();
    }

    /**
     * La propiedad de las variantes con este nombre ("Talle", "Color"), como en
     * Tiendanube: se elige o se escribe en el producto, sin pasar por Características.
     * Si no existe se crea; si existe, queda admitiendo varios valores (un producto
     * viene en S, M y L).
     */
    public static function paraVariantes(string $nombre): self
    {
        $etiqueta = static::porNombre($nombre);
        if (!$etiqueta) {
            return static::create([
                'nombre'           => EtiquetaValor::limpiar($nombre),
                'visible_usuarios' => true,
                'varios_valores'   => true,
            ]);
        }
        if (!$etiqueta->varios_valores) {
            $etiqueta->update(['varios_valores' => true]);
        }

        return $etiqueta;
    }

    /** Sin importar mayúsculas ni espacios de más: "talle" es "Talle". */
    public static function porNombre(string $nombre): ?self
    {
        return static::whereRaw('lower(trim(nombre)) = ?', [mb_strtolower(EtiquetaValor::limpiar($nombre), 'UTF-8')])->first();
    }

    /** Cuántos productos tienen más de un valor cargado en esta característica. */
    public function productosConVariosValores(): int
    {
        return $this->consultaConVariosValores()->get()->count();
    }

    /** Los nombres de algunos de esos productos, para decir cuáles son. */
    public function nombresConVariosValores(int $cuantos = 3): array
    {
        $ids = $this->consultaConVariosValores()->limit($cuantos)->pluck('producto_id');

        return Producto::whereIn('id', $ids)->orderBy('descripcion')->pluck('descripcion')->all();
    }

    /**
     * Los productos cuyas variantes se eligen por esta característica (Talle M):
     * mientras haya alguno no se puede borrar, o esas variantes quedarían sin
     * nombre y con su stock. La base también lo impide (RESTRICT).
     *
     * @return string[] hasta $cuantos nombres
     */
    public function productosConVariantes(int $cuantos = 3): array
    {
        return Producto::whereIn('id', function ($q) {
            $q->select('v.producto_id')->from('producto_variantes as v')
              ->join('producto_variante_valores as vv', 'vv.variante_id', '=', 'v.id')
              ->where('vv.etiqueta_id', $this->id);
        })->orderBy('descripcion')->limit($cuantos)->pluck('descripcion')->all();
    }

    private function consultaConVariosValores()
    {
        return DB::table('producto_etiqueta')->where('etiqueta_id', $this->id)
            ->select('producto_id')->groupBy('producto_id')->havingRaw('count(*) > 1');
    }

    public function valores()
    {
        return $this->hasMany(EtiquetaValor::class);
    }

    /**
     * Valores de la etiqueta que usa al menos un producto, para los autocompletados.
     *
     * Sólo los usados: un valor que quedó sin productos (porque se corrigió en todos)
     * no se sigue sugiriendo. El corte va en la consulta porque Modelo, en oleomc,
     * tiene más de 2.000 valores.
     */
    public function valoresEnUso(string $buscar, int $limite)
    {
        return $this->valores()
            ->has('asignaciones')
            ->withCount('asignaciones')
            ->buscar($buscar)
            ->orderBy('valor')
            ->limit($limite)
            ->get();
    }

    /**
     * Como valoresEnUso, pero mirando sólo un conjunto de productos (ej. los de un
     * menú): dentro de "Notebook", qué marcas hay entre las notebooks y cuántas de
     * cada una, no en toda la tienda.
     *
     * @param \Illuminate\Database\Eloquent\Builder $productos consulta de productos
     */
    public function valoresEnUsoEntre($productos, string $buscar, int $limite)
    {
        $ids = (clone $productos)->select('productos.id');

        $soloEsos = function ($q) use ($ids) {
            $q->whereIn('producto_id', $ids);
        };

        return $this->valores()
            ->whereHas('asignaciones', $soloEsos)
            ->withCount(['asignaciones' => $soloEsos])
            ->buscar($buscar)
            ->orderBy('valor')
            ->limit($limite)
            ->get();
    }

    /**
     * Qué pasa con un valor escrito para esta etiqueta: si ya existe, o si es nuevo y
     * se parece a alguno que existe (ver EstadoValorEtiqueta).
     */
    public function estadoDeValor(string $escrito): EstadoValorEtiqueta
    {
        $limpio = EtiquetaValor::limpiar($escrito);

        if ($limpio === '') {
            return EstadoValorEtiqueta::vacio();
        }

        $existente = $this->valores()
            ->where('normalizado', EtiquetaValor::normalizar($limpio))
            ->withCount('asignaciones')
            ->first();

        if ($existente) {
            return EstadoValorEtiqueta::existente($existente);
        }

        return EstadoValorEtiqueta::nuevo($this->valorParecido($limpio));
    }

    /**
     * El valor en uso más parecido a lo escrito, si la diferencia es la de un error de
     * tipeo: una letra de más, de menos o cambiada ("Notebok", "Asuz").
     *
     * La tolerancia crece con el largo: en un texto de 4 letras cambiar 2 ya es otra
     * palabra. Con menos de 3 letras no se propone nada: todo se parece a todo.
     *
     * Recorre los valores en PHP: Modelo, el más grande (oleomc), tiene 2.400 y se
     * resuelve en pocos milisegundos; no hace falta pg_trgm.
     */
    /** Los números de un texto, en orden ("400 ml x 2" → ["400", "2"]). */
    private static function numerosDe(string $texto): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?/', $texto, $coincidencias);

        return str_replace(',', '.', $coincidencias[0]);
    }

    public function valorParecido(string $escrito): ?EtiquetaValor
    {
        $buscado = EtiquetaValor::normalizar($escrito);
        $largo   = mb_strlen($buscado);

        if ($largo < 3 || strlen($buscado) > 255) {
            return null;
        }

        $tolerancia = $largo <= 4 ? 1 : ($largo <= 8 ? 2 : 3);

        $mejorId   = null;
        $mejorDist = $tolerancia + 1;

        $numeros = static::numerosDe($buscado);

        foreach ($this->valores()->has('asignaciones')->get(['id', 'normalizado']) as $candidato) {
            if (strlen($candidato->normalizado) > 255) {
                continue;
            }

            // Si cambian los números es otro valor, no un error de tipeo: «16 comprimidos»
            // no es «20 comprimidos», ni FPS 30 es FPS 50.
            if (static::numerosDe($candidato->normalizado) !== $numeros) {
                continue;
            }

            $distancia = levenshtein($buscado, $candidato->normalizado);

            if ($distancia > 0 && $distancia < $mejorDist) {
                $mejorDist = $distancia;
                $mejorId   = $candidato->id;
            }
        }

        return $mejorId ? $this->valores()->withCount('asignaciones')->find($mejorId) : null;
    }
}
