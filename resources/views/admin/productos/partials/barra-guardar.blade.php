{{--
    Barra fija al pie del formulario de producto, con el mismo criterio que la de
    Ajustes: los botones quedan siempre a mano, sin tener que subir a buscarlos.

    Parámetros:
      $textoGuardar — texto del botón principal
      $cancelarUrl  — adónde vuelve "Cancelar"
--}}
<div class="position-sticky bottom-0 bg-white border-top py-2 mt-3" style="z-index: 5;">
    <div class="d-flex justify-content-end align-items-center gap-2">
        <a href="{{ $cancelarUrl }}" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check2"></i> {{ $textoGuardar }}
        </button>
    </div>
</div>
