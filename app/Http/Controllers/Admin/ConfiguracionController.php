<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $configuraciones = Configuracion::orderBy('clave')->get();

        return view('admin.configuraciones.index', compact('configuraciones'));
    }

    public function update(Request $request)
    {
        $paletasValidas = implode(',', array_keys(Configuracion::paletas()));

        $request->validate([
            'mostrar_precios' => 'required|in:true,false',
            'mostrar_productos_sin_stock' => 'required|in:true,false',
            'mostrar_nombre_tienda' => 'required|in:true,false',
            'whatsapp_admin' => 'required|string|max:20',
            'nombre_tienda' => 'required|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'favicon' => 'nullable|mimes:ico,png,jpg,jpeg,svg|max:512',
            'paleta' => 'required|in:' . $paletasValidas,
            'posicion_menu' => 'required|in:superior,lateral',
        ]);

        Configuracion::establecer('mostrar_precios', $request->mostrar_precios, 'Mostrar precios en la tienda');
        Configuracion::establecer('mostrar_productos_sin_stock', $request->mostrar_productos_sin_stock, 'Mostrar productos sin stock');
        Configuracion::establecer('mostrar_nombre_tienda', $request->mostrar_nombre_tienda, 'Mostrar nombre en cabecera');
        Configuracion::establecer('whatsapp_admin', $request->whatsapp_admin, 'Número de WhatsApp del administrador');
        Configuracion::establecer('nombre_tienda', $request->nombre_tienda, 'Nombre de la tienda');
        Configuracion::establecer('paleta', $request->paleta, 'Paleta de colores');
        Configuracion::establecer('posicion_menu', $request->posicion_menu, 'Posición del menú en la tienda');

        // Manejar logo
        if ($request->hasFile('logo')) {
            // Eliminar logo anterior si existe
            $logoAnterior = Configuracion::logo();
            if ($logoAnterior) {
                Storage::disk('public')->delete($logoAnterior);
            }
            $logoPath = $request->file('logo')->store('config', 'public');
            Configuracion::establecer('logo', $logoPath, 'Logo de la tienda');
        }

        // Eliminar logo si se solicita
        if ($request->has('eliminar_logo') && $request->eliminar_logo) {
            $logoAnterior = Configuracion::logo();
            if ($logoAnterior) {
                Storage::disk('public')->delete($logoAnterior);
            }
            Configuracion::establecer('logo', '', 'Logo de la tienda');
        }

        // Manejar favicon
        if ($request->hasFile('favicon')) {
            $faviconAnterior = Configuracion::favicon();
            if ($faviconAnterior) {
                Storage::disk('public')->delete($faviconAnterior);
            }
            $faviconPath = $request->file('favicon')->store('config', 'public');
            Configuracion::establecer('favicon', $faviconPath, 'Favicon de la tienda');
        }

        // Eliminar favicon si se solicita
        if ($request->has('eliminar_favicon') && $request->eliminar_favicon) {
            $faviconAnterior = Configuracion::favicon();
            if ($faviconAnterior) {
                Storage::disk('public')->delete($faviconAnterior);
            }
            Configuracion::establecer('favicon', '', 'Favicon de la tienda');
        }

        return redirect()->route('admin.configuraciones.index')
            ->with('success', 'Configuraciones actualizadas correctamente');
    }
}
