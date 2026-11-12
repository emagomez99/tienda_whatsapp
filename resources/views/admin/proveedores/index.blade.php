@extends('layouts.admin')

@section('title', 'Proveedores')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h3 class="mb-0"><i class="bi bi-truck"></i> Proveedores</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-proveedores" titulo="Cómo funcionan los proveedores">
            @include('admin.ayudas.proveedores')
        </x-ayuda>
        @if(auth()->user()->puede('proveedores.crear'))
        <a href="{{ route('admin.proveedores.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Crear proveedor
        </a>
        @endif
    </div>
</div>

<x-filtros :url="route('admin.proveedores.index')" buscar="Buscar por nombre, contacto o email" titulo="Filtrar proveedores"
           :campos="[['nombre' => 'activo', 'titulo' => 'Estado', 'opciones' => ['1' => 'Activos', '0' => 'Inactivos']]]" />

<div class="card">
    <div class="card-body">
        @if($proveedores->isEmpty())
            @include('admin.partials.vacio', ['icono' => 'bi-truck', 'filtrado' => 'Ningún proveedor coincide con lo que buscás', 'nada' => 'Todavía no cargaste ningún proveedor', 'url' => route('admin.proveedores.index'), 'crear' => auth()->user()->puede('proveedores.crear') ? route('admin.proveedores.create') : null, 'crearTexto' => 'Cargar el primer proveedor'])
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th class="d-none d-md-table-cell">Contacto</th>
                            <th class="d-none d-md-table-cell">Teléfono</th>
                            <th class="d-none d-md-table-cell">Email</th>
                            <th>Productos</th>
                            <th class="d-none d-md-table-cell">Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($proveedores as $proveedor)
                            <tr>
                                {{-- En el celular quedan el nombre, cuántos productos tiene y
                                     los botones; el teléfono baja debajo del nombre. --}}
                                <td>
                                    <strong>{{ $proveedor->nombre }}</strong>
                                    @if(!$proveedor->activo)
                                        <span class="badge bg-secondary d-md-none">Inactivo</span>
                                    @endif
                                    @if($proveedor->telefono)
                                        <div class="small text-muted d-md-none">{{ $proveedor->telefono }}</div>
                                    @endif
                                </td>
                                <td class="d-none d-md-table-cell">{{ $proveedor->contacto ?? '-' }}</td>
                                <td class="d-none d-md-table-cell">{{ $proveedor->telefono ?? '-' }}</td>
                                <td class="d-none d-md-table-cell">{{ $proveedor->email ?? '-' }}</td>
                                <td><span class="badge rounded-pill bg-light text-dark cifras">{{ number_format($proveedor->productos_count, 0, ',', '.') }}</span></td>
                                <td class="d-none d-md-table-cell">
                                    @if($proveedor->activo)
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-secondary">Inactivo</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group">
                                        @if(auth()->user()->puede('proveedores.editar'))
                                        <a href="{{ route('admin.proveedores.edit', $proveedor) }}" class="btn btn-sm btn-outline-primary" title="Editar" aria-label="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @endif
                                        @if(auth()->user()->puede('proveedores.eliminar'))
                                        <form action="{{ route('admin.proveedores.destroy', $proveedor) }}" method="POST" data-confirmar="¿Eliminar el proveedor «{{ $proveedor->nombre }}»?" data-confirmar-boton="Sí, eliminar">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar" aria-label="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center">
                {{ $proveedores->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
