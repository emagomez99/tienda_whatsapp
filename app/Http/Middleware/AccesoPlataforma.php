<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * El panel de la plataforma (crear y borrar tiendas) sólo desde un dominio central
 * y con el usuario y la clave de PLATAFORMA_USUARIO / PLATAFORMA_CLAVE.
 *
 * - Desde el dominio de una tienda: 404, como si no existiera.
 * - Sin usuario o clave configurados: 403. Cerrado por defecto: un servidor nuevo
 *   no queda con el panel abierto porque alguien se olvidó de configurarlo.
 * - Usuario o clave mal: 401, y el navegador vuelve a pedirlos.
 *
 * Va con HTTPS en producción: la autenticación básica manda la clave en cada pedido.
 */
class AccesoPlataforma
{
    public function handle(Request $request, Closure $next)
    {
        if (!in_array($request->getHost(), config('tenancy.central_domains', []), true)) {
            abort(404);
        }

        $usuario = (string) config('plataforma.usuario');
        $clave = (string) config('plataforma.clave');

        if ($usuario === '' || $clave === '') {
            abort(403, 'El panel de la plataforma no está configurado.');
        }

        // hash_equals compara en tiempo constante: no deja adivinar la clave de a una letra.
        $ok = hash_equals($usuario, (string) $request->getUser())
            & hash_equals($clave, (string) $request->getPassword());

        if (!$ok) {
            return response('Usuario o clave incorrectos.', 401, ['WWW-Authenticate' => 'Basic realm="Plataforma", charset="UTF-8"']);
        }

        return $next($request);
    }
}
