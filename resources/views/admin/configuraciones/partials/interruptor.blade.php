{{--
    Ajuste de sí/no como tarjeta: título y ayuda a la izquierda, switch a la derecha.
    Toda la tarjeta es clickeable (es un <label>).

    Parámetros:
      $nombre — name e id del campo; se envía "true" o "false"
      $titulo — qué hace el ajuste
      $ayuda  — una línea opcional que explica la consecuencia
      $activo — bool, valor guardado (old() tiene prioridad)
--}}
@php $marcado = old($nombre, ($activo ?? false) ? 'true' : 'false') === 'true'; @endphp
<label class="ajuste-tarjeta ajuste-interruptor d-flex justify-content-between align-items-start gap-3 mb-0 h-100" for="{{ $nombre }}">
    <span>
        <span class="fw-semibold d-block">{{ $titulo }}</span>
        @if(!empty($ayuda))
            <span class="small text-muted">{{ $ayuda }}</span>
        @endif
    </span>
    <span class="form-check form-switch m-0 flex-shrink-0">
        <input type="hidden" name="{{ $nombre }}" value="false">
        <input class="form-check-input" type="checkbox" role="switch"
               id="{{ $nombre }}" name="{{ $nombre }}" value="true" {{ $marcado ? 'checked' : '' }}>
    </span>
</label>
