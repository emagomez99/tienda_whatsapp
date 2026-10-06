@extends('layouts.admin')

@section('title', 'Nuevo menú')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-plus-circle"></i> Nuevo menú</h3>
    <a href="{{ route('admin.menus.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form action="{{ route('admin.menus.store') }}" method="POST" id="menu-form">
    @csrf
    @include('admin.menus.partials.formulario')
</form>
@endsection
