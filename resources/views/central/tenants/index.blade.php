<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel Central — Tenants</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Panel Central</h1>
            <small class="text-muted">{{ count($tenants) }} tenant(s) registrados</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
            + Nuevo Tenant
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID / Slug</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>Dominio(s)</th>
                        <th class="text-center">Activo</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($tenants as $tenant)
                    <tr>
                        <td><code>{{ $tenant->id }}</code></td>
                        <td>{{ $tenant->nombre }}</td>
                        <td>{{ $tenant->email }}</td>
                        <td>
                            @php $planes = ['free'=>'secondary','basic'=>'info','pro'=>'warning']; @endphp
                            <span class="badge bg-{{ $planes[$tenant->plan] ?? 'secondary' }}">{{ $tenant->plan }}</span>
                        </td>
                        <td>
                            @foreach($tenant->domains as $domain)
                                <a href="http://{{ $domain->domain }}" target="_blank" class="text-decoration-none">
                                    {{ $domain->domain }}
                                </a>
                            @endforeach
                        </td>
                        <td class="text-center">
                            @if($tenant->activo)
                                <span class="badge bg-success">Sí</span>
                            @else
                                <span class="badge bg-danger">No</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('tenants.destroy', $tenant) }}"
                                  onsubmit="return confirm('¿Eliminar tenant {{ $tenant->id }} y su schema PostgreSQL? Esta acción no se puede deshacer.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            No hay tenants aún. Crea el primero con el botón de arriba.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal: Crear Tenant --}}
<div class="modal fade" id="modalCrear" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('tenants.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Tenant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="form-control font-monospace"
                               value="{{ old('slug') }}" pattern="[a-z0-9\-_]+"
                               placeholder="mi-tienda" required>
                        <div class="form-text">Solo minúsculas, números, guiones y guiones bajos. Será el schema: <code>tenant_<em>slug</em></code></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre de la tienda <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control"
                               value="{{ old('nombre') }}" placeholder="Mi Tienda Online" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control"
                               value="{{ old('email') }}" placeholder="admin@mitienda.com" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Dominio <span class="text-danger">*</span></label>
                        <input type="text" name="dominio" class="form-control"
                               value="{{ old('dominio') }}" placeholder="mitienda.com" required>
                        <div class="form-text">Sin http:// ni www. Ej: <code>mitienda.com</code> o <code>mitienda.test</code></div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <label class="form-label fw-semibold">Plan</label>
                            <select name="plan" class="form-select">
                                <option value="free" {{ old('plan') == 'free' ? 'selected' : '' }}>Free</option>
                                <option value="basic" {{ old('plan') == 'basic' ? 'selected' : '' }}>Basic</option>
                                <option value="pro" {{ old('plan') == 'pro' ? 'selected' : '' }}>Pro</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-check mt-3">
                        <input type="checkbox" name="seed" value="1" class="form-check-input"
                               id="seed" {{ old('seed') ? 'checked' : '' }}>
                        <label class="form-check-label" for="seed">
                            Ejecutar seed inicial <small class="text-muted">(admin + config + monedas)</small>
                        </label>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Tenant</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@if($errors->any())
<script>
    // Reabrir modal si hubo errores de validación
    new bootstrap.Modal(document.getElementById('modalCrear')).show();
</script>
@endif
</body>
</html>
