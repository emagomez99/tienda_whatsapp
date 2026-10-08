<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PlantillaDeProductos;
use App\Exports\ProductosExport;
use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Importacion;
use App\Services\ImportacionDeProductos;
use App\Support\FiltroDeProductos;
use Illuminate\Http\Request;

/**
 * Productos con Excel: bajar la plantilla, exportar, subir una planilla, ver qué va
 * a pasar y aplicarla por partes (ver ImportacionDeProductos).
 */
class ImportacionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:productos.importar');
    }

    /** Los dos pasos (bajar la plantilla, subir la planilla) y las importaciones anteriores. */
    public function index()
    {
        return view('admin.importaciones.index', [
            'categorias'    => Categoria::enOrdenDeArbol(),
            'importaciones' => Importacion::with('usuario:id,name')->latest()->limit(15)->get(),
        ]);
    }

    public function plantilla(Request $request)
    {
        $categoria = $request->filled('categoria') ? Categoria::findOrFail((int) $request->input('categoria')) : null;
        $nombre = 'plantilla-productos' . ($categoria ? '-' . $categoria->slug : '') . '.xlsx';

        $ruta = $this->archivoTemporal();
        (new PlantillaDeProductos($categoria))->escribir($ruta);

        return response()->download($ruta, $nombre)->deleteFileAfterSend();
    }

    /** Los productos del listado, con sus filtros, para editarlos en Excel y volver a subirlos. */
    public function exportar(Request $request)
    {
        @set_time_limit(300);
        $filtro = new FiltroDeProductos($request->query());

        $ruta = $this->archivoTemporal();
        (new ProductosExport($filtro->consulta()))->escribir($ruta);

        return response()->download($ruta, 'productos-' . now()->format('Y-m-d') . '.xlsx')->deleteFileAfterSend();
    }

    /** Un archivo .xlsx temporal, que se borra después de mandarlo. */
    private function archivoTemporal(): string
    {
        $base = tempnam(sys_get_temp_dir(), 'planilla');
        unlink($base);   // tempnam lo crea vacío; el que se usa es el .xlsx

        return $base . '.xlsx';
    }

    public function store(Request $request, ImportacionDeProductos $importaciones)
    {
        $request->validate([
            'planilla' => 'required|file|mimes:xlsx,csv,txt,ods|max:20480',
        ], [
            'planilla.required' => 'Elegí la planilla que querés subir.',
            'planilla.mimes'    => 'La planilla tiene que ser .xlsx, .csv u .ods. Si es un .xls viejo, abrila en Excel y guardala como .xlsx.',
            'planilla.max'      => 'La planilla puede pesar hasta 20 MB.',
        ]);

        $importacion = $importaciones->preparar($request->file('planilla'), auth()->id());

        if ($importacion->total === 0) {
            return redirect()->route('admin.importaciones.index')
                ->with('error', 'La planilla no tiene productos: revisá que la primera fila tenga los títulos y que haya filas debajo.');
        }

        return redirect()->route('admin.importaciones.show', $importacion);
    }

    /** La vista previa (si falta aplicarla) o el avance y el resultado. */
    public function show(Importacion $importacion)
    {
        return view('admin.importaciones.show', compact('importacion'));
    }

    /** Aplica la siguiente tanda; la pantalla lo llama hasta que termina. */
    public function aplicar(Importacion $importacion, ImportacionDeProductos $importaciones)
    {
        if ($importacion->estado === Importacion::CANCELADA) {
            return response()->json(['error' => 'Esta importación se canceló.'], 422);
        }

        $importacion = $importaciones->aplicarLote($importacion, auth()->id());

        return response()->json([
            'estado'       => $importacion->estado,
            'procesadas'   => $importacion->procesadas,
            'total'        => $importacion->total,
            'porcentaje'   => $importacion->porcentaje(),
            'creados'      => $importacion->creados,
            'actualizados' => $importacion->actualizados,
            'con_error'    => $importacion->con_error,
        ]);
    }

    public function cancelar(Importacion $importacion)
    {
        if ($importacion->estado === Importacion::REVISANDO) {
            $importacion->update(['estado' => Importacion::CANCELADA]);
        }

        return redirect()->route('admin.importaciones.index')->with('success', 'No se aplicó la planilla: no cambió nada.');
    }
}
