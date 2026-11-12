<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Los filtros y buscadores esperan un texto (?buscar=agua). Si alguien arma la
 * dirección a mano con una lista (?buscar[]=agua), ese parámetro se descarta: antes
 * llegaba hasta la consulta y la página daba error.
 */
class ConsultaSinListas
{
    /** Parámetros de la dirección que siempre son un solo valor. */
    const DE_UN_VALOR = [
        'buscar', 'q', 'page', 'orden', 'estado', 'desde', 'hasta',
        'proveedor', 'categoria', 'stock', 'disponible', 'variantes', 'foto', 'encargue',
        'caracteristica', 'valor', 'precio_desde', 'precio_hasta', 'con_stock',
        'activo', 'activa', 'perfil', 'global', 'uso', 'visible',
        'clave', 'celular', 'descripcion', 'enlace_valor', 'menu_id',
    ];

    public function handle(Request $request, Closure $next)
    {
        foreach (self::DE_UN_VALOR as $nombre) {
            if (is_array($request->query($nombre))) {
                $request->query->remove($nombre);
            }
        }

        return $next($request);
    }
}
