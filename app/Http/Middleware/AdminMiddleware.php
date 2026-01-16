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
                return response()->json(['message' => 'No autorizado'], 403);
            }
            return redirect()->route('login')->with('error', 'Acceso no autorizado');
        }

        if (!auth()->user()->activo) {
            auth()->logout();
            return redirect()->route('login')->with('error', 'Usuario desactivado');
        }

        return $next($request);
    }
}
