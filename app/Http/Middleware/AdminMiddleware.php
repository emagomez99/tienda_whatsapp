<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No tenés permiso para entrar ahí.'], 403);
            }
            return redirect()->route('login')->with('error', 'Ese usuario no puede entrar al panel.');
        }

        // También corta una sesión ya abierta si después se desactivó el usuario o su perfil.
        if (!auth()->user()->puedeEntrar()) {
            $mensaje = auth()->user()->activo ? 'Tu perfil está desactivado' : 'Usuario desactivado';
            auth()->logout();
            return redirect()->route('login')->with('error', $mensaje);
        }

        return $next($request);
    }
}
