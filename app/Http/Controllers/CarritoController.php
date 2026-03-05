<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Producto;
use Illuminate\Http\Request;

class CarritoController extends Controller
{
    public function index()
    {
        $carrito = session()->get('carrito', []);
        $productos = [];
        $total = 0;

        foreach ($carrito as $id => $cantidad) {
            $producto = Producto::with(['especificaciones', 'etiquetas'])->find($id);
            if ($producto) {
                $productos[] = [
                    'producto' => $producto,
                    'cantidad' => $cantidad,
                    'subtotal' => $producto->precio * $cantidad,
                ];
                $total += $producto->precio * $cantidad;
            }
        }

        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('carrito.index', compact('productos', 'total', 'mostrarPrecios'));
    }

    public function agregar(Request $request, Producto $producto)
    {
        $cantidad = $request->input('cantidad', 1);
        $carrito = session()->get('carrito', []);

        if (isset($carrito[$producto->id])) {
            $carrito[$producto->id] += $cantidad;
        } else {
            $carrito[$producto->id] = $cantidad;
        }

        session()->put('carrito', $carrito);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Producto agregado al carrito',
                'cantidad_carrito' => array_sum(session()->get('carrito', [])),
            ]);
        }

        return redirect()->back()->with('success', 'Producto agregado al carrito');
    }

    public function actualizar(Request $request, Producto $producto)
    {
        $cantidad = $request->input('cantidad', 1);
        $carrito = session()->get('carrito', []);

        if ($cantidad > 0) {
            $carrito[$producto->id] = $cantidad;
        } else {
            unset($carrito[$producto->id]);
        }

        session()->put('carrito', $carrito);

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
        $carrito = session()->get('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index')->with('error', 'El carrito está vacío');
        }

        $productos = [];
        $total = 0;

        foreach ($carrito as $id => $cantidad) {
            $producto = Producto::find($id);
            if ($producto) {
                $productos[] = [
                    'producto' => $producto,
                    'cantidad' => $cantidad,
                    'subtotal' => $producto->precio * $cantidad,
                ];
                $total += $producto->precio * $cantidad;
            }
        }

        $mostrarPrecios = Configuracion::mostrarPrecios();

        return view('carrito.checkout', compact('productos', 'total', 'mostrarPrecios'));
    }

    public function enviarPedido(Request $request)
    {
        $request->validate([
            'nombre'    => 'required|string|max:255',
            'apellido'  => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            'celular'   => 'required|string|max:50',
            'direccion' => 'required|string|max:255',
            'localidad' => 'required|string|max:255',
            'provincia' => 'required|string|max:255',
            'cp'        => 'required|string|max:20',
        ]);

        $carrito = session()->get('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index')->with('error', 'El carrito está vacío');
        }

        $total = 0;
        $mostrarPrecios = Configuracion::mostrarPrecios();
        $productosTexto = '';

        foreach ($carrito as $id => $cantidad) {
            $producto = Producto::with(['especificaciones', 'etiquetas'])->find($id);
            if ($producto) {
                $subtotal = $producto->precio * $cantidad;
                $total += $subtotal;

                $productosTexto .= "• {$producto->descripcion}";
                if ($producto->id_proveedor) {
                    $productosTexto .= " ({$producto->id_proveedor})";
                }
                $productosTexto .= " x{$cantidad}";
                if ($mostrarPrecios) {
                    $productosTexto .= " - $" . number_format($subtotal, 2);
                }
                $productosTexto .= "\n";

                // Agregar etiquetas
                if ($producto->etiquetas->count() > 0) {
                    $etiquetasTexto = $producto->etiquetas->map(function ($e) {
                        return "{$e->nombre}={$e->pivot->valor}";
                    })->implode(', ');
                    $productosTexto .= "  Etiquetas: {$etiquetasTexto}\n";
                }

                // Agregar especificaciones
                if ($producto->especificaciones->count() > 0) {
                    $especificacionesTexto = $producto->especificaciones->map(function ($e) {
                        return "{$e->clave}={$e->valor}";
                    })->implode(', ');
                    $productosTexto .= "  Info: {$especificacionesTexto}\n";
                }
            }
        }

        $totalTexto = $mostrarPrecios ? "*Total: $" . number_format($total, 2) . "*" : '';

        // Construir mensaje usando el template configurable
        $template = Configuracion::templateWhatsapp();
        $mensaje = str_replace(
            ['{nombre}', '{apellido}', '{email}', '{celular}', '{direccion}', '{localidad}', '{provincia}', '{cp}', '{productos}', '{total}'],
            [$request->nombre, $request->apellido, $request->email, $request->celular, $request->direccion, $request->localidad, $request->provincia, $request->cp, rtrim($productosTexto), $totalTexto],
            $template
        );

        // Obtener número de WhatsApp del administrador
        $whatsapp = Configuracion::whatsappAdmin();

        // Limpiar carrito
        session()->forget('carrito');

        // Codificar mensaje para URL
        $mensajeCodificado = urlencode($mensaje);
        $urlWhatsApp = "https://wa.me/{$whatsapp}?text={$mensajeCodificado}";

        return redirect()->away($urlWhatsApp);
    }

    public function cantidadItems()
    {
        $carrito = session()->get('carrito', []);
        return array_sum($carrito);
    }
}
