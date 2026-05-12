<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\PedidoProducto;
use App\Models\Producto;
use App\Models\StockMovimiento;
use Illuminate\Http\Request;

class PedidoController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:pedidos.ver')->only(['index', 'show']);
        $this->middleware('permiso:pedidos.gestionar')->only(['create', 'store', 'buscarCliente', 'sugerirClientes', 'confirmar', 'cancelar', 'updateProducto', 'destroyProducto', 'addProducto']);
    }

    public function index(Request $request)
    {
        $query = Pedido::orderBy('created_at', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            // Solo buscar por ID si empieza explícitamente con #
            if (str_starts_with($buscar, '#') && is_numeric(ltrim($buscar, '#'))) {
                $query->where('id', ltrim($buscar, '#'));
            } else {
                $query->where(function ($q) use ($buscar) {
                    $q->where('nombre',   'ilike', "%{$buscar}%")
                      ->orWhere('apellido', 'ilike', "%{$buscar}%")
                      ->orWhere('email',    'ilike', "%{$buscar}%")
                      ->orWhere('celular',  'ilike', "%{$buscar}%");
                });
            }
        }

        $pedidos = $query->paginate(20)->withQueryString();

        return view('admin.pedidos.index', compact('pedidos'));
    }

    public function create()
    {
        return view('admin.pedidos.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'               => 'required|string|max:100',
            'apellido'             => 'nullable|string|max:100',
            'email'                => 'nullable|email|max:150',
            'celular'              => 'nullable|string|max:30',
            'direccion'            => 'nullable|string|max:200',
            'localidad'            => 'nullable|string|max:100',
            'provincia'            => 'nullable|string|max:100',
            'cp'                   => 'nullable|string|max:20',
            'productos'            => 'nullable|array',
            'productos.*.id'       => 'required|integer|exists:productos,id',
            'productos.*.cantidad' => 'required|integer|min:1',
        ]);

        $pedido = \DB::transaction(function () use ($data) {
            $pedido = Pedido::create([
                'nombre'    => $data['nombre'],
                'apellido'  => $data['apellido']  ?? '',
                'email'     => $data['email']     ?? '',
                'celular'   => $data['celular']   ?? '',
                'direccion' => $data['direccion'] ?? '',
                'localidad' => $data['localidad'] ?? '',
                'provincia' => $data['provincia'] ?? '',
                'cp'        => $data['cp']        ?? '',
                'estado'    => 'pendiente',
                'total'     => 0,
            ]);

            foreach ($data['productos'] ?? [] as $p) {
                $producto = Producto::findOrFail($p['id']);
                $pedido->items()->create([
                    'producto_id'     => $producto->id,
                    'descripcion'     => $producto->descripcion,
                    'precio_unitario' => $producto->precio,
                    'cantidad'        => (int) $p['cantidad'],
                    'subtotal'        => $producto->precio * (int) $p['cantidad'],
                ]);
            }

            if (!empty($data['productos'])) {
                $pedido->recalcularTotal();
            }

            return $pedido;
        });

        return redirect()->route('admin.pedidos.show', $pedido)
            ->with('success', 'Pedido creado.');
    }

    public function sugerirClientes(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $resultados = Pedido::where(function ($query) use ($q) {
                $query->where('nombre',   'ilike', "%{$q}%")
                      ->orWhere('apellido', 'ilike', "%{$q}%")
                      ->orWhere('celular',  'ilike', "%{$q}%");
            })
            ->orderBy('created_at', 'desc')
            ->get(['nombre', 'apellido', 'email', 'celular', 'direccion', 'localidad', 'provincia', 'cp'])
            ->unique(function ($p) { return strtolower($p->nombre . '|' . $p->apellido . '|' . $p->celular); })
            ->take(8)
            ->values();

        return response()->json($resultados);
    }

    public function buscarCliente(Request $request)
    {
        $celular = trim($request->get('celular', ''));
        if (!$celular) {
            return response()->json(['found' => false]);
        }

        $pedido = Pedido::where('celular', 'ilike', '%' . $celular . '%')
            ->orderBy('created_at', 'desc')
            ->first(['nombre', 'apellido', 'email', 'celular', 'direccion', 'localidad', 'provincia', 'cp']);

        if (!$pedido) {
            return response()->json(['found' => false]);
        }

        return response()->json(['found' => true, 'data' => $pedido]);
    }

    public function show(Pedido $pedido)
    {
        $pedido->load('items.producto');

        return view('admin.pedidos.show', compact('pedido'));
    }

    public function confirmar(Pedido $pedido)
    {
        if (! $pedido->esPendiente()) {
            return redirect()->route('admin.pedidos.show', $pedido)
                ->with('error', 'Solo se pueden confirmar pedidos pendientes.');
        }

        $pedido->load('items.producto');

        // Validar stock antes de confirmar
        $errores = [];
        foreach ($pedido->items as $item) {
            if ($item->producto && ! $item->producto->por_encargue) {
                if ($item->producto->stock < $item->cantidad) {
                    $errores[] = '"' . $item->descripcion . '": stock disponible ' . $item->producto->stock . ', requerido ' . $item->cantidad;
                }
            }
        }

        if (!empty($errores)) {
            return redirect()->route('admin.pedidos.show', $pedido)
                ->with('error_stock', $errores);
        }

        // Descontar stock registrando movimiento por cada ítem con stock disponible
        foreach ($pedido->items as $item) {
            if ($item->producto && $item->producto->stock > 0) {
                $cantidad = min($item->cantidad, $item->producto->stock);
                $item->producto->registrarMovimiento(
                    -$cantidad,
                    StockMovimiento::TIPO_SALIDA_PEDIDO,
                    'Pedido #' . $pedido->id . ' confirmado',
                    $pedido->id,
                    auth()->id()
                );
            }
        }

        $pedido->update(['estado' => 'confirmado']);

        return redirect()->route('admin.pedidos.show', $pedido)
            ->with('success', 'Pedido confirmado y stock descontado.');
    }

    public function cancelar(Pedido $pedido)
    {
        if ($pedido->esCancelado()) {
            return redirect()->route('admin.pedidos.show', $pedido)
                ->with('error', 'El pedido ya está cancelado.');
        }

        // Si estaba confirmado, restaurar stock mediante devolución
        if ($pedido->esConfirmado()) {
            $pedido->load('items.producto');
            foreach ($pedido->items as $item) {
                if ($item->producto) {
                    $item->producto->registrarMovimiento(
                        $item->cantidad,
                        StockMovimiento::TIPO_DEVOLUCION_PEDIDO,
                        'Pedido #' . $pedido->id . ' cancelado',
                        $pedido->id,
                        auth()->id()
                    );
                }
            }
        }

        $pedido->update(['estado' => 'cancelado']);

        return redirect()->route('admin.pedidos.show', $pedido)
            ->with('success', 'Pedido cancelado.');
    }

    public function updateProducto(Request $request, Pedido $pedido, PedidoProducto $item)
    {
        if ($pedido->esCancelado()) {
            return redirect()->route('admin.pedidos.show', $pedido)
                ->with('error', 'No se puede editar un pedido cancelado.');
        }

        $request->validate(['cantidad' => 'required|integer|min:1']);

        if (! $item->producto) {
            return redirect()->route('admin.pedidos.show', $pedido)
                ->with('error', 'El producto ya no existe.');
        }

        $resultado = $pedido->actualizarItem($item->producto, (int) $request->cantidad, $item);

        return redirect()->route('admin.pedidos.show', $pedido)
            ->with($resultado['ok'] ? 'success' : 'error', $resultado['ok'] ? 'Ítem actualizado.' : $resultado['error']);
    }

    public function destroyProducto(Pedido $pedido, PedidoProducto $item)
    {
        if ($pedido->esCancelado()) {
            return redirect()->route('admin.pedidos.show', $pedido)
                ->with('error', 'No se puede editar un pedido cancelado.');
        }

        // Restaurar stock solo si el pedido ya estaba confirmado
        if ($pedido->esConfirmado() && $item->producto) {
            $item->producto->registrarMovimiento(
                $item->cantidad,
                StockMovimiento::TIPO_DEVOLUCION_PEDIDO,
                'Ítem eliminado del pedido #' . $pedido->id,
                $pedido->id,
                auth()->id()
            );
        }

        $item->delete();
        $pedido->recalcularTotal();

        $msg = $pedido->esConfirmado() ? 'Ítem eliminado y stock restaurado.' : 'Ítem eliminado.';

        return redirect()->route('admin.pedidos.show', $pedido)->with('success', $msg);
    }

    public function addProducto(Request $request, Pedido $pedido)
    {
        if ($pedido->esCancelado()) {
            return redirect()->route('admin.pedidos.show', $pedido)
                ->with('error', 'No se puede editar un pedido cancelado.');
        }

        $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'cantidad'    => 'required|integer|min:1',
        ]);

        $producto  = Producto::findOrFail($request->producto_id);
        $resultado = $pedido->agregarProducto($producto, (int) $request->cantidad);

        return redirect()->route('admin.pedidos.show', $pedido)
            ->with($resultado['ok'] ? 'success' : 'error', $resultado['ok'] ? 'Producto agregado al pedido.' : $resultado['error']);
    }
}
