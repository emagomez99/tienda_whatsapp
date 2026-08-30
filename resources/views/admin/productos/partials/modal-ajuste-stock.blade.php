{{--
    Modal de ajuste de stock. Compartido por edit y historial.

    $redirect indica a dónde volver después de guardar ('historial' o vacío para edit).

    Se elige ingreso o egreso con dos botones y la cantidad se carga siempre en
    positivo. Antes se pedía escribir el número con signo ("-5 para salida"), que
    obligaba al usuario a traducir una intención a una convención de signos y hacía
    fácil equivocarse justo en la operación que descuenta mercadería.
--}}
@php $redirectAjuste = isset($redirect) ? $redirect : ''; @endphp

<div class="modal fade" id="modal-ajuste-stock" tabindex="-1" aria-labelledby="modal-ajuste-stock-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.productos.ajustar-stock', $producto) }}" method="POST">
                @csrf
                @if($redirectAjuste)
                    <input type="hidden" name="_redirect" value="{{ $redirectAjuste }}">
                @endif

                <div class="modal-header">
                    <h5 class="modal-title" id="modal-ajuste-stock-label">
                        <i class="bi bi-arrow-left-right"></i> Ajustar stock
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    @php
                        // MessageBag::only() no existe en Laravel 8; se juntan con get().
                        $erroresAjuste = [];
                        foreach (['tipo', 'cantidad', 'descripcion'] as $campo) {
                            foreach ($errors->get($campo) as $mensaje) {
                                $erroresAjuste[] = $mensaje;
                            }
                        }
                    @endphp
                    @if($erroresAjuste)
                        <div class="alert alert-danger py-2 mb-3">
                            <ul class="mb-0 ps-3">
                                @foreach($erroresAjuste as $mensaje)
                                    <li>{{ $mensaje }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="alert alert-secondary py-2 mb-3">
                        Stock actual: <strong>{{ $producto->stock }}</strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold d-block">Tipo de movimiento *</label>
                        <div class="btn-group w-100" role="group" aria-label="Tipo de movimiento">
                            <input type="radio" class="btn-check" name="tipo" id="ajuste-tipo-ingreso"
                                   value="ingreso" autocomplete="off"
                                   {{ old('tipo', 'ingreso') === 'ingreso' ? 'checked' : '' }}>
                            <label class="btn btn-outline-success" for="ajuste-tipo-ingreso">
                                <i class="bi bi-box-arrow-in-down"></i> Ingreso
                            </label>

                            <input type="radio" class="btn-check" name="tipo" id="ajuste-tipo-egreso"
                                   value="egreso" autocomplete="off"
                                   {{ old('tipo') === 'egreso' ? 'checked' : '' }}>
                            <label class="btn btn-outline-danger" for="ajuste-tipo-egreso">
                                <i class="bi bi-box-arrow-up"></i> Egreso
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="ajuste-cantidad" class="form-label fw-semibold">Cantidad *</label>
                        <input type="number" name="cantidad" id="ajuste-cantidad" class="form-control"
                               min="1" step="1" value="{{ old('cantidad') }}"
                               placeholder="Ej: 10" required>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted">Stock resultante:</span>
                            <strong id="stock-resultante-preview">{{ $producto->stock }}</strong>
                        </div>
                    </div>

                    <div class="mb-1">
                        <label for="ajuste-descripcion" class="form-label fw-semibold">Motivo *</label>
                        <input type="text" name="descripcion" id="ajuste-descripcion"
                               class="form-control @error('descripcion') is-invalid @enderror"
                               value="{{ old('descripcion') }}" maxlength="255" required
                               placeholder="Ej: Recepción de mercadería, corrección de inventario">
                        <small class="text-muted">Queda registrado en el historial del producto.</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Confirmar ajuste
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        var stockBase = {{ (int) $producto->stock }};
        var cantidad  = document.getElementById('ajuste-cantidad');
        var preview   = document.getElementById('stock-resultante-preview');
        var tipos     = document.querySelectorAll('input[name="tipo"]');
        if (!cantidad || !preview) return;

        function actualizarPreview() {
            var esEgreso   = document.getElementById('ajuste-tipo-egreso').checked;
            var valor      = parseInt(cantidad.value, 10) || 0;
            var resultante = stockBase + (esEgreso ? -valor : valor);

            preview.textContent = resultante;
            preview.style.color = resultante < 0 ? '#dc3545' : '';
            preview.style.fontWeight = 'bold';
        }

        cantidad.addEventListener('input', actualizarPreview);
        Array.prototype.forEach.call(tipos, function (radio) {
            radio.addEventListener('change', actualizarPreview);
        });

        @if($errors->hasAny(['cantidad', 'tipo', 'descripcion']))
            new bootstrap.Modal(document.getElementById('modal-ajuste-stock')).show();
        @endif
    })();
</script>
@endpush
