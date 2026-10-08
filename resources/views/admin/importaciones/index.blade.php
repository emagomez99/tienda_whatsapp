@extends('layouts.admin')

@section('title', 'Importar productos')

{{--
    Productos con Excel, en dos pasos: bajar la plantilla (de una categoría, con sus
    datos) y subir la planilla completa. Después de subirla se ve qué va a pasar
    antes de aplicar nada (importaciones.show). Abajo, las importaciones anteriores.
--}}
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h3 class="mb-0"><i class="bi bi-file-earmark-spreadsheet"></i> Importar productos</h3>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <x-ayuda id="ayuda-importar" titulo="Cómo importar con Excel">
            @include('admin.ayudas.importar')
        </x-ayuda>
        <a href="{{ route('admin.productos.index') }}" class="btn btn-outline-secondary" title="Volver">
            <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Volver</span>
        </a>
    </div>
</div>
<p class="text-muted mb-4">Cargá o actualizá muchos productos de una vez con una planilla de Excel. Antes de cambiar nada, te mostramos qué va a pasar.</p>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="ayuda-icono">1</span>
                    <h5 class="mb-0">Bajá la plantilla</h5>
                </div>
                <p class="text-muted small">Elegí la categoría de los productos que vas a cargar: la planilla trae una columna por cada dato que pide (los obligatorios con *) y una hoja que explica cómo completarla.</p>
                <form action="{{ route('admin.importaciones.plantilla') }}" method="GET" class="d-flex flex-wrap gap-2">
                    <select name="categoria" class="form-select" style="max-width: 320px;" aria-label="Categoría de la plantilla">
                        <option value="">Todas las categorías</option>
                        @foreach($categorias as $categoria)
                            <option value="{{ $categoria->id }}">{!! str_repeat('&nbsp;&nbsp;&nbsp;', $categoria->nivel()) !!}{{ $categoria->nombre }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-download"></i> Bajar plantilla</button>
                </form>
                <p class="small text-muted mt-3 mb-0">
                    ¿Querés cambiar productos que ya tenés? <a href="{{ route('admin.importaciones.exportar') }}">Exportá tus productos</a>, cambiá lo que quieras en Excel y subila acá.
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="ayuda-icono">2</span>
                    <h5 class="mb-0">Subí la planilla</h5>
                </div>
                <p class="text-muted small">Todavía no se cambia nada: primero vas a ver cuántos productos se crean, cuántos se actualizan y si hay algo para corregir.</p>
                <form action="{{ route('admin.importaciones.store') }}" method="POST" enctype="multipart/form-data" id="form-subir-planilla">
                    @csrf
                    <input type="file" name="planilla" class="form-control @error('planilla') is-invalid @enderror" accept=".xlsx,.csv,.ods" required>
                    @error('planilla')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button type="submit" class="btn btn-primary mt-2" id="boton-subir-planilla">
                        <i class="bi bi-search"></i> Revisar planilla
                    </button>
                    <span class="small text-muted ms-2 d-none" id="subiendo-planilla">
                        <span class="spinner-border spinner-border-sm"></span> Leyendo la planilla… con muchos productos puede tardar un poco.
                    </span>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Importaciones anteriores</div>
    @if($importaciones->isEmpty())
        <div class="card-body text-muted small">Todavía no importaste ninguna planilla.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Planilla</th>
                        <th>Quién</th>
                        <th>Estado</th>
                        <th class="text-end">Creados</th>
                        <th class="text-end">Actualizados</th>
                        <th class="text-end">Con error</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($importaciones as $importacion)
                        <tr>
                            <td class="text-nowrap">{{ $importacion->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $importacion->nombre_archivo }}</td>
                            <td>{{ optional($importacion->usuario)->name ?? '-' }}</td>
                            <td>@include('admin.importaciones.partials.estado', ['estado' => $importacion->estado])</td>
                            <td class="text-end">{{ number_format($importacion->creados, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($importacion->actualizados, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($importacion->con_error, 0, ',', '.') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.importaciones.show', $importacion) }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@push('scripts')
<script>
    // Leer una planilla grande lleva unos segundos: que se vea que está trabajando
    // y que no se mande dos veces.
    document.getElementById('form-subir-planilla').addEventListener('submit', function () {
        document.getElementById('boton-subir-planilla').disabled = true;
        document.getElementById('subiendo-planilla').classList.remove('d-none');
    });
</script>
@endpush
@endsection
