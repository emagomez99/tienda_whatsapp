{{-- Especificaciones del producto. $dosColumnas: repartir en dos columnas en pantallas anchas. --}}
<dl class="spec-list{{ ($dosColumnas ?? false) ? ' spec-list-2col' : '' }}">
    @foreach($producto->especificaciones as $espec)
        <div>
            <dt>{{ $espec->clave }}</dt>
            <dd>{{ $espec->valor }}</dd>
        </div>
    @endforeach
</dl>
