{{-- Una especificación. $i es el índice del array, o __I__ en la plantilla. --}}
<div class="row g-2 mb-2 especificacion-row">
    <div class="col-md-5 position-relative">
        <input type="text" class="form-control especificacion-clave" name="especificaciones[{{ $i }}][clave]" value="{{ $clave }}"
               placeholder="Clave (ej: Peso)" maxlength="255" autocomplete="off"
               data-combo="{{ route('admin.especificaciones.claves') }}">
    </div>
    <div class="col-md-5 position-relative">
        <input type="text" class="form-control especificacion-valor" name="especificaciones[{{ $i }}][valor]" value="{{ $valor }}"
               placeholder="Valor (ej: 1.75 kg)" maxlength="255" autocomplete="off"
               data-combo="{{ route('admin.especificaciones.valores') }}" data-combo-fila=".especificacion-row"
               data-combo-param="clave" data-combo-desde=".especificacion-clave">
    </div>
    <div class="col-md-2">
        <button type="button" class="btn btn-outline-danger btn-eliminar-especificacion" title="Quitar">
            <i class="bi bi-trash"></i>
        </button>
    </div>
    <div class="col-12 especificacion-aviso small text-warning-emphasis"></div>
</div>
