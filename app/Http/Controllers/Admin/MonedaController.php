<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Moneda;
use App\Models\Producto;
use Illuminate\Http\Request;

class MonedaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permiso:monedas.ver')->only(['index']);
        $this->middleware('permiso:monedas.crear')->only(['create', 'store']);
        $this->middleware('permiso:monedas.editar')->only(['edit', 'update']);
        $this->middleware('permiso:monedas.eliminar')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Moneda::withCount(['productos', 'productosDeCompra']);

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'ilike', "%{$buscar}%")
                  ->orWhere('codigo', 'ilike', "%{$buscar}%");
            });
        }

        // La base primero: es la referencia contra la que se leen las demás.
        $monedas = $query->orderBy('es_base', 'desc')->orderBy('nombre')->paginate(15);
        $base    = Moneda::base();

        // Para la tabla de equivalencias: todas, no sólo la página, y sin el filtro
        // de búsqueda -- una equivalencia entre dos monedas no depende de lo que se
        // esté buscando en el listado.
        $todas = Moneda::orderBy('es_base', 'desc')->orderBy('codigo')->get();

        return view('admin.monedas.index', compact('monedas', 'base', 'todas'));
    }

    public function create()
    {
        return view('admin.monedas.create', [
            'base'    => Moneda::base(),
            'monedas' => Moneda::orderBy('codigo')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->normalizarCotizacion($this->validar($request));

        $validated['activa']     = $request->boolean('activa');
        $validated['es_base']    = $request->boolean('es_base');
        $validated['es_default'] = $request->boolean('es_default');

        $moneda = Moneda::create($validated);

        return redirect()->route('admin.monedas.index')
            ->with('success', 'Moneda "' . $moneda->nombre . '" creada correctamente');
    }

    public function edit(Moneda $moneda)
    {
        return view('admin.monedas.edit', [
            'moneda'  => $moneda,
            'base'    => Moneda::base(),
            'usos'    => $moneda->usos(),
            'monedas' => Moneda::orderBy('codigo')->get(),
            // Cuántos precios va a reescribir un cambio de cotización. Se calcula acá
            // y no en la vista para que el aviso salga de la misma definición que el
            // recálculo (ver Producto::contarEnMargenPorMoneda).
            'productosAfectados' => Producto::contarEnMargenPorMoneda($moneda->id),
        ]);
    }

    public function update(Request $request, Moneda $moneda)
    {
        $validated = $this->normalizarCotizacion($this->validar($request, $moneda));

        $validated['activa']     = $request->boolean('activa');
        $validated['es_base']    = $request->boolean('es_base');
        $validated['es_default'] = $request->boolean('es_default');

        // Desactivar una moneda en uso rompe los formularios que la ofrecen y deja
        // productos apuntando a una moneda que ya no se puede elegir.
        if (!$validated['activa'] && $moneda->activa) {
            $bloqueo = $this->motivoParaNoTocar($moneda, 'desactivar');

            if ($bloqueo) {
                return back()->withInput()->with('error', $bloqueo);
            }

            // Una moneda inactiva no se ofrece en el formulario de producto, así que
            // no puede ser la preseleccionada. El modelo la reactivaría en silencio
            // para sostener el invariante; es mejor decir por qué no se puede.
            if ($validated['es_default']) {
                return back()->withInput()->with(
                    'error',
                    'No se puede desactivar "' . $moneda->nombre . '" mientras venga preseleccionada: '
                    . 'destildá esa marca, o marcá otra moneda como preseleccionada.'
                );
            }
        }

        // Si dejaba de ser base habría cero bases y las cotizaciones perderían su
        // unidad de medida. Se cambia de base marcando OTRA, no desmarcando ésta.
        if ($moneda->es_base && !$validated['es_base']) {
            return back()->withInput()->with(
                'error',
                'No se puede dejar la tienda sin moneda de referencia: marcá otra como referencia y ésta deja de serlo sola.'
            );
        }

        $cotizacionAnterior = (float) $moneda->cotizacion;
        $eraBase            = $moneda->es_base;
        $eraDefault         = $moneda->es_default;

        $moneda->update($validated);

        $mensaje = 'Moneda "' . $moneda->nombre . '" actualizada correctamente';

        // El precio de venta está persistido en productos.precio: si cambia la
        // cotización hay que reescribirlo, o el catálogo queda con precios viejos.
        if ((float) $moneda->cotizacion !== $cotizacionAnterior) {
            $mensaje .= '. ' . $this->resumenDeRecalculo(Producto::recalcularPreciosEnMargen($moneda->id));
        }

        // Cambiar de base no reescala las demás cotizaciones: son números cargados a
        // mano y recalcularlos sería inventar valores que nadie cotizó. Quedan
        // expresadas contra la base vieja hasta que alguien las vuelva a cargar, y
        // eso hay que decirlo, no dejarlo pasar en silencio.
        if (!$eraBase && $moneda->es_base) {
            $mensaje .= ' Ahora las cotizaciones se escriben en ' . $moneda->codigo . ': revisá las de las demás monedas.';
        }

        if (!$eraDefault && $moneda->es_default) {
            $mensaje .= ' Los productos nuevos van a venir en ' . $moneda->codigo . '.';
        }

        return redirect()->route('admin.monedas.index')->with('success', $mensaje);
    }

    public function destroy(Moneda $moneda)
    {
        $bloqueo = $this->motivoParaNoTocar($moneda, 'eliminar');

        if ($bloqueo) {
            return redirect()->route('admin.monedas.index')->with('error', $bloqueo);
        }

        $nombre = $moneda->nombre;
        $moneda->delete();

        return redirect()->route('admin.monedas.index')
            ->with('success', 'Moneda "' . $nombre . '" eliminada correctamente');
    }

    private function validar(Request $request, Moneda $moneda = null)
    {
        $unico = $moneda ? ',' . $moneda->id : '';

        return $request->validate([
            'nombre'     => 'required|string|max:255|unique:monedas,nombre' . $unico,
            'codigo'     => 'required|string|size:3|unique:monedas,codigo' . $unico,
            'simbolo'    => 'required|string|max:3',
            'cotizacion' => 'required|numeric|min:0.000001',
            'activa'     => 'boolean',
            'es_base'    => 'boolean',
            'es_default' => 'boolean',
            // Contra qué moneda está expresada la cotización que se acaba de cargar.
            // Ausente = contra la base, que es como se guarda internamente.
            'cotizacion_referencia_id' => [
                'nullable',
                'exists:monedas,id',
                function ($atributo, $valor, $fallar) use ($moneda) {
                    if ($moneda && (int) $valor === (int) $moneda->id) {
                        $fallar('Una moneda no se puede cotizar contra sí misma: elegí otra moneda de referencia.');
                    }
                },
            ],
        ], [
            'nombre.required'     => 'Poné un nombre para la moneda.',
            'nombre.unique'       => 'Ya existe una moneda con ese nombre.',
            'codigo.required'     => 'Indicá el código de la moneda (ej: ARS, USD).',
            'codigo.size'         => 'El código tiene que tener exactamente 3 letras (ej: ARS, USD).',
            'codigo.unique'       => 'Ya existe una moneda con ese código.',
            'simbolo.required'    => 'Indicá el símbolo con el que se muestran los precios (ej: $, U$S).',
            'simbolo.max'         => 'El símbolo no puede tener más de 3 caracteres.',
            'cotizacion.required' => 'Indicá la cotización de la moneda.',
            'cotizacion.numeric'  => 'La cotización tiene que ser un número.',
            'cotizacion.min'      => 'La cotización tiene que ser mayor a cero.',
        ]);
    }

    /**
     * Traduce la cotización cargada a la escala interna, que siempre es contra la
     * moneda base.
     *
     * El formulario deja escribir "1 EUR equivale a 1,16 USD" porque es como se lee
     * una cotización en la vida real, pero guardar los pares sueltos permitiría
     * cargar combinaciones imposibles (1 USD = 1500 ARS, 1 EUR = 1736 ARS y a la vez
     * 1 USD = 0,95 EUR) y el precio de un producto saldría distinto según por qué
     * camino se convierta. Con un solo número por moneda, los pares se derivan y no
     * pueden contradecirse.
     *
     * Si 1 unidad de esta moneda equivale a `valor` unidades de la referencia, y la
     * referencia vale `cotizacion` bases, entonces esta moneda vale valor * cotizacion
     * bases. Sin referencia, el número ya viene en la escala interna.
     */
    private function normalizarCotizacion(array $validated)
    {
        $referenciaId = isset($validated['cotizacion_referencia_id'])
            ? $validated['cotizacion_referencia_id']
            : null;

        unset($validated['cotizacion_referencia_id']);

        if (!$referenciaId) {
            return $validated;
        }

        $referencia = Moneda::find($referenciaId);

        if ($referencia) {
            $validated['cotizacion'] = (float) $validated['cotizacion'] * (float) $referencia->cotizacion;
        }

        return $validated;
    }

    /**
     * Motivo por el que la moneda no se puede eliminar ni desactivar, o null si se
     * puede. El mensaje dice EN QUÉ está siendo usada: "no se puede" a secas deja al
     * usuario adivinando qué tiene que desarmar primero.
     */
    private function motivoParaNoTocar(Moneda $moneda, $accion)
    {
        if ($moneda->es_base) {
            return 'No se puede ' . $accion . ' tu moneda de referencia: es en la que están escritas todas las cotizaciones.';
        }

        $usos = $moneda->usos();

        if (!empty($usos)) {
            return 'No se puede ' . $accion . ' "' . $moneda->nombre . '": está en uso en ' . implode(', ', $usos) . '.';
        }

        return null;
    }

    private function resumenDeRecalculo($actualizados)
    {
        if ($actualizados === 0) {
            return 'No hubo precios que recalcular.';
        }

        if ($actualizados === 1) {
            return 'Se recalculó el precio de 1 producto.';
        }

        return 'Se recalcularon los precios de ' . $actualizados . ' productos.';
    }
}
