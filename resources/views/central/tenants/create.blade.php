<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Tenant — Panel Central</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container py-5" style="max-width:600px">
    <h1 class="mb-4">Nuevo Tenant</h1>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tenants.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Slug <small class="text-muted">(identificador único, ej: demo, arcor, perfumes)</small></label>
            <input type="text" name="slug" class="form-control font-monospace" value="{{ old('slug') }}" pattern="[a-z0-9\-_]+" placeholder="mi-tienda" required>
            <div class="form-text">Solo minúsculas, números, guiones y guiones bajos. Será el nombre del schema: <code>tenant_<em>slug</em></code></div>
        </div>
        <div class="mb-3">
            <label class="form-label">Nombre de la tienda</label>
            <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Dominio (ej: tienda-juan.com)</label>
            <input type="text" name="dominio" class="form-control" value="{{ old('dominio') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Plan</label>
            <select name="plan" class="form-select">
                <option value="free">Free</option>
                <option value="basic">Basic</option>
                <option value="pro">Pro</option>
            </select>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="seed" value="1" class="form-check-input" id="seed">
            <label class="form-check-label" for="seed">Ejecutar seed inicial (admin + configuraciones + monedas)</label>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Crear Tenant</button>
            <a href="{{ route('tenants.index') }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>
