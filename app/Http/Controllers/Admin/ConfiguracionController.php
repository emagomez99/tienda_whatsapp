<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\Moneda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfiguracionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:configuraciones.ver')->only(['index']);
        $this->middleware('permiso:configuraciones.editar')->only(['update']);
    }

    public function index()
    {
        $configuraciones = Configuracion::orderBy('clave')->get();
        $monedas = Moneda::where('activa', true)->orderBy('nombre')->get();

        return view('admin.configuraciones.index', compact('configuraciones', 'monedas'));
    }

    public function update(Request $request)
    {
        $paletasValidas = implode(',', array_keys(Configuracion::paletas()));

        $request->validate([
            'mostrar_precios'            => 'required|in:true,false',
            'mostrar_productos_sin_stock'=> 'required|in:true,false',
            'mostrar_nombre_tienda'      => 'required|in:true,false',
            'whatsapp_admin'             => 'nullable|string|max:20',
            'nombre_tienda'              => 'required|string|max:255',
            'logo'                       => 'nullable|image|max:2048',
            'favicon'                    => 'nullable|mimes:ico,png,jpg,jpeg,svg|max:512',
            'paleta'                     => 'required|in:' . $paletasValidas,
            'posicion_menu'              => 'required|in:superior,lateral',
            'pedir_direccion_envio'      => 'required|in:true,false',
            'template_whatsapp'          => 'nullable|string|max:2000',
            'modo_imagen_producto'       => 'nullable|in:ambos,solo_url,solo_archivo',
            'moneda_default'             => 'nullable|exists:monedas,id',
            'mostrar_proveedor'          => 'required|in:true,false',
            'social_instagram'           => 'nullable|url|max:255',
            'social_facebook'            => 'nullable|url|max:255',
            'social_twitter'             => 'nullable|url|max:255',
            'social_tiktok'              => 'nullable|url|max:255',
            'social_youtube'             => 'nullable|url|max:255',
            'social_whatsapp'            => 'nullable|string|max:20',
        ]);

        Configuracion::establecer('mostrar_precios', $request->mostrar_precios, 'Mostrar precios en la tienda');
        Configuracion::establecer('mostrar_productos_sin_stock', $request->mostrar_productos_sin_stock, 'Mostrar productos sin stock');
        Configuracion::establecer('mostrar_nombre_tienda', $request->mostrar_nombre_tienda, 'Mostrar nombre en cabecera');
        Configuracion::establecer('whatsapp_admin', $request->whatsapp_admin ?? '', 'Número de WhatsApp del administrador');
        Configuracion::establecer('nombre_tienda', $request->nombre_tienda, 'Nombre de la tienda');
        Configuracion::establecer('paleta', $request->paleta, 'Paleta de colores');
        Configuracion::establecer('posicion_menu', $request->posicion_menu, 'Posición del menú en la tienda');
        Configuracion::establecer('pedir_direccion_envio', $request->pedir_direccion_envio, 'Solicitar dirección de envío en el checkout');
        Configuracion::establecer('mostrar_proveedor', $request->mostrar_proveedor, 'Mostrar proveedor en ficha de producto');

        Configuracion::establecer('social_instagram', $request->input('social_instagram', ''), 'Instagram');
        Configuracion::establecer('social_facebook',  $request->input('social_facebook',  ''), 'Facebook');
        Configuracion::establecer('social_twitter',   $request->input('social_twitter',   ''), 'Twitter/X');
        Configuracion::establecer('social_tiktok',    $request->input('social_tiktok',    ''), 'TikTok');
        Configuracion::establecer('social_youtube',   $request->input('social_youtube',   ''), 'YouTube');
        Configuracion::establecer('social_whatsapp',  $request->input('social_whatsapp',  ''), 'WhatsApp footer');

        $templateWhatsapp = $request->filled('template_whatsapp')
            ? $request->template_whatsapp
            : Configuracion::templateWhatsappDefault();
        Configuracion::establecer('template_whatsapp', $templateWhatsapp, 'Template del mensaje de WhatsApp');

        Configuracion::establecer('moneda_default', $request->input('moneda_default', ''), 'Moneda por defecto para nuevos productos');

        if (auth()->user()->esSuperAdmin() && $request->filled('modo_imagen_producto')) {
            Configuracion::establecer('modo_imagen_producto', $request->modo_imagen_producto, 'Modo de carga de imágenes de productos');
        }

        $tenantDir = tenant('id');

        // Manejar logo
        if ($request->hasFile('logo')) {
            $logoAnterior = Configuracion::logo();
            if ($logoAnterior) {
                Storage::disk('public')->delete($logoAnterior);
            }
            $logoPath = $request->file('logo')->store($tenantDir . '/config', 'public');
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
            $faviconPath = $request->file('favicon')->store($tenantDir . '/config', 'public');
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
