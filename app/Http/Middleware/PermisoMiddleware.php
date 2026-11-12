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
                return response()->json(['message' => 'No tenés permiso para hacer eso.'], 403);
            }

            return redirect()->route('admin.dashboard')
                ->with('error', 'No tenés permiso para entrar ahí.');
        }

        return $next($request);
    }
}
