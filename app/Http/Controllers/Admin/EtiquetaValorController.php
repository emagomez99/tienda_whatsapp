<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ValorDeEtiquetaDuplicado;
use App\Http\Controllers\Controller;
use App\Models\EtiquetaValor;
use Illuminate\Http\Request;

/**
 * Acciones sobre un valor de etiqueta desde el árbol del panel: ocultarlo,
 * renombrarlo, unirlo con otro o borrarlo si ya no lo usa nadie.
 */
class EtiquetaValorController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:etiquetas.editar');
    }

    public function cambiarVisibilidad(EtiquetaValor $valor)
    {
        $valor->update(['visible' => !$valor->visible]);

        return back()->with('success', $valor->visible
            ? "«{$valor->valor}» ahora se muestra en la tienda."
            : "«{$valor->valor}» ya no se muestra en la tienda.");
    }

    /**
     * Renombrar. Si el nombre nuevo es otro valor que ya existe no se toca nada: se
     * vuelve con la propuesta de unirlos, que el usuario tiene que confirmar.
     */
    public function update(Request $request, EtiquetaValor $valor)
    {
        $request->validate(['valor' => 'required|string|max:255']);

        $anterior = $valor->valor;

        try {
            $valor->renombrar($request->input('valor'));
        } catch (ValorDeEtiquetaDuplicado $e) {
            return back()->with('fusion_propuesta', [
                'origen_id'  => $valor->id,
                'destino_id' => $e->existente()->id,
            ]);
        }

        return back()->with('success', "«{$anterior}» ahora se llama «{$valor->valor}».");
    }

    public function fusionar(Request $request, EtiquetaValor $valor)
    {
        $request->validate(['destino_id' => 'required|integer']);

        $destino = EtiquetaValor::where('etiqueta_id', $valor->etiqueta_id)
            ->findOrFail($request->input('destino_id'));

        $origen = $valor->valor;
        $valor->fusionarEn($destino);

        return back()->with('success', "Los productos de «{$origen}» pasaron a «{$destino->valor}».");
    }

    /** Sólo un valor sin productos: borrar uno en uso dejaría productos sin el dato. */
    public function destroy(EtiquetaValor $valor)
    {
        if ($valor->asignaciones()->exists()) {
            return back()->with('error', "«{$valor->valor}» todavía lo usan productos: no se puede borrar.");
        }

        $valor->delete();

        return back()->with('success', "Se borró «{$valor->valor}».");
    }
}
