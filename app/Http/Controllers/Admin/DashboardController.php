<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'productos' => Producto::count(),
            'productos_disponibles' => Producto::disponibles()->count(),
            'proveedores' => Proveedor::where('activo', true)->count(),
            'usuarios' => User::where('activo', true)->count(),
            'productos_sin_stock' => Producto::where('stock', 0)->where('por_encargue', false)->count(),
        ];

        $productosRecientes = Producto::with('proveedor')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'productosRecientes'));
    }
}
