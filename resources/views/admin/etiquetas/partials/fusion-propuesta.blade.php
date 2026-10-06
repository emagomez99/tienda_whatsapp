{{--
    Aparece cuando se renombró un valor a uno que ya existe (EtiquetaValorController
    @update). Unir mueve productos y no se puede deshacer, así que se pide acá en vez
    de hacerlo solo.
--}}
@if(session('fusion_propuesta'))
    @php
        $origen  = \App\Models\EtiquetaValor::withCount('asignaciones')->with('etiqueta')->find(session('fusion_propuesta.origen_id'));
        $destino = \App\Models\EtiquetaValor::withCount('asignaciones')->find(session('fusion_propuesta.destino_id'));
    @endphp
    @if($origen && $destino)
        <div class="alert alert-warning d-flex flex-column flex-md-row align-items-md-center gap-3">
            <div class="flex-grow-1">
                <strong><i class="bi bi-intersect"></i> Ya existe «{{ $destino->valor }}» en {{ $origen->etiqueta->nombre }}.</strong><br>
                ¿Unir «{{ $origen->valor }}» ({{ $origen->asignaciones_count }} {{ $origen->asignaciones_count === 1 ? 'producto' : 'productos' }})
                con «{{ $destino->valor }}» ({{ $destino->asignaciones_count }} {{ $destino->asignaciones_count === 1 ? 'producto' : 'productos' }})?
                <div class="small text-muted mt-1">Los productos y menús de «{{ $origen->valor }}» pasan a «{{ $destino->valor }}». No se puede deshacer.</div>
            </div>
            <div class="d-flex gap-2">
                <form action="{{ route('admin.etiqueta-valores.fusionar', $origen) }}" method="POST" class="m-0">
                    @csrf
                    <input type="hidden" name="destino_id" value="{{ $destino->id }}">
                    <button type="submit" class="btn btn-warning">Sí, unir</button>
                </form>
                <a href="{{ url()->current() . (request()->getQueryString() ? '?' . request()->getQueryString() : '') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    @endif
@endif
