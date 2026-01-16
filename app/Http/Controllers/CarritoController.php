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
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'celular' => 'required|string|max:50',
        ]);

        $carrito = session()->get('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index')->with('error', 'El carrito está vacío');
        }

        // Construir mensaje para WhatsApp
        $mensaje = "🛒 *NUEVO PEDIDO*\n\n";
        $mensaje .= "*Cliente:*\n";
        $mensaje .= "Nombre: {$request->nombre} {$request->apellido}\n";
        $mensaje .= "Email: {$request->email}\n";
        $mensaje .= "Celular: {$request->celular}\n\n";
        $mensaje .= "*Productos:*\n";

        $total = 0;
        $mostrarPrecios = Configuracion::mostrarPrecios();

        foreach ($carrito as $id => $cantidad) {
            $producto = Producto::with(['especificaciones', 'etiquetas'])->find($id);
            if ($producto) {
                $subtotal = $producto->precio * $cantidad;
                $total += $subtotal;

                $mensaje .= "• {$producto->descripcion}";
                if ($producto->id_proveedor) {
                    $mensaje .= " ({$producto->id_proveedor})";
                }
                $mensaje .= " x{$cantidad}";
                if ($mostrarPrecios) {
                    $mensaje .= " - $" . number_format($subtotal, 2);
                }
                $mensaje .= "\n";

                // Agregar etiquetas
                if ($producto->etiquetas->count() > 0) {
                    $etiquetasTexto = $producto->etiquetas->map(function ($e) {
                        return "{$e->nombre}={$e->pivot->valor}";
                    })->implode(', ');
                    $mensaje .= "  Etiquetas: {$etiquetasTexto}\n";
                }

                // Agregar especificaciones
                if ($producto->especificaciones->count() > 0) {
                    $especificacionesTexto = $producto->especificaciones->map(function ($e) {
                        return "{$e->clave}={$e->valor}";
                    })->implode(', ');
                    $mensaje .= "  Info: {$especificacionesTexto}\n";
                }
            }
        }

        if ($mostrarPrecios) {
            $mensaje .= "\n*Total: $" . number_format($total, 2) . "*";
        }

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
