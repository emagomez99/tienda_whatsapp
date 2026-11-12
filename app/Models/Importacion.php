<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Una planilla de Excel subida para importar productos (ver ImportacionDeProductos).
 *
 * Las filas leídas y analizadas se guardan aparte, en un archivo JSON en el disco
 * local (`filas()`), porque pueden ser decenas de miles y se aplican por partes.
 */
class Importacion extends Model
{
    protected $table = 'importaciones';

    const REVISANDO = 'revisando';   // leída y analizada: falta que la apliquen
    const APLICANDO = 'aplicando';
    const TERMINADA = 'terminada';
    const CANCELADA = 'cancelada';

    protected $fillable = [
        'user_id', 'nombre_archivo', 'ruta', 'estado', 'total', 'procesadas',
        'creados', 'actualizados', 'con_error', 'resumen',
    ];

    protected $casts = [
        'resumen' => 'array',
        'total' => 'integer',
        'procesadas' => 'integer',
        'creados' => 'integer',
        'actualizados' => 'integer',
        'con_error' => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Dónde van las filas analizadas, al lado de la planilla. */
    public function rutaFilas(): string
    {
        return preg_replace('/\.[^.\/]+$/', '', $this->ruta) . '.filas.json';
    }

    public function guardarFilas(array $filas): void
    {
        Storage::disk('local')->put($this->rutaFilas(), json_encode($filas, JSON_UNESCAPED_UNICODE));
    }

    /** @return array las filas analizadas (ver AnalisisDeImportacion) */
    public function filas(): array
    {
        $disco = Storage::disk('local');

        return $disco->exists($this->rutaFilas()) ? json_decode($disco->get($this->rutaFilas()), true) : [];
    }

    /** Las filas que no le cambian nada al producto: no se aplican (ver AnalisisDeImportacion). */
    public function sinCambios(): int
    {
        return (int) (($this->resumen ?: [])['sin_cambios'] ?? 0);
    }

    public function porcentaje(): int
    {
        return $this->total > 0 ? (int) floor($this->procesadas * 100 / $this->total) : 100;
    }

    public function terminada(): bool
    {
        return in_array($this->estado, [self::TERMINADA, self::CANCELADA], true);
    }
}
