<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PermisoMiddleware
{
    public function handle(Request $request, Closure $next, $permiso)
    {
        if (!auth()->check() || !auth()->user()->puede($permiso)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No tienes permiso para realizar esta acción.'], 403);
            }

            return redirect()->route('admin.dashboard')
                ->with('error', 'No tienes permiso para acceder a esa sección.');
        }

        return $next($request);
    }
}
