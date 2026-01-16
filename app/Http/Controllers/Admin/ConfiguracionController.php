<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $configuraciones = Configuracion::orderBy('clave')->get();

        return view('admin.configuraciones.index', compact('configuraciones'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'mostrar_precios' => 'required|in:true,false',
            'mostrar_productos_sin_stock' => 'required|in:true,false',
            'whatsapp_admin' => 'required|string|max:20',
            'nombre_tienda' => 'required|string|max:255',
        ]);

        Configuracion::establecer('mostrar_precios', $request->mostrar_precios, 'Mostrar precios en la tienda');
        Configuracion::establecer('mostrar_productos_sin_stock', $request->mostrar_productos_sin_stock, 'Mostrar productos sin stock');
        Configuracion::establecer('whatsapp_admin', $request->whatsapp_admin, 'Número de WhatsApp del administrador');
        Configuracion::establecer('nombre_tienda', $request->nombre_tienda, 'Nombre de la tienda');

        return redirect()->route('admin.configuraciones.index')
            ->with('success', 'Configuraciones actualizadas correctamente');
    }
}
