{{--
    JS de la dirección web. Compartido por create y edit.

    Dos modos:
      - automática (por defecto): se muestra una línea de sólo lectura que se
        actualiza a medida que se escribe el nombre.
      - manual: al tocar "Editar" aparece el campo y se apaga la generación
        automática (el input oculto autogenerar_slug pasa a 0).

    La vista previa la calcula el servidor (admin.productos.preview-slug) en vez de
    reimplementar Str::slug() acá: una transliteración propia diverge de la de PHP en
    los símbolos poco comunes (Ø -> o, € -> eur) y mostraría una dirección distinta de
    la que realmente se guarda.
--}}
<script>
    (function () {
        var campo = document.getElementById('slug-campo');
        if (!campo) return;

        var descInput = document.getElementById('descripcion');
        var input     = document.getElementById('slug');
        var oculto    = document.getElementById('autogenerar_slug');
        var vista     = document.getElementById('slug-vista');
        var edicion   = document.getElementById('slug-edicion');
        var preview   = document.getElementById('slug-preview');
        var btnEditar = document.getElementById('slug-editar');
        var btnAuto   = document.getElementById('slug-auto');

        var urlPreview     = input.dataset.urlPreview;
        var debounceId     = null;
        var ultimaPeticion = 0;

        function esAutomatica() {
            return oculto.value === '1';
        }

        function pedirPreview() {
            if (!descInput || !urlPreview || !esAutomatica()) return;

            // Las respuestas pueden llegar desordenadas: sólo se aplica la más reciente.
            var peticion = ++ultimaPeticion;

            fetch(urlPreview + '?descripcion=' + encodeURIComponent(descInput.value), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || peticion !== ultimaPeticion || !esAutomatica()) return;
                    preview.textContent = data.slug || '';
                    input.value         = data.slug || '';
                })
                .catch(function () {
                    // Sin preview es preferible no mostrar nada a mostrar algo distinto
                    // de lo que se va a guardar. El servidor genera el slug igual.
                });
        }

        if (descInput) {
            descInput.addEventListener('input', function () {
                if (!esAutomatica()) return;
                clearTimeout(debounceId);
                debounceId = setTimeout(pedirPreview, 300);
            });
        }

        // Al ocultar el botón su tooltip queda flotando en pantalla, así que se cierra
        // a mano antes de cambiar de vista.
        function cerrarTooltip(boton) {
            if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
            var t = bootstrap.Tooltip.getInstance(boton);
            if (t) t.hide();
        }

        btnEditar.addEventListener('click', function () {
            cerrarTooltip(btnEditar);
            oculto.value = '0';
            vista.classList.add('d-none');
            edicion.classList.remove('d-none');
            input.focus();
            input.select();
        });

        btnAuto.addEventListener('click', function () {
            cerrarTooltip(btnAuto);
            oculto.value = '1';
            edicion.classList.add('d-none');
            vista.classList.remove('d-none');
            pedirPreview();
        });

        // Filtra caracteres inválidos mientras se escribe a mano. No genera el slug:
        // sólo descarta lo que el servidor rechazaría con la misma regla
        // (regex /^[a-z0-9-]+$/), así que no puede divergir.
        input.addEventListener('input', function () {
            var pos = this.selectionStart;
            this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
            this.setSelectionRange(pos, pos);
            preview.textContent = this.value;
        });
    })();
</script>
