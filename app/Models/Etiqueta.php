<?php

namespace App\Models;

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
        $query = $this->valores()
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                  ->from('producto_etiqueta')
                  ->whereColumn('producto_etiqueta.etiqueta_valor_id', 'etiqueta_valores.id');
            });

        $buscar = trim($buscar);
        if ($buscar !== '') {
            $query->where('valor', 'ilike', '%' . $buscar . '%');
        }

        return $query->orderBy('valor')->limit($limite)->pluck('valor');
    }

    public function proveedores()
    {
        return $this->belongsToMany(Proveedor::class, 'proveedor_etiqueta')
                    ->withPivot('obligatoria')
                    ->withTimestamps();
    }
}
