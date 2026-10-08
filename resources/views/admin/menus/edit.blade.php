@extends('layouts.admin')

@section('title', 'Editar menú')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-pencil"></i> Editar menú: {{ $menu->nombre }}</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-menus" titulo="Cómo funciona el menú">
            @include('admin.ayudas.menus')
        </x-ayuda>
        <a href="{{ route('admin.menus.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<form action="{{ route('admin.menus.update', $menu) }}" method="POST" id="menu-form">
    @csrf
    @method('PUT')
    @include('admin.menus.partials.formulario')
</form>
@endsection
