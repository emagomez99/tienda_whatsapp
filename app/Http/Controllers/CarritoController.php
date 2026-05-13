<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Pedido;
use App\Models\PedidoProducto;
use App\Models\Producto;
use Illuminate\Http\Request;

class CarritoController extends Controller
{
    public function index()
    {
        $ajustes = $this->validarYCorregirStock();
        $carrito = session()->get('carrito', []);
        $productos = [];
        $totalesPorMoneda = [];

        foreach ($carrito as $id => $cantidad) {
            $producto = Producto::with(['especificaciones', 'etiquetas', 'moneda'])->find($id);
            if ($producto) {
                $subtotal = $producto->precio * $cantidad;
                $productos[] = [
                    'producto' => $producto,
                    'cantidad' => $cantidad,
                    'subtotal' => $subtotal,
                ];
                $monedaId = $producto->moneda_id ?? 0;
                if (!isset($totalesPorMoneda[$monedaId])) {
                    $totalesPorMoneda[$monedaId] = [
                        'moneda' => $producto->moneda,
                        'total'  => 0,
                    ];
                }
                $totalesPorMoneda[$monedaId]['total'] += $subtotal;
            }
        }

        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('carrito.index', compact('productos', 'totalesPorMoneda', 'mostrarPrecios', 'ajustes'));
    }

