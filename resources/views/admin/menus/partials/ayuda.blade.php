{{--
    "Cómo funciona" del árbol de menús: se abre desde el encabezado y recuerda si se
    dejó abierto (en este navegador). Dos columnas: qué muestra cada tipo de menú y
    cómo se ordena el árbol.
--}}
<div class="collapse border-bottom" id="ayuda-menus">
    <div class="ayuda-menus">
        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="ayuda-titulo">Qué muestra cada menú</h6>
                <div class="ayuda-item">
                    <span class="ayuda-icono"><i class="bi bi-folder2"></i></span>
                    <div><strong>Agrupa</strong><br>No muestra productos: al tocarlo se despliegan sus submenús.</div>
                </div>
                <div class="ayuda-item">
                    <span class="ayuda-icono"><i class="bi bi-tag"></i></span>
                    <div><strong>Etiqueta</strong><br>Los productos con esa etiqueta, y con ese valor si se eligió uno. Ej: Marca = Asus.</div>
                </div>
                <div class="ayuda-item">
                    <span class="ayuda-icono"><i class="bi bi-truck"></i></span>
                    <div><strong>Proveedor</strong><br>Todos los productos de ese proveedor.</div>
                </div>
            </div>
            <div class="col-md-6">
                <h6 class="ayuda-titulo">Cómo se arma</h6>
                <div class="ayuda-item">
                    <span class="ayuda-icono"><i class="bi bi-grip-vertical"></i></span>
                    <div><strong>Ordenar</strong><br>Arrastrá desde <i class="bi bi-grip-vertical"></i> hacia arriba o hacia abajo.</div>
                </div>
                <div class="ayuda-item">
                    <span class="ayuda-icono"><i class="bi bi-arrow-return-right"></i></span>
                    <div><strong>Meter uno dentro de otro</strong><br>Arrastralo sobre el otro menú y corré el mouse hacia la derecha, hasta su nombre: la fila se resalta y al soltar queda adentro. Para crear uno nuevo adentro, <i class="bi bi-plus-lg"></i> en la fila.</div>
                </div>
                <div class="ayuda-item">
                    <span class="ayuda-icono"><i class="bi bi-diagram-2"></i></span>
                    <div><strong>Los submenús heredan</strong><br>Suman lo que filtra el de arriba: «Notebook › Asus» son las notebooks Asus, no todo lo de Asus.</div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .ayuda-menus { padding: 1.25rem 1.25rem .5rem; background: #fbfbfc; }
    .ayuda-titulo { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; margin-bottom: .75rem; }
    .ayuda-item { display: flex; gap: .75rem; align-items: flex-start; margin-bottom: .9rem; font-size: .875rem; color: #495057; line-height: 1.4; }
    .ayuda-item strong { color: #212529; font-weight: 600; }
    .ayuda-icono {
        flex-shrink: 0; width: 2rem; height: 2rem; border-radius: .5rem;
        display: inline-flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--admin-color) 22%, white); color: #212529;
    }
    #boton-ayuda-menus[aria-expanded="true"] { font-weight: 600; }
</style>
@endpush

@push('scripts')
<script>
    // Recordar si quedó abierta. Es una comodidad de este navegador: si el
    // almacenamiento no está disponible, simplemente arranca cerrada.
    (function () {
        var panel = document.getElementById('ayuda-menus');
        if (!panel) return;
        try {
            if (localStorage.getItem('ayuda-menus-abierta') === '1') {
                panel.classList.add('show');
                document.getElementById('boton-ayuda-menus').setAttribute('aria-expanded', 'true');
            }
            panel.addEventListener('shown.bs.collapse', function () { localStorage.setItem('ayuda-menus-abierta', '1'); });
            panel.addEventListener('hidden.bs.collapse', function () { localStorage.removeItem('ayuda-menus-abierta'); });
        } catch (e) {}
    })();
</script>
@endpush
