{{--
    Campo de texto con desplegable de valores ya usados. Reemplaza a los tres
    autocompletados que había duplicados en el alta y la edición (etiquetas,
    claves de especificación y valores de especificación): seis copias de la misma
    lógica, todas con la misma regla de exigir tres caracteres antes de sugerir
    nada -- justo lo que impedía mirar qué valores existen.

    Cómo se usa: al input se le pone data-combo con la URL de las sugerencias.

        <input class="form-control" data-combo="{{ route('...') }}">

    Opcionales, para las URLs que dependen de otro campo de la misma fila:
        data-combo-param="etiqueta_id"   nombre del parámetro extra en la query
        data-combo-desde=".etiqueta-select"  selector (dentro de la fila) de donde sale
        data-combo-url-con  plantilla de URL con __ID__ a reemplazar
        data-combo-fila     selector del contenedor que hace de "fila" (por defecto, el padre)

    Funciona con teclado (flechas, Enter, Escape) y muestra "Usar ..." cuando lo
    escrito no está en la lista, para que crear un valor nuevo sea una acción
    visible y no el efecto de que no aparezcan sugerencias.
--}}
<style>
    .combo-lista {
        position: absolute; z-index: 1050; width: 100%;
        max-height: 260px; overflow-y: auto;
        background: #fff; border: 1px solid #dee2e6; border-radius: .375rem;
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.1);
    }
    .combo-lista .combo-item { padding: .45rem .75rem; cursor: pointer; font-size: .9rem; }
    .combo-lista .combo-item:hover,
    .combo-lista .combo-item.activo { background: #e8f0fe; }
    .combo-lista .combo-nuevo { border-top: 1px solid #eee; color: #0d6efd; }
    .combo-lista .combo-aviso { padding: .4rem .75rem; font-size: .8rem; color: #6c757d; }
    .combo-envoltorio { position: relative; }
</style>

<script>
(function () {
    var TOPE = {{ \App\Http\Controllers\Admin\ProductoController::MAX_SUGERENCIAS }};

    function iniciar(input) {
        if (input.dataset.comboListo === '1') return;
        input.dataset.comboListo = '1';
        input.setAttribute('autocomplete', 'off');

        // El desplegable se posiciona respecto de un contenedor relativo.
        var envoltorio = input.parentElement;
        if (!envoltorio.classList.contains('combo-envoltorio')) {
            envoltorio.classList.add('combo-envoltorio');
        }

        var lista = document.createElement('div');
        lista.className = 'combo-lista d-none';
        envoltorio.appendChild(lista);

        var temporizador = null, secuencia = 0, indiceActivo = -1, opciones = [];

        function url(consulta) {
            var base = input.dataset.combo;

            // URLs que dependen de otro campo de la fila (ej. la etiqueta elegida).
            if (input.dataset.comboUrlCon) {
                var fila = input.closest(input.dataset.comboFila || '.row') || input.parentElement;
                var origen = fila.querySelector(input.dataset.comboDesde);
                if (!origen || !origen.value) return null;
                base = input.dataset.comboUrlCon.replace('__ID__', encodeURIComponent(origen.value));
            }

            var params = '?q=' + encodeURIComponent(consulta);

            // Parámetro extra tomado de otro campo (ej. la clave de la especificación).
            if (input.dataset.comboParam && input.dataset.comboDesde) {
                var fila2 = input.closest(input.dataset.comboFila || '.row') || input.parentElement;
                var campo = fila2.querySelector(input.dataset.comboDesde);
                if (campo && campo.value) {
                    params += '&' + input.dataset.comboParam + '=' + encodeURIComponent(campo.value);
                }
            }

            return base + params;
        }

        function cerrar() {
            lista.classList.add('d-none');
            indiceActivo = -1;
        }

        function elegir(valor) {
            input.value = valor;
            cerrar();
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function pintar(valores, escrito) {
            lista.innerHTML = '';
            opciones = [];

            valores.forEach(function (v) {
                var item = document.createElement('div');
                item.className = 'combo-item';
                item.textContent = v;
                item.addEventListener('mousedown', function (e) { e.preventDefault(); elegir(v); });
                lista.appendChild(item);
                opciones.push({ el: item, valor: v });
            });

            // Crear un valor nuevo, como acción explícita.
            var existe = valores.some(function (v) { return v.toLowerCase() === escrito.toLowerCase(); });
            if (escrito !== '' && !existe) {
                var nuevo = document.createElement('div');
                nuevo.className = 'combo-item combo-nuevo';
                nuevo.innerHTML = '<i class="bi bi-plus-circle"></i> Crear «' + escrito.replace(/</g, '&lt;') + '»';
                nuevo.addEventListener('mousedown', function (e) { e.preventDefault(); elegir(escrito); });
                lista.appendChild(nuevo);
                opciones.push({ el: nuevo, valor: escrito });
            }

            if (valores.length >= TOPE) {
                var aviso = document.createElement('div');
                aviso.className = 'combo-aviso';
                aviso.textContent = 'Hay más valores: seguí escribiendo para filtrar.';
                lista.appendChild(aviso);
            }

            if (!lista.children.length) { cerrar(); return; }
            lista.classList.remove('d-none');
        }

        function buscar() {
            var destino = url(input.value.trim());
            if (!destino) { cerrar(); return; }

            var mia = ++secuencia;
            fetch(destino, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (datos) {
                    if (mia !== secuencia) return;   // respuesta vieja
                    pintar(Array.isArray(datos) ? datos : [], input.value.trim());
                })
                .catch(function () { cerrar(); });
        }

        function buscarConEspera() {
            clearTimeout(temporizador);
            temporizador = setTimeout(buscar, 250);
        }

        // Al enfocar se abre mostrando lo que hay, sin exigir que se escriba nada:
        // ese era el problema del componente anterior.
        input.addEventListener('focus', buscar);
        input.addEventListener('input', buscarConEspera);

        input.addEventListener('keydown', function (e) {
            if (lista.classList.contains('d-none')) return;

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!opciones.length) return;
                if (indiceActivo >= 0) opciones[indiceActivo].el.classList.remove('activo');
                indiceActivo = e.key === 'ArrowDown'
                    ? (indiceActivo + 1) % opciones.length
                    : (indiceActivo <= 0 ? opciones.length - 1 : indiceActivo - 1);
                opciones[indiceActivo].el.classList.add('activo');
                opciones[indiceActivo].el.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && indiceActivo >= 0) {
                e.preventDefault();
                elegir(opciones[indiceActivo].valor);
            } else if (e.key === 'Escape') {
                cerrar();
            }
        });

        input.addEventListener('blur', function () {
            setTimeout(cerrar, 150);   // da tiempo al clic sobre la lista
        });
    }

    function iniciarTodos(raiz) {
        (raiz || document).querySelectorAll('[data-combo]').forEach(iniciar);
    }

    iniciarTodos();

    // Las filas de etiquetas y especificaciones se agregan dinámicamente.
    new MutationObserver(function (mutaciones) {
        mutaciones.forEach(function (m) {
            m.addedNodes.forEach(function (n) {
                if (n.nodeType !== 1) return;
                if (n.matches && n.matches('[data-combo]')) iniciar(n);
                iniciarTodos(n);
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
})();
</script>
