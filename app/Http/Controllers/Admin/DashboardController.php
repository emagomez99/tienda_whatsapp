<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Support\Mes;
use App\Support\RangoFechas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:dashboard.ver');
    }

    public function index(Request $request)
    {
        // Mes de las estadísticas de pedidos (por estado, facturado, pedidos listados).
        // Productos, stock, proveedores y pendientes son conteos del momento: no tienen
        // historial que filtrar, y lo pendiente se atiende hoy sea del mes que sea.
        //
        // Se ofrecen al menos los últimos 12 meses, o desde el primer pedido si es anterior.
        $hace12Meses  = Mes::de(Carbon::now()->subMonths(11));
        $primerPedido = Pedido::min('created_at');
        $masViejo     = $primerPedido ? Mes::de(Carbon::parse($primerPedido)) : $hace12Meses;
        if ($hace12Meses->esAnteriorA($masViejo)) {
            $masViejo = $hace12Meses;
        }
        $meses = Mes::hastaHoyDesde($masViejo);

        $mes = Mes::desdeParametro($request->query('mes'));
        if ($mes->esAnteriorA($masViejo)) {
            $mes = $masViejo;
        }
        $hayMesAnterior = $mes->esPosteriorA($masViejo);

        $periodo = [$mes->inicio(), $mes->fin()];
        // Para que los links a Pedidos abran filtrados por el mismo mes.
        $rangoDelMes = RangoFechas::delMes($mes)->parametros();

        $stats = [
            'productos'            => Producto::count(),
            'productos_disponibles'=> Producto::disponibles()->count(),
            'proveedores'          => Proveedor::where('activo', true)->count(),
            'usuarios'             => User::where('activo', true)->count(),
            'productos_sin_stock'  => Producto::where(function ($q) {
                $q->where('stock', 0)->orWhereNull('stock');
            })->count(),
        ];

        $porEstadoDelMes = Pedido::whereBetween('created_at', $periodo)
            ->select('estado', DB::raw('COUNT(*) as cantidad'))
            ->groupBy('estado')
            ->pluck('cantidad', 'estado');

        $pedidosStats = [
            // Todos los pendientes, sin importar el mes: es lo que hay que atender.
            'pendientes'      => Pedido::where('estado', 'pendiente')->count(),
            // Del mes elegido.
            'pendientes_mes'  => (int) ($porEstadoDelMes['pendiente'] ?? 0),
            'confirmados'     => (int) ($porEstadoDelMes['confirmado'] ?? 0),
            'cancelados'      => (int) ($porEstadoDelMes['cancelado'] ?? 0),
            'totales_mes' => DB::table('pedido_totales as pt')
                                ->join('pedidos as p', 'p.id', '=', 'pt.pedido_id')
                                ->leftJoin('monedas as m', 'm.id', '=', 'pt.moneda_id')
                                ->where('p.estado', 'confirmado')
                                ->whereBetween('p.created_at', $periodo)
                                ->select('m.nombre as moneda_nombre', 'm.simbolo as moneda_simbolo', DB::raw('SUM(pt.total) as total'))
                                ->groupBy('pt.moneda_id', 'm.nombre', 'm.simbolo')
                                ->get(),
        ];

        // En el mes actual, los últimos pedidos sin importar el mes (a principio de mes
        // la lista no queda vacía). En un mes pasado, los últimos de ese mes.
        $pedidosRecientes = Pedido::with('totales.moneda')
            ->when(! $mes->esActual(), function ($q) use ($periodo) {
                $q->whereBetween('created_at', $periodo);
            })
            ->orderBy('created_at', 'desc')->limit(5)->get();

        $productosRecientes = Producto::with('proveedor')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'pedidosStats', 'pedidosRecientes', 'productosRecientes', 'mes', 'meses', 'hayMesAnterior', 'rangoDelMes'
        ));
    }
}
