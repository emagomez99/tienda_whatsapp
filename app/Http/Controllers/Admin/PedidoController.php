<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\PedidoProducto;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\Variante;
use App\Support\BusquedaDeTexto;
use App\Support\Paginas;
use App\Support\RangoFechas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedidoController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:pedidos.ver')->only(['index', 'show']);
        $this->middleware('permiso:pedidos.gestionar')->only(['create', 'store', 'buscarCliente', 'sugerirClientes', 'confirmar', 'cancelar', 'updateProducto', 'destroyProducto', 'addProducto']);
    }

    public function index(Request $request)
    {
        // Al entrar sin elegir fechas se ven los últimos 30 días. "Todas las fechas"
        // llega como desde/hasta vacíos, que no es lo mismo que no mandarlos.
        $rango = $request->query->has('desde') || $request->query->has('hasta')
            ? RangoFechas::desdeTextos($request->query('desde'), $request->query('hasta'))
            : RangoFechas::ultimosDias(30);

        // Búsqueda y fechas primero, sin el estado: así los contadores de cada pestaña
        // de estado dicen cuántos hay de cada uno dentro de lo que se está buscando.
        $query = Pedido::query();
        $rango->aplicarA($query, 'created_at');

        $buscar = is_string($request->query('buscar')) ? trim($request->query('buscar')) : '';
        // "#48" o "48": el número de pedido (hasta 18 cifras: más no entra en la columna).
        $numero = ltrim($buscar, '#');
        $esNumero = $numero !== '' && ctype_digit($numero) && strlen($numero) <= 18;
        if ($buscar !== '' && $buscar[0] === '#') {
            $query->where('id', $esNumero ? $numero : 0);
        } elseif ($buscar !== '') {
            // Palabra por palabra y sin tildes: "ana perez" encuentra a "Ana" "Pérez",
            // que están en dos columnas distintas.
            $query->where(function ($q) use ($buscar, $numero, $esNumero) {
                $q->where(function ($q) use ($buscar) {
                    foreach (BusquedaDeTexto::palabras($buscar) as $palabra) {
                        $patron = BusquedaDeTexto::patron($palabra);
                        $q->where(function ($q) use ($patron) {
                            foreach (['nombre', 'apellido', 'email', 'celular'] as $columna) {
                                BusquedaDeTexto::contiene($q, $columna, $patron);
                            }
                        });
                    }
                });
                if ($esNumero) {
                    $q->orWhere('id', $numero);
                }
            });
        }

        $porEstado = (clone $query)
            ->select('estado', DB::raw('COUNT(*) as cantidad'))
            ->groupBy('estado')
            ->pluck('cantidad', 'estado');

        $estado = in_array($request->query('estado'), ['pendiente', 'confirmado', 'cancelado'], true)
            ? $request->query('estado')
            : null;

        $pedidos = Paginas::enRango($query
            ->when($estado, function ($q) use ($estado) {
                $q->where('estado', $estado);
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->with('totales.moneda')
            ->paginate(20)
            ->withQueryString()
            // "Todas las fechas" viaja como desde/hasta vacíos, y withQueryString los
            // pierde: sin esto, la página 2 volvía a "últimos 30 días".
            ->appends($request->query->has('desde') || $request->query->has('hasta') ? $rango->parametros() : []));

        return view('admin.pedidos.index', compact('pedidos', 'rango', 'porEstado', 'estado'));
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
            'productos.*.variante_id' => 'nullable|integer',
            'productos.*.cantidad' => 'required|integer|min:1|max:1000000',
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
            ]);

            foreach ($data['productos'] ?? [] as $p) {
                $producto = Producto::findOrFail($p['id']);
                // Con variantes, el ítem es de una (Talle M): su nombre y su precio.
                $variante = !empty($p['variante_id'])
                    ? Variante::where('producto_id', $producto->id)->findOrFail((int) $p['variante_id'])
                    : null;
                if (!$variante && $producto->tieneVariantes()) {
                    throw ValidationException::withMessages(['productos' => '"' . $producto->descripcion . '" tiene variantes: elegí cuál.']);
                }
                $precio = $variante ? $variante->precioFinal() : $producto->precio;
                $pedido->items()->create([
                    'producto_id'     => $producto->id,
                    'variante_id'     => $variante ? $variante->id : null,
                    'descripcion'     => $variante ? $variante->descripcionCompleta() : $producto->descripcion,
                    'precio_unitario' => $precio,
                    'cantidad'        => (int) $p['cantidad'],
                    'subtotal'        => $precio * (int) $p['cantidad'],
                    'moneda_id'       => $producto->moneda_id,
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
        $pedido->load('items.producto', 'items.moneda', 'totales.moneda');

        return view('admin.pedidos.show', compact('pedido'));
    }

    /**
     * Confirma y descuenta el stock. Todo en una transacción con el pedido
     * bloqueado: un doble clic descontaba dos veces, y si un ítem fallaba a la mitad
     * (otra venta se llevó el stock) los anteriores ya se habían descontado con el
     * pedido todavía pendiente, y al reintentar se descontaban otra vez.
     */
    public function confirmar(Pedido $pedido)
    {
        try {
            $problema = DB::transaction(function () use ($pedido) {
                if (! $pedido->bloquear()->esPendiente()) {
                    return ['error' => 'Solo se pueden confirmar pedidos pendientes.'];
                }

                $pedido->load(['items.producto' => function ($q) { $q->withCount('variantes'); }, 'items.variante.valores']);

                // Validar stock antes de confirmar (el de la variante, si el ítem es de una)
                $errores = [];
                foreach ($pedido->items as $item) {
                    if (!$item->producto) {
                        continue;
                    }
                    if (!$item->variante_id && $item->producto->tieneVariantes()) {
                        $errores[] = '"' . $item->descripcion . '": ahora tiene variantes. Quitalo y volvé a agregarlo eligiendo cuál.';
                        continue;
                    }
                    $origen = $item->origenDeStock();
                    if (! $item->producto->por_encargue && $origen->stock < $item->cantidad) {
                        $errores[] = '"' . $item->descripcion . '": stock disponible ' . $origen->stock . ', requerido ' . $item->cantidad;
                    }
                }

                if (!empty($errores)) {
                    return ['error_stock' => $errores];
                }

                // Descontar stock registrando movimiento por cada ítem con stock disponible
                foreach ($pedido->items as $item) {
                    $origen = $item->producto ? $item->origenDeStock() : null;
                    if ($origen && $origen->stock > 0) {
                        $cantidad = min($item->cantidad, $origen->stock);
                        $origen->registrarMovimiento(
                            -$cantidad,
                            StockMovimiento::TIPO_SALIDA_PEDIDO,
                            'Pedido #' . $pedido->id . ' confirmado',
                            $pedido->id,
                            auth()->id()
                        );
                    }
                }

                $pedido->update(['estado' => 'confirmado']);

                return null;
            });
        } catch (\UnderflowException $e) {
            $problema = ['error' => 'El stock cambió mientras se confirmaba (¿otra venta al mismo tiempo?). No se descontó nada. ' . $e->getMessage()];
        }

        if ($problema) {
            return redirect()->route('admin.pedidos.show', $pedido)->with($problema);
        }

        return redirect()->route('admin.pedidos.show', $pedido)
            ->with('success', 'Pedido confirmado y stock descontado.');
    }

    public function cancelar(Pedido $pedido)
    {
        // Con el pedido bloqueado: dos "Cancelar" a la vez devolvían el stock dos veces.
        $sinDevolver = [];
        $error = DB::transaction(function () use ($pedido, &$sinDevolver) {
            if ($pedido->bloquear()->esCancelado()) {
                return 'El pedido ya está cancelado.';
            }

            // Si estaba confirmado, restaurar stock mediante devolución
            if ($pedido->esConfirmado()) {
                $pedido->load(['items.producto' => function ($q) { $q->withCount('variantes'); }, 'items.variante.valores']);
                foreach ($pedido->items as $item) {
                    if ($this->puedeDevolver($item)) {
                        $item->origenDeStock()->registrarMovimiento(
                            $item->cantidad,
                            StockMovimiento::TIPO_DEVOLUCION_PEDIDO,
                            'Pedido #' . $pedido->id . ' cancelado',
                            $pedido->id,
                            auth()->id()
                        );
                    } elseif ($this->sinDondeDevolver($item)) {
                        $sinDevolver[] = $item->descripcion;
                    }
                }
            }

            $pedido->update(['estado' => 'cancelado']);

            return null;
        });

        if ($error) {
            return redirect()->route('admin.pedidos.show', $pedido)->with('error', $error);
        }

        $redireccion = redirect()->route('admin.pedidos.show', $pedido)->with('success', 'Pedido cancelado.');

        return $sinDevolver ? $redireccion->with('warning', $this->avisoSinDevolver($sinDevolver)) : $redireccion;
    }

    /**
     * Un ítem confirmado cuyo stock no tiene adónde volver: se borró la opción que se
     * vendió (al producto se le quitó el Color) o el producto. Hay que avisarlo, si no
     * el dueño cree que el stock volvió.
     */
    private function sinDondeDevolver(PedidoProducto $item): bool
    {
        return !$item->producto || (!$item->producto->por_encargue && !$this->puedeDevolver($item));
    }

    private function avisoSinDevolver(array $descripciones): string
    {
        return 'No se devolvió el stock de ' . implode(', ', array_map(function ($d) { return '"' . $d . '"'; }, $descripciones))
            . ': esa opción o ese producto ya no existe. Si lo tenés, sumalo a mano desde el producto.';
    }

    public function updateProducto(Request $request, Pedido $pedido, PedidoProducto $item)
    {
        $this->asegurarQueEsDelPedido($pedido, $item);

        if ($pedido->esCancelado()) {
            return redirect()->route('admin.pedidos.show', $pedido)
                ->with('error', 'No se puede editar un pedido cancelado.');
        }

        $request->validate(['cantidad' => 'required|integer|min:1|max:1000000']);

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
        $this->asegurarQueEsDelPedido($pedido, $item);

        // La devolución y el borrado van juntos y con el pedido bloqueado: dos clics
        // en "Quitar" devolvían el stock dos veces.
        $sinDevolver = false;
        $error = DB::transaction(function () use ($pedido, $item, &$sinDevolver) {
            if ($pedido->bloquear()->esCancelado()) {
                return 'No se puede editar un pedido cancelado.';
            }
            if (!PedidoProducto::whereKey($item->id)->exists()) {
                return 'Ese ítem ya no estaba en el pedido.';
            }

            // Restaurar stock solo si el pedido ya estaba confirmado y el producto tiene stock físico
            if ($pedido->esConfirmado() && $this->puedeDevolver($item)) {
                $item->origenDeStock()->registrarMovimiento(
                    $item->cantidad,
                    StockMovimiento::TIPO_DEVOLUCION_PEDIDO,
                    'Ítem eliminado del pedido #' . $pedido->id,
                    $pedido->id,
                    auth()->id()
                );
            } elseif ($pedido->esConfirmado() && $this->sinDondeDevolver($item)) {
                $sinDevolver = true;
            }

            $item->delete();
            $pedido->recalcularTotal();

            return null;
        });

        if ($error) {
            return redirect()->route('admin.pedidos.show', $pedido)->with('error', $error);
        }

        if ($sinDevolver) {
            return redirect()->route('admin.pedidos.show', $pedido)
                ->with('success', 'Ítem eliminado.')
                ->with('warning', $this->avisoSinDevolver([$item->descripcion]));
        }

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
            'variante_id' => 'nullable|integer',
            'cantidad'    => 'required|integer|min:1|max:1000000',
        ]);

        $producto  = Producto::findOrFail($request->producto_id);
        $variante  = $request->filled('variante_id')
            ? Variante::where('producto_id', $producto->id)->findOrFail((int) $request->variante_id)
            : null;
        $resultado = $pedido->agregarProducto($producto, (int) $request->cantidad, $variante);

        return redirect()->route('admin.pedidos.show', $pedido)
            ->with($resultado['ok'] ? 'success' : 'error', $resultado['ok'] ? 'Producto agregado al pedido.' : $resultado['error']);
    }

    /**
     * El ítem de la URL tiene que ser de este pedido: si no, se podía cambiar o
     * quitar (y mover su stock) un ítem de otro pedido escribiendo su número.
     */
    private function asegurarQueEsDelPedido(Pedido $pedido, PedidoProducto $item): void
    {
        if ((int) $item->pedido_id !== (int) $pedido->id) {
            abort(404);
        }
    }

    /**
     * Si el stock de este ítem se puede devolver: a su variante (Talle M) o al
     * producto. No si se encarga (no se descontó), ni si el producto pasó a tener
     * variantes después del pedido: no se sabe a cuál devolverlo.
     */
    private function puedeDevolver(PedidoProducto $item): bool
    {
        if (!$item->producto || $item->producto->por_encargue) {
            return false;
        }

        return $item->variante_id ? (bool) $item->variante : !$item->producto->tieneVariantes();
    }
}
