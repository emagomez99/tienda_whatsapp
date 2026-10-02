<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\ColorHex;
use App\Support\RecortadorDeMargenes;
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
        $monedas         = Moneda::where('activa', true)->orderBy('nombre')->get();

        // Productos reales para la vista previa de la card en la pestaña Tienda. Si no
        // hay ninguno que sirva, la vista muestra uno de ejemplo.
        $ejemploConStock = Producto::with(['moneda', 'proveedor'])->disponibles()->whereNotNull('url_imagen')->first()
            ?: Producto::with(['moneda', 'proveedor'])->disponibles()->first();
        $ejemploSinStock = Producto::with('moneda')
            ->where('disponible', true)->where('stock', '<=', 0)->where('por_encargue', false)
            ->first();
        $proveedorEjemplo = optional(optional($ejemploConStock)->proveedor)->nombre
            ?: (Proveedor::orderBy('nombre')->value('nombre') ?: 'Distribuidora Ejemplo');

        return view('admin.configuraciones.index', compact(
            'configuraciones', 'monedas', 'ejemploConStock', 'ejemploSinStock', 'proveedorEjemplo'
        ));
    }

    public function update(Request $request)
    {
        $request->validate([
            'moneda_tienda'              => 'nullable|exists:monedas,id',
            'moneda_favorita'            => 'nullable|exists:monedas,id',
            'mostrar_precios'            => 'required|in:true,false',
            'mostrar_productos_sin_stock'=> 'required|in:true,false',
            'mostrar_nombre_tienda'      => 'required|in:true,false',
            'whatsapp_admin'             => 'nullable|string|max:20',
            'nombre_tienda'              => 'required|string|max:255',
            'logo'                       => 'nullable|image|max:2048',
            'favicon'                    => 'nullable|mimes:ico,png,jpg,jpeg,svg|max:512',
            'logo_alto'                  => 'nullable|integer|between:' . Configuracion::LOGO_ALTO_MIN . ',' . Configuracion::LOGO_ALTO_MAX,
            'color_primario'             => ['required', 'regex:' . ColorHex::PATRON],
            'posicion_menu'              => 'required|in:superior,lateral',
            'pedir_direccion_envio'      => 'required|in:true,false',
            'template_whatsapp'          => 'nullable|string|max:2000',
            'modo_imagen_producto'        => 'nullable|in:ambos,solo_url,solo_archivo',
            'imagenes_adicionales_activas'=> 'nullable|in:true,false',
            'max_imagenes_adicionales'    => 'nullable|integer|min:1|max:20',
            'mostrar_proveedor'          => 'required|in:true,false',
            'social_instagram'           => 'nullable|url|max:255',
            'social_facebook'            => 'nullable|url|max:255',
            'social_twitter'             => 'nullable|url|max:255',
            'social_tiktok'              => 'nullable|url|max:255',
            'social_youtube'             => 'nullable|url|max:255',
            'social_whatsapp'            => 'nullable|string|max:20',
            'seo_titulo_default'         => 'nullable|string|max:60',
            'seo_descripcion_default'    => 'nullable|string|max:160',
            'seo_keywords'               => 'nullable|string|max:255',
            'google_analytics_id'        => ['nullable', 'string', 'max:50', 'regex:/^G-[A-Za-z0-9]+$/'],
            'google_site_verification'   => 'nullable|string|max:255',
            'robots_index'               => 'required|in:true,false',
            'ubicacion_activa'           => 'required|in:true,false',
            'ciudad'                     => 'nullable|required_if:ubicacion_activa,true|string|max:100',
            'provincia'                  => 'nullable|string|max:100',
            'direccion'                  => 'nullable|string|max:255',
            'codigo_postal'              => 'nullable|string|max:20',
        ], [
            'ciudad.required_if' => 'La ciudad es obligatoria si activás la ubicación para SEO local.',
        ]);

        Configuracion::establecer('mostrar_precios', $request->mostrar_precios, 'Mostrar precios en la tienda');
        Configuracion::establecer('mostrar_productos_sin_stock', $request->mostrar_productos_sin_stock, 'Mostrar productos sin stock');
        Configuracion::establecer('mostrar_nombre_tienda', $request->mostrar_nombre_tienda, 'Mostrar nombre en cabecera');
        Configuracion::establecer('whatsapp_admin', $request->whatsapp_admin ?? '', 'Número de WhatsApp del administrador');
        Configuracion::establecer('nombre_tienda', $request->nombre_tienda, 'Nombre de la tienda');
        Configuracion::establecer('logo_alto', (string) ($request->input('logo_alto') ?: Configuracion::LOGO_ALTO_DEFAULT), 'Alto del logo en la cabecera (px)');
        Configuracion::establecer('color_primario', ColorHex::desde($request->color_primario)->hex(), 'Color principal de la tienda');
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

        Configuracion::establecer('seo_titulo_default', $request->input('seo_titulo_default', ''), 'Meta title por defecto de la tienda');
        Configuracion::establecer('seo_descripcion_default', $request->input('seo_descripcion_default', ''), 'Meta description por defecto de la tienda');
        Configuracion::establecer('seo_keywords', $request->input('seo_keywords', ''), 'Palabras clave por defecto de la tienda');
        Configuracion::establecer('google_analytics_id', $request->input('google_analytics_id', ''), 'ID de Google Analytics (GA4)');
        Configuracion::establecer('google_site_verification', $request->input('google_site_verification', ''), 'Código de verificación de Google Search Console');
        Configuracion::establecer('robots_index', $request->robots_index, 'Permitir que los buscadores indexen la tienda');

        Configuracion::establecer('ubicacion_activa', $request->ubicacion_activa, 'Declarar ubicación de la tienda para SEO local');
        Configuracion::establecer('ciudad', $request->input('ciudad', ''), 'Ciudad donde opera/está radicada la tienda');
        Configuracion::establecer('provincia', $request->input('provincia', ''), 'Provincia donde opera/está radicada la tienda');
        Configuracion::establecer('direccion', $request->input('direccion', ''), 'Dirección del local (solo si es visitable por el público)');
        Configuracion::establecer('codigo_postal', $request->input('codigo_postal', ''), 'Código postal');

        if (auth()->user()->esSuperAdmin()) {
            if ($request->filled('modo_imagen_producto')) {
                Configuracion::establecer('modo_imagen_producto', $request->modo_imagen_producto, 'Modo de carga de imágenes de productos');
            }
            Configuracion::establecer('imagenes_adicionales_activas', $request->input('imagenes_adicionales_activas', 'true'), 'Habilitar imágenes adicionales por producto');
            Configuracion::establecer('max_imagenes_adicionales', (string) max(1, (int) ($request->input('max_imagenes_adicionales') ?: 3)), 'Máximo de imágenes adicionales por producto');
        }

        $tenantDir = tenant('id');

        // Manejar logo
        if ($request->hasFile('logo')) {
            $logoAnterior = Configuracion::logo();
            if ($logoAnterior) {
                Storage::disk('public')->delete($logoAnterior);
            }
            $logoPath = $request->file('logo')->store($tenantDir . '/config', 'public');
            // Sin el aire transparente alrededor, el logo usa todo el alto de la cabecera.
            RecortadorDeMargenes::recortar(Storage::disk('public')->path($logoPath));
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

        $aviso = $this->aplicarMonedaDeLaTienda($request->input('moneda_tienda'));
        $this->aplicarMonedaFavorita($request->input('moneda_favorita'));

        return redirect()->route('admin.configuraciones.index')
            ->with('success', 'Configuraciones actualizadas correctamente' . $aviso);
    }

    /**
     * Cambia en qué moneda cobra la tienda.
     *
     * Vive en Ajustes y no en el ABM de monedas porque es un dato de la tienda que se
     * contesta una vez -- "¿en qué moneda cobrás?" -- y no una propiedad que haya que
     * decidir cada vez que se edita una moneda cualquiera.
     *
     * Marcarla dispara Moneda::reexpresarLasDemas(), que divide las otras cotizaciones
     * por la de ésta: es un cambio de unidad de medida, así que las equivalencias
     * reales no se mueven y los precios tampoco. El recálculo posterior recorre el
     * catálogo entero -- todas las cotizaciones cambiaron a la vez -- y en la práctica
     * sólo corrige redondeos.
     *
     * @return string Texto a agregar al mensaje de éxito. Vacío si no cambió nada.
     */
    private function aplicarMonedaDeLaTienda($monedaId)
    {
        if (!$monedaId) {
            return '';
        }

        $nueva = Moneda::find($monedaId);

        if (!$nueva || $nueva->es_base) {
            return '';
        }

        $nueva->es_base = true;
        $nueva->save();

        $recalculados = Producto::recalcularPreciosEnMargen();

        $aviso = '. Ahora cobrás en ' . $nueva->nombre
               . ': las cotizaciones de las demás monedas se reexpresaron solas y los precios no se movieron';

        return $aviso . ($recalculados > 0 ? ' (se ajustó el redondeo de ' . $recalculados . ').' : '.');
    }

    /**
     * Cambia qué moneda viene elegida al cargar un producto.
     *
     * A diferencia de la moneda de la tienda, ésta puede no existir: es una comodidad
     * de carga, no una pieza estructural. Marcarla degrada a la anterior desde el
     * modelo (ver Moneda::boot), así que acá sólo hay que encender la nueva o apagar
     * la que hubiera.
     */
    private function aplicarMonedaFavorita($monedaId)
    {
        if ($monedaId) {
            $nueva = Moneda::find($monedaId);

            if ($nueva && !$nueva->es_default) {
                $nueva->es_default = true;
                $nueva->save();
            }

            return;
        }

        // Se consulta por la marca y no con porDefecto(), que filtra por activa: si
        // quedó una favorita inactiva por un camino viejo, hay que poder apagarla.
        $actual = Moneda::where('es_default', true)->first();

        if ($actual) {
            $actual->es_default = false;
            $actual->save();
        }
    }
}
