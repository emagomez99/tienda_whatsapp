{{--
    Partial: intl-tel-input
    Parámetros:
      $inputId   — id del input visible (default: 'telefono-input')
      $fieldName — name del hidden input que se envía al servidor (default: 'telefono')
      $value     — valor actual (para edición o old input)
      $required  — bool, si el campo es obligatorio (default: false)
      $label     — texto del label (default: 'Teléfono')
--}}
@php
    $inputId   = $inputId   ?? 'telefono-input';
    $fieldName = $fieldName ?? 'telefono';
    $value     = $value     ?? '';
    $required  = $required  ?? false;
    $label     = $label     ?? 'Teléfono';
@endphp

<label for="{{ $inputId }}" class="form-label">{{ $label }}{{ $required ? ' *' : '' }}</label>
<input type="tel"
       id="{{ $inputId }}"
       class="form-control @error($fieldName) is-invalid @enderror"
       autocomplete="tel"
       {{ $required ? 'required' : '' }}>
<input type="hidden" name="{{ $fieldName }}" id="{{ $inputId }}-hidden" value="{{ old($fieldName, $value) }}">
@error($fieldName)
    <div class="invalid-feedback d-block">{{ $message }}</div>
@enderror
<div class="invalid-feedback" id="{{ $inputId }}-error"></div>

@push('styles')
@once
<link rel="stylesheet" href="{{ asset('vendor/intl-tel-input/intlTelInput.css') }}">
<style>
    .iti {
        --iti-path-flags-1x: url('{{ asset('vendor/intl-tel-input/flags.webp') }}');
        --iti-path-flags-2x: url('{{ asset('vendor/intl-tel-input/flags@2x.webp') }}');
        width: 100%;
    }
</style>
@endonce
@endpush

@push('scripts')
@once
<script src="{{ asset('vendor/intl-tel-input/intlTelInput.min.js') }}"></script>
@endonce
<script>
(function () {
    var input    = document.getElementById('{{ $inputId }}');
    var hidden   = document.getElementById('{{ $inputId }}-hidden');
    var errorEl  = document.getElementById('{{ $inputId }}-error');
    var required = {{ $required ? 'true' : 'false' }};

    var iti = window.intlTelInput(input, {
        initialCountry: 'ar',
        loadUtils: function () { return import('{{ asset('vendor/intl-tel-input/utils.js') }}'); },
    });

    if (hidden.value) {
        iti.setNumber(hidden.value);
    }

    input.closest('form').addEventListener('submit', function (e) {
        errorEl.textContent = '';
        input.classList.remove('is-invalid');

        var numero = input.value.trim();

        if (!numero && !required) {
            hidden.value = '';
            return;
        }

        if (!iti.isValidNumber()) {
            e.preventDefault();
            input.classList.add('is-invalid');
            errorEl.textContent = 'Ingresá un número de teléfono válido.';
            input.focus();
            return;
        }

        hidden.value = iti.getNumber();
    });
})();
</script>
@endpush
