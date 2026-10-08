<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Cabeceras que le piden al navegador que se cuide solo:
 *
 * - X-Frame-Options: otra página no puede meter la tienda o el panel en un marco
 *   invisible y hacer que el usuario aprete botones sin saberlo.
 * - X-Content-Type-Options: un archivo se usa según su tipo, no según lo que parece.
 * - Referrer-Policy: al salir a otro sitio, no le pasa la dirección completa.
 * - Strict-Transport-Security, sólo con HTTPS: el navegador no vuelve a entrar sin él.
 *
 * Y no anuncia la versión de PHP (X-Powered-By): no le ahorra trabajo a nadie.
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next)
    {
        $respuesta = $next($request);

        if (!headers_sent()) {
            header_remove('X-Powered-By');
        }

        $cabeceras = $respuesta->headers;
        $cabeceras->set('X-Frame-Options', 'SAMEORIGIN', false);
        $cabeceras->set('X-Content-Type-Options', 'nosniff', false);
        $cabeceras->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $cabeceras->remove('X-Powered-By');

        if ($request->isSecure()) {
            $cabeceras->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $respuesta;
    }
}
