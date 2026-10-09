<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Pedido;
use App\Models\PedidoProducto;
use App\Models\Producto;
use App\Models\Variante;
use App\Support\LineaDeCarrito;
use App\Support\MensajeWhatsapp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * El carrito de la tienda, en la sesión: clave de línea => cantidad (ver
 * LineaDeCarrito). Una línea es un producto o una de sus variantes (Talle M).
 */
class CarritoController extends Controller
{
    public function index()
    {
        $ajustes = $this->validarYCorregirStock();
        // Lo ve el cliente: sólo etiquetas y valores visibles. El mensaje de
        // WhatsApp (enviarPedido) sí lleva todas, porque lo recibe la tienda.
        $lineas = $this->lineas(['especificaciones', 'etiquetasPublicas', 'moneda']);
        $productos = $this->paraLaVista($lineas);
        $totalesPorMoneda = $this->totales($lineas);
        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('carrito.index', compact('productos', 'totalesPorMoneda', 'mostrarPrecios', 'ajustes'));
    }

    public function agregar(Request $request, Producto $producto)
    {
        $cantidad = $this->cantidadPedida($request);
        $carrito = session()->get('carrito', []);

        // Con variantes, se agrega una (Talle M): sin elegirla no se puede.
        $variante = $this->varianteElegida($request, $producto, 'variante_id');
        if ($variante === null && $producto->tieneVariantes()) {
            return $this->respuestaAgregar($request, false, 'Elegí una opción antes de agregarlo (por ejemplo, el talle).', $carrito);
        }

        $clave = LineaDeCarrito::clave($producto->id, $variante ? $variante->id : null);
        $origen = $variante ?: $producto;
        $nombre = $variante ? $variante->descripcionCompleta() : $producto->descripcion;

        $cantidadActual = $carrito[$clave] ?? 0;
        $result  = $origen->evaluarParaLaTienda($cantidadActual + $cantidad);
        $warning = null;

        if (! $result->esSuficiente()) {
            if ($result->cantidad <= 0) {
                return $this->respuestaAgregar($request, false, $result->error, $carrito);
            }
            if ($result->cantidad > $cantidadActual) {
                $warning = 'La cantidad de "' . $nombre . '" fue ajustada al stock disponible (' . $result->cantidad . ').';
            } else {
                return $this->respuestaAgregar($request, false, 'Ya tenés el máximo disponible en el carrito (' . $result->cantidad . ').', $carrito, true);
            }
        }

        $carrito[$clave] = $result->cantidad;
        session()->put('carrito', $carrito);

        return $this->respuestaAgregar($request, true, $warning ?? 'Producto agregado al carrito', $carrito, $warning !== null);
    }

    private function respuestaAgregar(Request $request, bool $ok, string $mensaje, array $carrito, bool $aviso = false)
    {
        if ($request->ajax()) {
            return response()->json(array_filter([
                'success'          => $ok,
                'message'          => $mensaje,
                'warning'          => $aviso,
                'cantidad_carrito' => array_sum($carrito),
            ], function ($v) { return $v !== null; }));
        }

        return redirect()->back()->with($ok && !$aviso ? 'success' : 'warning', $mensaje);
    }