    public function agregar(Request $request, Producto $producto)
    {
        $cantidad = $request->input('cantidad', 1);
        $carrito = session()->get('carrito', []);

        $cantidadActual = isset($carrito[$producto->id]) ? $carrito[$producto->id] : 0;
        $result  = $producto->evaluarCantidad($cantidadActual + $cantidad);
        $warning = null;

        if (! $result->esSuficiente()) {
            if ($result->cantidad <= 0) {
                if ($request->ajax()) {
                    return response()->json([
                        'success'          => false,
                        'message'          => $result->error,
                        'cantidad_carrito' => array_sum($carrito),
                    ]);
                }
                return redirect()->back()->with('warning', $result->error);
            }
            if ($result->cantidad > $cantidadActual) {
                $warning = 'La cantidad de "' . $producto->descripcion . '" fue ajustada al stock disponible (' . $result->cantidad . ').';
            } else {
                if ($request->ajax()) {
                    return response()->json([
                        'success'          => false,
                        'message'          => 'Ya tenés el máximo disponible en el carrito (' . $result->cantidad . ').',
                        'warning'          => true,
                        'cantidad_carrito' => array_sum($carrito),
                    ]);
                }
                return redirect()->back()->with('warning', 'Ya tenés el máximo disponible en el carrito (' . $result->cantidad . ').');
            }
        }

        $carrito[$producto->id] = $result->cantidad;
        session()->put('carrito', $carrito);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $warning ?? 'Producto agregado al carrito',
                'warning' => $warning !== null,
                'cantidad_carrito' => array_sum(session()->get('carrito', [])),
            ]);
        }

        if ($warning) {
            return redirect()->back()->with('warning', $warning);
        }

        return redirect()->back()->with('success', 'Producto agregado al carrito');
    }

    public function actualizar(Request $request, Producto $producto)
    {
        $cantidad = (int) $request->input('cantidad', 1);
        $carrito = session()->get('carrito', []);

        $result  = $producto->evaluarCantidad($cantidad);
        $warning = null;
        if (! $result->esSuficiente()) {
            $cantidad = $result->cantidad;
            $warning  = 'Stock ' . $cantidad;
        }

        if ($cantidad > 0) {
            $carrito[$producto->id] = $cantidad;
        } else {
            unset($carrito[$producto->id]);
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

    public function eliminar(Producto $producto)
    {
        $carrito = session()->get('carrito', []);
        unset($carrito[$producto->id]);
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
        $carrito = session()->get('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index')->with('error', 'El carrito está vacío');
        }

        $productos = [];
        $totalesPorMoneda = [];

        foreach ($carrito as $id => $cantidad) {
            $producto = Producto::with('moneda')->find($id);
            if ($producto) {
                $subtotal = $producto->precio * $cantidad;
                $productos[] = [
                    'producto' => $producto,
                    'cantidad' => $cantidad,
                    'subtotal' => $subtotal,
                ];
                $monedaId = $producto->moneda_id ?? 0;
                if (!isset($totalesPorMoneda[$monedaId])) {
                    $totalesPorMoneda[$monedaId] = [
                        'moneda' => $producto->moneda,
                        'total'  => 0,
                    ];
                }
                $totalesPorMoneda[$monedaId]['total'] += $subtotal;
            }
        }

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

        $carrito = session()->get('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index')->with('error', 'El carrito está vacío');
        }

        $mostrarPrecios = Configuracion::mostrarPrecios();
        $totalesPorMoneda = [];
        $productosTexto = '';
        $productosTextoDetalle = '';

        foreach ($carrito as $id => $cantidad) {
            $producto = Producto::with(['especificaciones', 'etiquetas', 'moneda'])->find($id);
            if (!$producto) {
                continue;
            }

            $subtotal = $producto->precio * $cantidad;
            $monedaId = $producto->moneda_id ?? 0;
            if (!isset($totalesPorMoneda[$monedaId])) {
                $totalesPorMoneda[$monedaId] = [
                    'moneda' => $producto->moneda,
                    'total'  => 0,
                ];
            }
            $totalesPorMoneda[$monedaId]['total'] += $subtotal;

            $simbolo = $producto->moneda ? $producto->moneda->simbolo : '$';
            $lineaBase = "• {$producto->descripcion}";
            if ($producto->id_proveedor) {
                $lineaBase .= " ({$producto->id_proveedor})";
            }
            $lineaBase .= " x{$cantidad}";
            if ($mostrarPrecios) {
                $lineaBase .= " - {$simbolo}" . number_format($subtotal, 2);
            }

            $productosTexto .= $lineaBase . "\n";
            $productosTextoDetalle .= $lineaBase . "\n";

            if ($producto->etiquetas->count() > 0) {
                $etiquetasTexto = $producto->etiquetas->map(function ($e) {
                    return "{$e->nombre}={$e->pivot->valor}";
                })->implode(', ');
                $productosTextoDetalle .= "  Etiquetas: {$etiquetasTexto}\n";
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
                $totalTexto .= "*Total {$simbolo}: " . number_format($grupo['total'], 2) . "*\n";
            }
            $totalTexto = rtrim($totalTexto);
        }

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

        foreach ($carrito as $id => $cantidad) {
            $producto = Producto::find($id);
            if ($producto) {
                PedidoProducto::create([
                    'pedido_id'       => $pedido->id,
                    'producto_id'     => $producto->id,
                    'descripcion'     => $producto->descripcion,
                    'precio_unitario' => $producto->precio,
                    'cantidad'        => $cantidad,
                    'subtotal'        => $producto->precio * $cantidad,
                    'moneda_id'       => $producto->moneda_id,
                ]);
            }
        }

        // Guardar totales por moneda
        foreach ($totalesPorMoneda as $monedaId => $grupo) {
            $pedido->totales()->create([
                'moneda_id' => $monedaId ?: null,
                'total'     => $grupo['total'],
            ]);
        }

        // Construir mensaje usando el template configurable
        $template = Configuracion::templateWhatsapp();
        $mensaje = str_replace(
            ['{pedido_id}', '{nombre}', '{apellido}', '{email}', '{celular}', '{direccion}', '{localidad}', '{provincia}', '{cp}', '{productos+detalles}', '{productos}', '{total}'],
            [$pedido->id, $request->nombre, $request->apellido, $request->email, $request->celular, $request->direccion ?? '', $request->localidad ?? '', $request->provincia ?? '', $request->cp ?? '', rtrim($productosTextoDetalle), rtrim($productosTexto), $totalTexto],
            $template
        );

        // Si no se pidió dirección, limpiar líneas que quedaron vacías o con solo separadores/labels
        if (!$pedirDireccion) {
            $lineas = explode("\n", $mensaje);
            $lineas = array_filter($lineas, function ($linea) {
                // Si la línea tiene "label: valor", verificar que el valor tenga contenido alfanumérico
                if (strpos($linea, ':') !== false) {
                    $partes = explode(':', $linea, 2);
                    $valor  = preg_replace('/[^a-zA-Z0-9\x{00C0}-\x{024F}]/u', '', $partes[1]);
                    if ($valor === '') return false;
                }
                // Eliminar líneas sin ningún contenido alfanumérico
                return preg_replace('/[^a-zA-Z0-9\x{00C0}-\x{024F}]/u', '', $linea) !== '';
            });
            $mensaje = implode("\n", array_values($lineas));
            $mensaje = preg_replace('/\n{3,}/', "\n\n", $mensaje);
        }

        // Obtener número de WhatsApp del administrador
        $whatsapp = Configuracion::whatsappAdmin();

        // Limpiar carrito
        session()->forget('carrito');

        // Codificar mensaje para URL
        $mensajeCodificado = rawurlencode($mensaje);
        $urlWhatsApp = "https://wa.me/{$whatsapp}?text={$mensajeCodificado}";

        return redirect()->away($urlWhatsApp);
    }

    public function cantidadItems()
    {
        $carrito = session()->get('carrito', []);
        return array_sum($carrito);
    }

    public function stockProducto(Producto $producto)
    {
        return response()->json(['stock' => $producto->stockMaximo()]);
    }

    private function validarYCorregirStock()
    {
        $carrito = session()->get('carrito', []);
        $ajustes = [];

        foreach ($carrito as $id => $cantidad) {
            $producto = Producto::find($id);
            if (!$producto) {
                continue;
            }
            $result = $producto->evaluarCantidad($cantidad);
            if (! $result->esSuficiente()) {
                if ($result->cantidad <= 0) {
                    unset($carrito[$id]);
                    $ajustes[] = '"' . $producto->descripcion . '" fue eliminado del carrito porque no tiene stock disponible.';
                } else {
                    $carrito[$id] = $result->cantidad;
                    $ajustes[] = 'La cantidad de "' . $producto->descripcion . '" fue ajustada a ' . $result->cantidad . ' (stock disponible).';
                }
            }
        }

        if (!empty($ajustes)) {
            session()->put('carrito', $carrito);
        }

        return $ajustes;
    }
}
