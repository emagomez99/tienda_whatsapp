{{--
    Imágenes del producto como una sola galería: la primera es la principal y las
    demás van al carrusel de la ficha. Reemplaza a las tarjetas "Imagen principal" e
    "Imágenes adicionales", que eran dos interfaces distintas para lo mismo.

    Se reordena arrastrando (o con ★, que pasa una al primer lugar) y se envía la
    lista entera, en orden, como fichas: ver App\Services\GaleriaProducto.

    Lo que respeta de Ajustes:
      - modo de imagen: sólo URL, sólo archivo o ambos;
      - imágenes adicionales activas o no, y cuántas como máximo.

    Usa $producto si existe (edición).
--}}
@php
    $maximo      = App\Services\GaleriaProducto::maximo();
    $modoImagen  = App\Models\Configuracion::modoImagenProducto();
    $permiteUrl  = $modoImagen !== 'solo_archivo';
    $permiteArch = $modoImagen !== 'solo_url';

    // Lo que hay guardado, con la ficha que lo identifica.
    $guardadas = [];
    if (isset($producto)) {
        if ($producto->url_imagen) {
            $guardadas['principal'] = ['src' => $producto->imagen_url, 'externa' => $producto->esImagenExterna()];
        }
        foreach ($producto->imagenes as $img) {
            $guardadas['id:' . $img->id] = ['src' => $img->imagen_url, 'externa' => $img->esExterna()];
        }
    }

    // Tras un error de validación se respeta el orden enviado. Los archivos no se
    // pueden recuperar: el navegador no deja volver a ponerlos en el campo.
    $iniciales = [];
    $archivosPerdidos = false;
    $fichas = session()->hasOldInput() && old('galeria_enviada') ? (array) old('galeria', []) : array_keys($guardadas);
    foreach ($fichas as $ficha) {
        if (isset($guardadas[$ficha])) {
            $iniciales[] = ['ficha' => $ficha] + $guardadas[$ficha];
        } elseif (stripos($ficha, 'url:') === 0) {
            $iniciales[] = ['ficha' => $ficha, 'src' => substr($ficha, 4), 'externa' => true];
        } elseif (stripos($ficha, 'archivo:') === 0) {
            $archivosPerdidos = true;
        }
    }
@endphp
<div class="card mb-4" id="card-galeria">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-images"></i> Imágenes
            @include('admin.productos.partials.ayuda', ['texto' => __('productos.ayuda.galeria'), 'grande' => true])
        </h5>
        <small class="text-muted"><span id="galeria-cuenta">0</span> de {{ $maximo }}</small>
    </div>
    <div class="card-body">
        @if($archivosPerdidos)
            <div class="alert alert-warning py-2 small">
                <i class="bi bi-exclamation-triangle"></i> Las imágenes que habías subido desde tu equipo no se guardaron por el error de arriba: volvé a elegirlas.
            </div>
        @endif
        @error('galeria')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
        @error('galeria.*')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
        @error('galeria_archivos.*')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

        <div class="galeria" id="galeria-items"></div>

        <div class="galeria-agregar mt-3" id="galeria-agregar">
            <div class="row g-2 align-items-start">
                @if($permiteUrl)
                    <div class="{{ $permiteArch ? 'col-md-7' : 'col-12' }}">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                            <input type="url" class="form-control" id="galeria-url" placeholder="Pegá la URL de una imagen (https://...)">
                            <button type="button" class="btn btn-outline-primary" id="galeria-agregar-url">Agregar</button>
                        </div>
                        <small class="text-danger d-none" id="galeria-url-error">Tiene que empezar con http:// o https://</small>
                    </div>
                @endif
                @if($permiteArch)
                    <div class="{{ $permiteUrl ? 'col-md-5' : 'col-12' }}">
                        <label class="btn btn-outline-primary w-100 mb-0">
                            <i class="bi bi-upload"></i> Subir desde el equipo
                            <input type="file" id="galeria-elegir" accept="image/*" {{ $maximo > 1 ? 'multiple' : '' }} hidden>
                        </label>
                        <small class="text-muted d-block text-center">JPG, PNG o WEBP · hasta 2 MB cada una</small>
                    </div>
                @endif
            </div>
        </div>
        <div class="alert alert-secondary py-2 small mt-3 mb-0 d-none" id="galeria-lleno">
            <i class="bi bi-info-circle"></i> Llegaste al máximo de {{ $maximo }} {{ $maximo === 1 ? 'imagen' : 'imágenes' }}. Quitá una para agregar otra.
        </div>

        {{-- Lo que se envía: la lista en orden y los archivos nuevos. --}}
        <input type="hidden" name="galeria_enviada" value="1">
        <div id="galeria-fichas"></div>
        <input type="file" name="galeria_archivos[]" id="galeria-archivos" multiple hidden>
    </div>