    public function actualizar(Request $request, Producto $producto)
    {
        $cantidad = (int) $request->input('cantidad', 1);
        $carrito = session()->get('carrito', []);
        $variante = $this->varianteElegida($request, $producto, 'variante');
        $clave = LineaDeCarrito::clave($producto->id, $variante ? $variante->id : null);

        $result  = ($variante ?: $producto)->evaluarParaLaTienda($cantidad);
        $warning = null;
        if (! $result->esSuficiente()) {
            $cantidad = $result->cantidad;
            $warning  = 'Stock ' . $cantidad;
        }

        if ($cantidad > 0) {
            $carrito[$clave] = $cantidad;
        } else {
            unset($carrito[$clave]);
        }

        session()->put('carrito', $carrito);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'warning' => $warning,
                'cantidad' => $cantidad,
                'cantidad_carrito' => array_sum($carrito),
            ]);
        }

        if ($warning) {
            return redirect()->route('carrito.index')->with('warning', $warning);
        }

        return redirect()->route('carrito.index')->with('success', 'Carrito actualizado');
    }

    public function eliminar(Request $request, Producto $producto)
    {
        $variante = $this->varianteElegida($request, $producto, 'variante');
        $carrito = session()->get('carrito', []);
        unset($carrito[LineaDeCarrito::clave($producto->id, $variante ? $variante->id : null)]);
        session()->put('carrito', $carrito);

        return redirect()->route('carrito.index')->with('success', 'Producto eliminado del carrito');
    }

    public function vaciar()
    {
        session()->forget('carrito');

        return redirect()->route('carrito.index')->with('success', 'Carrito vaciado');
    }

    public function checkout()
    {
        $ajustes = $this->validarYCorregirStock();

        if (empty(session()->get('carrito', []))) {
            return redirect()->route('carrito.index')->with('error', 'El carrito está vacío');
        }

        $lineas = $this->lineas(['moneda']);
        $productos = $this->paraLaVista($lineas);
        $totalesPorMoneda = $this->totales($lineas);
        $mostrarPrecios = Configuracion::mostrarPrecios();
        $pedirDireccion = Configuracion::pedirDireccionEnvio();

        return view('carrito.checkout', compact('productos', 'totalesPorMoneda', 'mostrarPrecios', 'pedirDireccion', 'ajustes'));
    }

    public function enviarPedido(Request $request)
    {
        $pedirDireccion = Configuracion::pedirDireccionEnvio();
        $reglaDireccion = $pedirDireccion ? 'required|string|max:255' : 'nullable|string|max:255';

        $request->validate([
            'nombre'    => 'required|string|max:255',
            'apellido'  => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            'celular'   => 'required|string|max:50',
            'direccion' => $reglaDireccion,
            'localidad' => $reglaDireccion,
            'provincia' => $reglaDireccion,
            'cp'        => $pedirDireccion ? 'required|string|max:20' : 'nullable|string|max:20',
        ]);

        // Re-validar stock en el momento del envío (otro usuario pudo haber comprado)
        $ajustes = $this->validarYCorregirStock();
        if (!empty($ajustes)) {
            session()->flash('ajustes_stock', $ajustes);
            return redirect()->route('carrito.checkout');
        }

        $lineas = $this->lineas(['especificaciones', 'etiquetas', 'moneda']);
        if (empty($lineas)) {
            return redirect()->route('carrito.index')->with('error', 'El carrito está vacío');
        }

        $mostrarPrecios = Configuracion::mostrarPrecios();
        $totalesPorMoneda = $this->totales($lineas);
        $productosTexto = '';
        $productosTextoDetalle = '';

        foreach ($lineas as $linea) {
            $producto = $linea->producto;
            $simbolo = $producto->moneda ? $producto->moneda->simbolo : '$';
            $lineaBase = '• ' . $linea->descripcion();
            if ($linea->codigo()) {
                $lineaBase .= ' (' . $linea->codigo() . ')';
            }
            $lineaBase .= " x{$linea->cantidad}";
            if ($mostrarPrecios) {
                $lineaBase .= " - {$simbolo}" . number_format($linea->subtotal(), 2, ',', '.');
            }

            $productosTexto .= $lineaBase . "\n";
            $productosTextoDetalle .= $lineaBase . "\n";

            if ($producto->etiquetas->count() > 0) {
                $etiquetasTexto = collect(Producto::agruparCaracteristicas($producto->etiquetas))->map(function ($c) {
                    return "{$c['etiqueta']->nombre}={$c['texto']}";
                })->implode(', ');
                $productosTextoDetalle .= "  Características: {$etiquetasTexto}\n";
            }

            if ($producto->especificaciones->count() > 0) {
                $especificacionesTexto = $producto->especificaciones->map(function ($e) {
                    return "{$e->clave}={$e->valor}";
                })->implode(', ');
                $productosTextoDetalle .= "  Info: {$especificacionesTexto}\n";
            }
        }

        $totalTexto = '';
        if ($mostrarPrecios) {
            foreach ($totalesPorMoneda as $grupo) {
                $simbolo = $grupo['moneda'] ? $grupo['moneda']->simbolo : '$';
                $totalTexto .= "*Total {$simbolo}: " . number_format($grupo['total'], 2, ',', '.') . "*\n";
            }
            $totalTexto = rtrim($totalTexto);
        }

        // El pedido, sus ítems y sus totales juntos: si algo falla a la mitad no
        // queda un pedido sin ítems o sin total en el panel.
        $pedido = DB::transaction(function () use ($request, $lineas, $totalesPorMoneda) {
            // Guardar pedido en la base de datos
            $pedido = Pedido::create([
                'nombre'    => $request->nombre,
                'apellido'  => $request->apellido,
                'email'     => $request->email,
                'celular'   => $request->celular,
                'direccion' => $request->direccion,
                'localidad' => $request->localidad,
                'provincia' => $request->provincia,
                'cp'        => $request->cp,
                'estado'    => 'pendiente',
            ]);

            foreach ($lineas as $linea) {
                PedidoProducto::create([
                    'pedido_id'       => $pedido->id,
                    'producto_id'     => $linea->producto->id,
                    'variante_id'     => $linea->variante ? $linea->variante->id : null,
                    'descripcion'     => $linea->descripcion(),
                    'precio_unitario' => $linea->precio(),
                    'cantidad'        => $linea->cantidad,
                    'subtotal'        => $linea->subtotal(),
                    'moneda_id'       => $linea->producto->moneda_id,
                ]);
            }

            // Guardar totales por moneda
            foreach ($totalesPorMoneda as $monedaId => $grupo) {
                $pedido->totales()->create([
                    'moneda_id' => $monedaId ?: null,
                    'total'     => $grupo['total'],
                ]);
            }

            return $pedido;
        });

        // Mensaje con la plantilla configurable. MensajeWhatsapp limpia lo que queda de
        // los datos vacíos (la dirección cuando no se pide, un email que falta...).
        $mensaje = MensajeWhatsapp::armar(Configuracion::templateWhatsapp(), [
            'pedido_id'          => $pedido->id,
            'nombre'             => $request->nombre,
            'apellido'           => $request->apellido,
            'email'              => $request->email,
            'celular'            => $request->celular,
            'direccion'          => $pedirDireccion ? $request->direccion : '',
            'localidad'          => $pedirDireccion ? $request->localidad : '',
            'provincia'          => $pedirDireccion ? $request->provincia : '',
            'cp'                 => $pedirDireccion ? $request->cp : '',
            'productos'          => rtrim($productosTexto),
            'productos+detalles' => rtrim($productosTextoDetalle),
            'total'              => $totalTexto,
        ]);

        // Obtener número de WhatsApp del administrador
        $whatsapp = Configuracion::whatsappAdmin();

        // Limpiar carrito
        session()->forget('carrito');

        // Codificar mensaje para URL (api.whatsapp.com evita la redirección de wa.me que puede corromper emojis)
        $phone = preg_replace('/\D/', '', $whatsapp);
        $mensajeCodificado = rawurlencode($mensaje);
        $urlWhatsApp = "https://api.whatsapp.com/send?phone={$phone}&text={$mensajeCodificado}";

        return redirect()->away($urlWhatsApp);
    }

    public function cantidadItems()
    {
        $carrito = session()->get('carrito', []);
        return array_sum($carrito);
    }

    public function stockProducto(Request $request, Producto $producto)
    {
        $variante = $this->varianteElegida($request, $producto, 'variante');

        return response()->json(['stock' => ($variante ?: $producto)->stockMaximo()]);
    }

    /** Un número entero de 1 en adelante: "abc", 0 o -3 no se piden. */
    private function cantidadPedida(Request $request): int
    {
        $request->validate(['cantidad' => 'nullable|integer|min:1|max:100000']);

        return (int) $request->input('cantidad', 1);
    }

    /** La variante que vino en el pedido, si es de este producto. */
    private function varianteElegida(Request $request, Producto $producto, string $campo): ?Variante
    {
        if (!$request->filled($campo)) {
            return null;
        }

        $variante = Variante::where('producto_id', $producto->id)->with('valores')->find((int) $request->input($campo));
        if ($variante) {
            $variante->setRelation('producto', $producto);
        }

        return $variante;
    }

    /**
     * Las líneas del carrito con su producto (y variante), en el orden en que se
     * agregaron. Las de productos que ya no existen se saltean.
     *
     * @return LineaDeCarrito[]
     */
    private function lineas(array $con = []): array
    {
        $carrito = session()->get('carrito', []);
        $ids = [];
        $idsVariantes = [];
        foreach (array_keys($carrito) as $clave) {
            [$productoId, $varianteId] = LineaDeCarrito::leerClave($clave);
            $ids[] = $productoId;
            if ($varianteId) {
                $idsVariantes[] = $varianteId;
            }
        }

        // Con el conteo de variantes: validarYCorregirStock pregunta tieneVariantes()
        // por cada línea, y sin esto era una consulta por producto.
        $productos = Producto::with($con)->withCount('variantes')->whereIn('id', array_unique($ids))->get()->keyBy('id');
        $variantes = empty($idsVariantes)
            ? collect()
            : Variante::with('valores.etiqueta')->whereIn('id', array_unique($idsVariantes))->get()->keyBy('id');

        $lineas = [];
        foreach ($carrito as $clave => $cantidad) {
            [$productoId, $varianteId] = LineaDeCarrito::leerClave($clave);
            $producto = $productos[$productoId] ?? null;
            if (!$producto) {
                continue;
            }
            $variante = null;
            if ($varianteId) {
                $variante = $variantes[$varianteId] ?? null;
                if (!$variante || (int) $variante->producto_id !== (int) $producto->id) {
                    continue;
                }
                $variante->setRelation('producto', $producto);
            }
            $lineas[] = new LineaDeCarrito($producto, $variante, (int) $cantidad);
        }

        return $lineas;
    }

    /** Lo que esperan las vistas del carrito y del checkout. */
    private function paraLaVista(array $lineas): array
    {
        return array_map(function (LineaDeCarrito $linea) {
            return [
                'linea'    => $linea,
                'clave'    => $linea->claveDeLinea(),
                'producto' => $linea->producto,
                'variante' => $linea->variante,
                'cantidad' => $linea->cantidad,
                'precio'   => $linea->precio(),
                'subtotal' => $linea->subtotal(),
            ];
        }, $lineas);
    }

    /** moneda_id => ['moneda' => Moneda|null, 'total' => ...] */
    private function totales(array $lineas): array
    {
        $totales = [];
        foreach ($lineas as $linea) {
            $monedaId = $linea->producto->moneda_id ?? 0;
            if (!isset($totales[$monedaId])) {
                $totales[$monedaId] = ['moneda' => $linea->producto->moneda, 'total' => 0];
            }
            $totales[$monedaId]['total'] += $linea->subtotal();
        }

        return $totales;
    }

    private function validarYCorregirStock()
    {
        $carrito = session()->get('carrito', []);
        $ajustes = [];
        $quedan = [];

        foreach ($this->lineas() as $linea) {
            $clave = $linea->claveDeLinea();
            $quedan[$clave] = true;
            $nombre = $linea->descripcion();

            // Un carrito armado antes de que el producto tuviera variantes: no se sabe cuál.
            if (!$linea->variante && $linea->producto->tieneVariantes()) {
                unset($carrito[$clave]);
                $ajustes[] = '"' . $nombre . '" fue eliminado del carrito: ahora tiene opciones (por ejemplo, talles). Volvé a agregarlo eligiendo una.';
                continue;
            }

            $result = $linea->origen()->evaluarParaLaTienda($linea->cantidad);
            if (! $result->esSuficiente()) {
                if ($result->cantidad <= 0) {
                    unset($carrito[$clave]);
                    $aLaVenta = $linea->producto->disponible && (!$linea->variante || $linea->variante->activa);
                    $ajustes[] = $aLaVenta
                        ? '"' . $nombre . '" fue eliminado del carrito porque no tiene stock disponible.'
                        : '"' . $nombre . '" fue eliminado del carrito porque ya no está a la venta.';
                } else {
                    $carrito[$clave] = $result->cantidad;
                    $ajustes[] = 'La cantidad de "' . $nombre . '" fue ajustada a ' . $result->cantidad . ' (stock disponible).';
                }
            }
        }

        // Las líneas de productos que ya no existen se van sin aviso. Si lo que ya no
        // existe es la opción (se le quitó el Color al producto), se avisa: el cliente
        // la había elegido y si no la ve más no sabe qué pasó.
        $sinOpcion = [];
        foreach (array_keys($carrito) as $clave) {
            if (!isset($quedan[(string) $clave])) {
                [$productoId, $varianteId] = LineaDeCarrito::leerClave($clave);
                if ($varianteId) {
                    $sinOpcion[] = $productoId;
                }
                unset($carrito[$clave]);
            }
        }
        if ($sinOpcion) {
            foreach (Producto::whereIn('id', array_unique($sinOpcion))->orderBy('descripcion')->pluck('descripcion') as $nombre) {
                $ajustes[] = 'La opción de "' . $nombre . '" que elegiste ya no está a la venta y se quitó del carrito. Fijate si hay otra que te sirva.';
            }
        }

        if (!empty($ajustes) || count($carrito) !== count(session()->get('carrito', []))) {
            session()->put('carrito', $carrito);
        }

        return $ajustes;
    }
}
