{{--
    Cómo se ve el menú en la tienda, arriba de la lista: en escritorio (barra con
    desplegables) y en celular (menú lateral). Sólo los menús activos, igual que la
    tienda. Tocar una opción lleva a su fila en la lista de abajo.

    Recibe $menus (árbol completo) y $productosPorMenu.
--}}
@php
    $activos = function ($lista) {
        return $lista->filter(function ($m) { return $m->activo; })->values();
    };
    $logo   = App\Models\Configuracion::logo();
    $tienda = App\Models\Configuracion::nombreTienda();
@endphp

<div class="card mb-4" id="vista-previa-menu">
    <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-eye"></i> Así se ve en la tienda</span>
        <div class="btn-group btn-group-sm" role="tablist">
            <button type="button" class="btn btn-outline-secondary active" data-bs-toggle="tab" data-bs-target="#vp-escritorio" role="tab">
                <i class="bi bi-display"></i> Escritorio
            </button>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="tab" data-bs-target="#vp-celular" role="tab">
                <i class="bi bi-phone"></i> Celular
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="vp-escritorio" role="tabpanel">
                <nav class="vp-barra">
                    @if($logo)
                        <img src="{{ url('storage/' . $logo) }}" alt="" class="vp-logo">
                    @else
                        <strong class="me-2">{{ $tienda }}</strong>
                    @endif
                    @forelse($activos($menus) as $menu)
                        @include('admin.menus.partials.vista-previa-item', ['menu' => $menu, 'nivel' => 0, 'activos' => $activos])
                    @empty
                        <span class="opacity-75 small">Todavía no hay menús activos.</span>
                    @endforelse
                </nav>
                <small class="text-muted d-block mt-2">
                    Pasá el mouse por las opciones para ver los submenús. Tocá una para ir a su fila en la lista.
                </small>
            </div>

            <div class="tab-pane fade" id="vp-celular" role="tabpanel">
                <div class="vp-celular">
                    <div class="vp-celular-encabezado">
                        @if($logo)
                            <img src="{{ url('storage/' . $logo) }}" alt="" class="vp-logo">
                        @else
                            <strong>{{ $tienda }}</strong>
                        @endif
                    </div>
                    @forelse($activos($menus) as $menu)
                        @include('admin.menus.partials.vista-previa-drawer', ['menu' => $menu, 'nivel' => 0, 'activos' => $activos])
                    @empty
                        <div class="p-3 small opacity-75">Todavía no hay menús activos.</div>
                    @endforelse
                </div>
                <small class="text-muted d-block mt-2 text-center">Menú lateral que se abre con ☰ en el celular.</small>
            </div>
        </div>

        <small class="text-muted d-block mt-1">
            <i class="bi bi-info-circle"></i> Los menús inactivos no aparecen en la tienda.
        </small>
    </div>
</div>

@push('styles')
<style>
    /* ── Escritorio: barra con desplegables, con el mismo estilo que la tienda ── */
    .vp-barra {
        display: flex; flex-wrap: wrap; align-items: center; gap: .25rem;
        background: var(--admin-color); color: var(--admin-texto);
        border-radius: .5rem; padding: .5rem 1rem; min-height: 56px;
    }
    .vp-logo { height: 32px; width: auto; max-width: 120px; object-fit: contain; margin-right: .75rem; }
    .vp-item { position: relative; }
    .vp-link {
        display: flex; align-items: center; justify-content: space-between; gap: .4rem;
        padding: .4rem .6rem; border-radius: .4rem;
        color: inherit; text-decoration: none; white-space: nowrap;
    }
    .vp-barra > .vp-item > .vp-link:hover { background: rgba(var(--admin-texto-rgb), .1); color: inherit; }
    .vp-panel {
        display: none; position: absolute; top: 100%; left: 0; z-index: 30;
        margin-top: .25rem; padding: .3rem; min-width: 10rem;
        background: #fff; color: #212529; border-radius: .6rem;
        box-shadow: 0 .5rem 1.75rem rgba(0, 0, 0, .14), 0 0 0 1px rgba(0, 0, 0, .04);
    }
    .vp-panel::before { content: ""; position: absolute; left: 0; right: 0; top: -.3rem; height: .3rem; }
    .vp-item:hover > .vp-panel, .vp-item:focus-within > .vp-panel { display: block; }
    .vp-panel .vp-panel { top: -.3rem; left: 100%; margin: 0 0 0 .2rem; }
    .vp-panel .vp-panel::before { top: 0; bottom: 0; left: -.5rem; right: auto; width: .5rem; height: auto; }
    .vp-panel .vp-link { color: #212529; padding: .4rem .7rem; font-size: .95rem; }
    .vp-panel .vp-item:hover > .vp-link,
    .vp-panel .vp-link:hover { background: color-mix(in srgb, var(--admin-color) 25%, white); color: #212529; }
    .vp-todos { font-weight: 600; }
    .vp-separador { border-top: 1px solid #e9ecef; margin: .3rem .25rem; }
    .vp-cuenta { font-size: .7rem; color: #6c757d; }
    .vp-cuenta.vacio { color: #b45309; }
    .vp-barra > .vp-item > .vp-link .vp-cuenta { color: inherit; opacity: .7; }

    /* ── Celular: el menú lateral, como columna angosta ── */
    .vp-celular {
        width: 280px; max-width: 100%; margin: 0 auto; overflow: hidden;
        background: var(--admin-color); color: var(--admin-texto);
        border-radius: 1rem; box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .15);
    }
    .vp-celular-encabezado { padding: 1rem 1.25rem; border-bottom: 1px solid rgba(var(--admin-texto-rgb), .15); }
    .vp-drawer-item, .vp-celular summary {
        display: flex; justify-content: space-between; align-items: center; gap: .5rem;
        padding: .7rem 1.25rem; color: inherit; text-decoration: none; cursor: pointer;
        border-bottom: 1px solid rgba(var(--admin-texto-rgb), .1); list-style: none;
    }
    .vp-celular summary::-webkit-details-marker { display: none; }
    .vp-celular details[open] > summary .bi-chevron-down { transform: rotate(180deg); }
    .vp-celular .bi-chevron-down { transition: transform .2s; font-size: .8rem; }
    .vp-celular .vp-sub { background: rgba(var(--admin-texto-rgb), .05); }
    .vp-celular .vp-sub .vp-drawer-item, .vp-celular .vp-sub summary { padding-left: 2rem; font-size: .93rem; }
    .vp-celular .vp-sub .vp-sub .vp-drawer-item, .vp-celular .vp-sub .vp-sub summary { padding-left: 2.75rem; }
    .vp-celular .vp-cuenta { color: inherit; opacity: .7; }

    /* Al tocar una opción de la vista previa, su fila de la lista se resalta. */
    .menu-item:target { animation: recien-movido 1.6s ease-out; }
</style>
@endpush
