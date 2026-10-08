{{--
    Botones para elegir la variante en la ficha (Talle: S M L · Color: Rojo Azul).
    Va en la ficha de escritorio y en la del celular: el script de tienda/show los
    mantiene iguales y le pasa la variante elegida a los formularios de agregar.
    Recibe $selector (App\Support\SelectorDeVariantes).
--}}
<div class="selector-variantes mb-3">
    @foreach($selector->ejes as $eje)
        <div class="mb-2" data-eje="{{ $eje['id'] }}">
            <div class="ficha-rotulo mb-1">
                {{ $eje['nombre'] }}: <span class="fw-normal text-muted variante-elegida" data-eje="{{ $eje['id'] }}">elegí una opción</span>
            </div>
            <div class="d-flex flex-wrap gap-2" role="group" aria-label="{{ $eje['nombre'] }}">
                @foreach($eje['opciones'] as $opcion)
                    <button type="button" class="btn btn-outline-secondary opcion-variante"
                            data-eje="{{ $eje['id'] }}" data-valor="{{ $opcion['id'] }}" aria-pressed="false">
                        {{ $opcion['valor'] }}
                    </button>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
