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
            'productos_sin_stock'  => Producto::where(function ($q) {
                $q->where('stock', 0)->orWhereNull('stock');
            })->count(),
        ];

        $pedidosStats = [
            'pendientes'  => Pedido::where('estado', 'pendiente')->count(),
            'confirmados' => Pedido::where('estado', 'confirmado')->count(),
            'cancelados'  => Pedido::where('estado', 'cancelado')->count(),
            'totales_mes' => DB::table('pedido_totales as pt')
                                ->join('pedidos as p', 'p.id', '=', 'pt.pedido_id')
                                ->leftJoin('monedas as m', 'm.id', '=', 'pt.moneda_id')
                                ->where('p.estado', 'confirmado')
                                ->whereMonth('p.created_at', now()->month)
                                ->whereYear('p.created_at', now()->year)
                                ->select('m.nombre as moneda_nombre', 'm.simbolo as moneda_simbolo', DB::raw('SUM(pt.total) as total'))
                                ->groupBy('pt.moneda_id', 'm.nombre', 'm.simbolo')
                                ->get(),
        ];

        $pedidosRecientes = Pedido::with('totales.moneda')->orderBy('created_at', 'desc')->limit(5)->get();

        $productosRecientes = Producto::with('proveedor')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'pedidosStats', 'pedidosRecientes', 'productosRecientes'));
    }
}
