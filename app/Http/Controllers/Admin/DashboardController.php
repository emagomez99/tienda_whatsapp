<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:dashboard.ver');
    }

    public function index()
    {
        $stats = [
            'productos'            => Producto::count(),
            'productos_disponibles'=> Producto::disponibles()->count(),
            'proveedores'          => Proveedor::where('activo', true)->count(),
            'usuarios'             => User::where('activo', true)->count(),
            'productos_sin_stock'  => Producto::where('stock', 0)->where('por_encargue', false)->count(),
        ];

        $pedidosStats = [
            'pendientes'  => Pedido::where('estado', 'pendiente')->count(),
            'confirmados' => Pedido::where('estado', 'confirmado')->count(),
            'cancelados'  => Pedido::where('estado', 'cancelado')->count(),
            'total_mes'   => Pedido::where('estado', 'confirmado')
                                ->whereMonth('created_at', now()->month)
                                ->whereYear('created_at', now()->year)
                                ->sum('total'),
        ];

        $pedidosRecientes = Pedido::orderBy('created_at', 'desc')->limit(5)->get();

        $productosRecientes = Producto::with('proveedor')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'pedidosStats', 'pedidosRecientes', 'productosRecientes'));
    }
}