</div>

@push('styles')
<style>
    .galeria { display: flex; flex-wrap: wrap; gap: .75rem; min-height: 1rem; }
    .galeria-item {
        position: relative; width: 132px; border: 1px solid #dee2e6; border-radius: .5rem;
        background: #fff; cursor: grab; transition: box-shadow .15s, opacity .15s;
    }
    .galeria-item.arrastrando { opacity: .4; }
    .galeria-item.destino { box-shadow: 0 0 0 3px rgba(13,110,253,.35); }
    .galeria-item .galeria-img { position: relative; height: 100px; display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: .5rem .5rem 0 0; background: #f8f9fa; }
    .galeria-item img { max-width: 100%; max-height: 100%; object-fit: contain; pointer-events: none; }
    .galeria-item .galeria-origen { position: absolute; right: .25rem; bottom: .25rem; font-size: .65rem; background: rgba(255,255,255,.9); color: #6c757d; border-radius: .25rem; padding: 0 .25rem; }
    /* Botones de 30px: se tocan con el dedo. En pantallas táctiles no hay arrastrar,
       así que estos son la única forma de ordenar. */
    .galeria-item .galeria-acciones { display: flex; justify-content: space-between; border-top: 1px solid #eee; }
    .galeria-item .galeria-acciones button { flex: 1; min-height: 30px; border: 0; background: none; color: #6c757d; font-size: .9rem; line-height: 1; }
    .galeria-item .galeria-acciones button:hover { color: #212529; background: #f8f9fa; }
    .galeria-item .galeria-principal { position: absolute; top: .35rem; left: .35rem; }
    .galeria-item:first-child { border-color: #ffc107; }
    .galeria-vacia { color: #6c757d; font-size: .9rem; padding: 1rem 0; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var MAXIMO   = {{ $maximo }};
    var INICIALES = @json($iniciales);
    var SIN_IMAGEN = '/img/no-image.svg';

    var lista    = document.getElementById('galeria-items');
    var fichas   = document.getElementById('galeria-fichas');
    var campoArchivos = document.getElementById('galeria-archivos');
    var inputUrl = document.getElementById('galeria-url');
    var elegir   = document.getElementById('galeria-elegir');

    // Cada imagen: {ficha, src, externa} y, si es nueva desde el equipo, {archivo: File}.
    var imagenes = INICIALES.slice();

    function avisarCambio() {
        // La vista previa y el aviso de cambios escuchan eventos del formulario.
        lista.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function pintar() {
        lista.innerHTML = '';

        if (!imagenes.length) {
            var vacia = document.createElement('div');
            vacia.className = 'galeria-vacia';
            vacia.innerHTML = '<i class="bi bi-image"></i> Todavía no tiene imágenes. La primera que agregues va a ser la principal.';
            lista.appendChild(vacia);
        }

        imagenes.forEach(function (imagen, i) {
            var item = document.createElement('div');
            item.className = 'galeria-item';
            item.draggable = true;
            item.dataset.indice = i;

            var marco = document.createElement('div');
            marco.className = 'galeria-img';
            var img = document.createElement('img');
            img.src = imagen.src;
            img.alt = '';
            img.onerror = function () { this.onerror = null; this.src = SIN_IMAGEN; };
            marco.appendChild(img);
            item.appendChild(marco);

            if (i === 0) {
                var badge = document.createElement('span');
                badge.className = 'badge bg-warning text-dark galeria-principal';
                badge.innerHTML = '<i class="bi bi-star-fill"></i> Principal';
                item.appendChild(badge);
            }

            var origen = document.createElement('span');
            origen.className = 'galeria-origen';
            origen.innerHTML = imagen.archivo
                ? '<i class="bi bi-upload"></i> Nueva'
                : (imagen.externa ? '<i class="bi bi-link-45deg"></i> URL' : '<i class="bi bi-hdd"></i> Subida');
            marco.appendChild(origen);

            var acciones = document.createElement('div');
            acciones.className = 'galeria-acciones';
            if (i > 0) acciones.appendChild(boton('bi-star', 'Usar como principal', function () { mover(i, 0); }));
            if (i > 0) acciones.appendChild(boton('bi-chevron-left', 'Mover a la izquierda', function () { mover(i, i - 1); }));
            if (i < imagenes.length - 1) acciones.appendChild(boton('bi-chevron-right', 'Mover a la derecha', function () { mover(i, i + 1); }));
            acciones.appendChild(boton('bi-x-lg text-danger', 'Quitar', function () { imagenes.splice(i, 1); actualizar(); }));
            item.appendChild(acciones);

            lista.appendChild(item);
        });

        var lleno = imagenes.length >= MAXIMO;
        document.getElementById('galeria-cuenta').textContent = imagenes.length;
        document.getElementById('galeria-agregar').classList.toggle('d-none', lleno);
        document.getElementById('galeria-lleno').classList.toggle('d-none', !lleno || MAXIMO <= 1);
    }

    function boton(icono, titulo, accion) {
        var b = document.createElement('button');
        b.type = 'button';
        b.title = titulo;
        b.setAttribute('aria-label', titulo);
        b.innerHTML = '<i class="bi ' + icono + '"></i>';
        b.addEventListener('click', accion);
        return b;
    }

    function mover(desde, hasta) {
        var imagen = imagenes.splice(desde, 1)[0];
        imagenes.splice(hasta, 0, imagen);
        actualizar();
    }

    /** Arma lo que se envía: las fichas en orden y el campo de archivos. */
    function volcar() {
        fichas.innerHTML = '';
        var transferencia = new DataTransfer();

        imagenes.forEach(function (imagen) {
            var ficha = imagen.ficha;
            if (imagen.archivo) {
                ficha = 'archivo:' + transferencia.items.length;
                transferencia.items.add(imagen.archivo);
            }
            var oculto = document.createElement('input');
            oculto.type = 'hidden';
            oculto.name = 'galeria[]';
            oculto.value = ficha;
            fichas.appendChild(oculto);
        });

        campoArchivos.files = transferencia.files;
    }

    function actualizar() {
        pintar();
        volcar();
        avisarCambio();
    }

    // ── Agregar ─────────────────────────────────────────────────────────────

    function agregarUrl() {
        var url = inputUrl.value.trim();
        var valida = /^https?:\/\/\S+$/i.test(url);
        document.getElementById('galeria-url-error').classList.toggle('d-none', valida || url === '');
        if (!valida || imagenes.length >= MAXIMO) return;

        imagenes.push({ ficha: 'url:' + url, src: url, externa: true });
        inputUrl.value = '';
        actualizar();
    }

    if (inputUrl) {
        document.getElementById('galeria-agregar-url').addEventListener('click', agregarUrl);
        inputUrl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); agregarUrl(); }   // sin esto, Enter envía el producto
        });
    }

    if (elegir) {
        elegir.addEventListener('change', function () {
            var lugar = MAXIMO - imagenes.length;
            Array.prototype.slice.call(this.files, 0, lugar).forEach(function (archivo) {
                imagenes.push({ ficha: '', src: URL.createObjectURL(archivo), externa: false, archivo: archivo });
            });
            this.value = '';   // permite volver a elegir el mismo archivo
            actualizar();
        });
    }

    // ── Arrastrar para ordenar ──────────────────────────────────────────────

    var arrastrado = null;

    lista.addEventListener('dragstart', function (e) {
        var item = e.target.closest('.galeria-item');
        if (!item) return;
        arrastrado = parseInt(item.dataset.indice, 10);
        item.classList.add('arrastrando');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(arrastrado));   // Firefox no arrastra sin esto
    });

    lista.addEventListener('dragover', function (e) {
        var item = e.target.closest('.galeria-item');
        if (arrastrado === null || !item) return;
        e.preventDefault();
        lista.querySelectorAll('.destino').forEach(function (el) { el.classList.remove('destino'); });
        item.classList.add('destino');
    });

    lista.addEventListener('drop', function (e) {
        var item = e.target.closest('.galeria-item');
        if (arrastrado === null || !item) return;
        e.preventDefault();
        var hasta = parseInt(item.dataset.indice, 10);
        var desde = arrastrado;
        arrastrado = null;
        if (desde !== hasta) mover(desde, hasta); else pintar();
    });

    lista.addEventListener('dragend', function () {
        arrastrado = null;
        lista.querySelectorAll('.arrastrando, .destino').forEach(function (el) {
            el.classList.remove('arrastrando', 'destino');
        });
    });

    pintar();
    volcar();
})();
</script>
@endpush
