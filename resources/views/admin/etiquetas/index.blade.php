@extends('layouts.admin')

@section('title', 'Características')

@section('content')
@php $puedeEditar = auth()->user()->puede('etiquetas.editar'); @endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h3 class="mb-0"><i class="bi bi-ui-checks"></i> Características</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-caracteristicas" titulo="Cómo funcionan las características">
            @include('admin.ayudas.caracteristicas')
        </x-ayuda>
        <a href="{{ route('admin.etiquetas.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Crear característica
        </a>
    </div>
</div>

<p class="text-muted mb-4">Los datos que se cargan en cada producto: Marca, Sabor, Contenido… Qué datos pide cada producto se elige en su <a href="{{ route('admin.categorias.index') }}">categoría</a>.</p>

@include('admin.etiquetas.partials.fusion-propuesta')

<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('admin.etiquetas.index') }}" method="GET" class="row g-2">
            <div class="col">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar característica o valor (ej: Marca, Asus)..." value="{{ $buscar }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i> Buscar</button>
                @if($buscar !== '')
                    <a href="{{ route('admin.etiquetas.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                @endif
            </div>
        </form>
    </div>
</div>

@if($etiquetas->isEmpty())
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No se encontraron características.
    </div>
@else
    <div class="card">
        <ul class="list-group list-group-flush">
            @foreach($etiquetas as $etiqueta)
                @php
                    $valores = $valoresPorEtiqueta[$etiqueta->id];
                    $abierta = $buscar !== '';
                @endphp
                <li class="list-group-item p-0">
                    <div class="d-flex align-items-center gap-2 px-3 py-2">
                        <button type="button" class="btn btn-sm btn-link text-secondary p-0 etiqueta-toggle {{ $abierta ? '' : 'collapsed' }}"
                                data-bs-toggle="collapse" data-bs-target="#valores-{{ $etiqueta->id }}"
                                aria-expanded="{{ $abierta ? 'true' : 'false' }}" title="Ver valores">
                            <i class="bi bi-chevron-right"></i>
                        </button>

                        @if($puedeEditar)
                            <form action="{{ route('admin.etiquetas.visibilidad', $etiqueta) }}" method="POST" class="m-0">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-link p-0 {{ $etiqueta->visible_usuarios ? 'text-success' : 'text-muted' }}"
                                        title="{{ $etiqueta->visible_usuarios ? 'Se muestra en la tienda. Click para ocultarla.' : 'Oculta en la tienda. Click para mostrarla.' }}">
                                    <i class="bi {{ $etiqueta->visible_usuarios ? 'bi-eye' : 'bi-eye-slash' }}"></i>
                                </button>
                            </form>
                        @endif

                        <div class="flex-grow-1" role="button" data-bs-toggle="collapse" data-bs-target="#valores-{{ $etiqueta->id }}">
                            <strong class="{{ $etiqueta->visible_usuarios ? '' : 'text-muted' }}">{{ $etiqueta->nombre }}</strong>
                            <span class="text-muted small ms-2">
                                {{ $etiqueta->valores_count }} {{ $etiqueta->valores_count === 1 ? 'valor' : 'valores' }}
                                · {{ $etiqueta->productos_count }} {{ $etiqueta->productos_count === 1 ? 'producto' : 'productos' }}
                            </span>
                        </div>

                        <div class="btn-group">
                            <a href="{{ route('admin.etiquetas.edit', $etiqueta) }}" class="btn btn-sm btn-outline-primary" title="Editar característica">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('admin.etiquetas.destroy', $etiqueta) }}" method="POST"
                                  data-confirmar="¿Eliminar la característica «{{ $etiqueta->nombre }}»?"
                                  data-confirmar-detalle="Se {{ $etiqueta->valores_count === 1 ? 'borra también su único valor' : 'borran también sus ' . $etiqueta->valores_count . ' valores' }} y se quita de {{ $etiqueta->productos_count }} {{ $etiqueta->productos_count === 1 ? 'producto' : 'productos' }}."
                                  data-confirmar-boton="Sí, eliminar">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar característica">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="collapse {{ $abierta ? 'show' : '' }}" id="valores-{{ $etiqueta->id }}">
                        <div class="ps-5 pe-3 pb-2">
                            @forelse($valores as $valor)
                                @include('admin.etiquetas.partials.valor-fila', ['valor' => $valor, 'puedeEditar' => $puedeEditar])
                            @empty
                                <div class="text-muted small py-1">Todavía no hay productos con esta característica.</div>
                            @endforelse

                            @if($etiqueta->valores_count > $valores->count())
                                <a href="{{ route('admin.etiquetas.show', ['etiqueta' => $etiqueta, 'buscar' => $buscar ?: null]) }}" class="small d-inline-block mt-1 ms-2">
                                    Ver los {{ number_format($etiqueta->valores_count, 0, ',', '.') }} valores <i class="bi bi-arrow-right"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="d-flex justify-content-center mt-3">
        {{ $etiquetas->withQueryString()->links() }}
    </div>
@endif

<p class="text-muted small mt-3">
    <i class="bi bi-eye"></i> indica qué ve el cliente en la tienda. Si ocultás un valor, el cliente no lo ve en ningún lado,
    pero tus productos lo siguen teniendo: un menú armado con ese valor sigue mostrando esos productos.
</p>
@endsection

@push('styles')
<style>
    .etiqueta-toggle i { display: inline-block; transition: transform .2s; }
    .etiqueta-toggle:not(.collapsed) i { transform: rotate(90deg); }
</style>
@endpush

@push('scripts')
    @include('admin.etiquetas.partials.valores-scripts')
@endpush
