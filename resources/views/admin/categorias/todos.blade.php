@extends('layouts.admin')

@section('title', 'Todos los productos')

{{--
    Lo que se pide al cargar cualquier producto (características globales), con el
    mismo editor que una categoría. Se llega desde la fila "Todos los productos" de
    arriba del árbol de Categorías. Ver CategoriaController::editarTodos.
--}}
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h3 class="mb-0"><i class="bi bi-globe"></i> Todos los productos</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-categorias" titulo="Cómo funcionan las categorías">
            @include('admin.ayudas.categorias')
        </x-ayuda>
        <a href="{{ route('admin.categorias.index') }}" class="btn btn-outline-secondary" title="Volver">
            <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
        </a>
    </div>
</div>
<p class="text-muted mb-4">Datos que tiene cualquier producto, esté en el estante que esté. Por ejemplo la marca: la tiene una gaseosa, unos fideos y un detergente.</p>

<form action="{{ route('admin.categorias.todos.update') }}" method="POST" id="form-todos">
    @csrf
    @method('PUT')
    <div class="row">
        <div class="col-lg-8">
            @include('admin.categorias.partials.caracteristicas', [
                'explicacionCaracteristicas' => 'Se piden al cargar cualquier producto, además de lo que pida su categoría. Las obligatorias hay que completarlas para poder guardar.',
                'ordenable' => false,
            ])
        </div>
    </div>

    @include('admin.partials.barra-guardar', [
        'formId'       => 'form-todos',
        'textoGuardar' => 'Guardar cambios',
        'cancelarUrl'  => route('admin.categorias.index'),
    ])
</form>
@endsection
