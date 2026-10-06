<?php

namespace App\Models;

use App\Support\EstadoValorEtiqueta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Etiqueta extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'visible_usuarios',
    ];

    protected $casts = [
        'visible_usuarios' => 'boolean',
    ];

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'producto_etiqueta')
                    ->using(ProductoEtiqueta::class)
                    ->withPivot('valor', 'etiqueta_valor_id')
                    ->withTimestamps();
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

        foreach ($this->valores()->has('asignaciones')->get(['id', 'normalizado']) as $candidato) {
            if (strlen($candidato->normalizado) > 255) {
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

    public function proveedores()
    {
        return $this->belongsToMany(Proveedor::class, 'proveedor_etiqueta')
                    ->withPivot('obligatoria')
                    ->withTimestamps();
    }
}
