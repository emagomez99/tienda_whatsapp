@extends('layouts.admin')

@section('title', 'Etiquetas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-tags"></i> Etiquetas</h2>
    <a href="{{ route('admin.etiquetas.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nueva Etiqueta
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('admin.etiquetas.index') }}" method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre..." value="{{ request('buscar') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($etiquetas->isEmpty())
            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle"></i> No se encontraron etiquetas.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Visible Usuarios</th>
                            <th>Productos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($etiquetas as $etiqueta)
                            <tr>
                                <td><strong>{{ $etiqueta->nombre }}</strong></td>
                                <td>
                                    @if($etiqueta->visible_usuarios)
                                        <span class="badge bg-success">Si</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-secondary">{{ $etiqueta->productos_count }}</span></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('admin.etiquetas.edit', $etiqueta) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.etiquetas.destroy', $etiqueta) }}" method="POST" onsubmit="return confirm('¿Eliminar esta etiqueta?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center">
                {{ $etiquetas->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
