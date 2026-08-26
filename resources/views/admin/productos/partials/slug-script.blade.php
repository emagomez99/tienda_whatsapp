{{--
    JS del campo de dirección web. Compartido por create y edit.

    Con "Generar automáticamente" prendido el campo se completa solo desde el nombre
    y queda de sólo lectura (readonly, no disabled: así se puede copiar la dirección,
    y además el valor igual se envía -- el servidor lo recalcula por su cuenta, no
    depende de que el navegador lo omita).

    La vista previa la calcula el servidor (admin.productos.preview-slug) en vez de
    reimplementar Str::slug() acá: una transliteración propia diverge de la de PHP en
    los símbolos poco comunes (Ø -> o, € -> eur) y mostraría una dirección distinta de
    la que realmente se guarda.
--}}
<script>
    (function () {
        var descInput  = document.getElementById('descripcion');
        var slugInput  = document.getElementById('slug');
        var autoSwitch = document.getElementById('autogenerar_slug');
        if (!slugInput || !autoSwitch) return;

        var urlPreview = slugInput.dataset.urlPreview;
        var debounceId = null;
        var ultimaPeticion = 0;

        function pedirPreview() {
            if (!descInput || !urlPreview) return;

            // Las respuestas pueden llegar desordenadas: sólo se aplica la más reciente.
            var peticion = ++ultimaPeticion;
            var descripcion = descInput.value;

            fetch(urlPreview + '?descripcion=' + encodeURIComponent(descripcion), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || peticion !== ultimaPeticion) return;
                    if (!autoSwitch.checked) return;
                    slugInput.value = data.slug || '';
                })
                .catch(function () {
                    // Sin preview es preferible no mostrar nada a mostrar algo distinto
                    // de lo que se va a guardar. El servidor genera el slug igual.
                });
        }

        function pedirPreviewConDebounce() {
            clearTimeout(debounceId);
            debounceId = setTimeout(pedirPreview, 300);
        }

        function aplicarEstado() {
            slugInput.readOnly = autoSwitch.checked;
            slugInput.classList.toggle('bg-light', autoSwitch.checked);
            if (autoSwitch.checked) {
                pedirPreview();
            }
        }

        autoSwitch.addEventListener('change', aplicarEstado);

        if (descInput) {
            descInput.addEventListener('input', function () {
                if (autoSwitch.checked) {
                    pedirPreviewConDebounce();
                }
            });
        }

        // Filtra caracteres inválidos mientras se escribe a mano (switch apagado).
        // No es una generación de slug: sólo descarta lo que el servidor rechazaría
        // con la misma regla (regex /^[a-z0-9-]+$/), así que no puede divergir.
        slugInput.addEventListener('input', function () {
            if (this.readOnly) return;
            var pos = this.selectionStart;
            this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
            this.setSelectionRange(pos, pos);
        });

        aplicarEstado();
    })();
</script>
