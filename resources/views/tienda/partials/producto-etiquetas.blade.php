{{-- Etiquetas visibles de la ficha de producto (desktop y mobile). $etiquetasVisibles lo define tienda/show. --}}
@if($etiquetasVisibles->isNotEmpty())
    <div class="pdp-tags">
        @foreach($etiquetasVisibles as $etiqueta)
            <span class="chip"><span class="chip-k">{{ $etiqueta->nombre }}</span> {{ $etiqueta->pivot->valor }}</span>
        @endforeach
    </div>
@endif
