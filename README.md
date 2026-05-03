# TiendasApp — Plataforma Multi-tenant

Plataforma SaaS de tiendas online construida con **Laravel 8** y **stancl/tenancy v3.6**.
Cada cliente tiene su propia tienda aislada en un schema de PostgreSQL independiente.

## Arquitectura

```
PostgreSQL: tiendasapp
├── schema: public          → tenants, domains (datos de la plataforma)
├── schema: tenant_demo     → toda la app del tenant "demo"
├── schema: tenant_arcor    → toda la app del tenant "arcor"
└── schema: tenant_...
```

- **Identificación**: por dominio (`tienda-juan.com` → tenant `juan`)
- **Panel central**: `http://plataforma.test` → gestión de tenants
- **Tienda**: `http://{dominio}` → la tienda del tenant

---

## Requisitos

- PHP 7.4+
- PostgreSQL 13+
- Composer
- Node.js + npm (para assets)

---

## Puesta en marcha

### 1. Clonar e instalar dependencias

```bash
git clone <repo> tiendasapp
cd tiendasapp
composer install --ignore-platform-req=php
npm install && npm run dev
```

> `--ignore-platform-req=php` es necesario si la CLI local tiene PHP < 7.4.
> En el servidor de producción con PHP 7.4+ se puede omitir.

---

### 2. Configurar el entorno

Copiar el archivo de ejemplo:

```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env` con los valores del entorno:

```ini
APP_NAME="TiendasApp"
APP_ENV=production          # o local en desarrollo
APP_URL=http://plataforma.test

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tiendasapp
DB_USERNAME=postgres
DB_PASSWORD=tu_password

CACHE_DRIVER=file
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
```

---

### 3. Crear la base de datos en PostgreSQL

```bash
psql -U postgres -c "CREATE DATABASE tiendasapp;"
```

O desde psql interactivo:

```sql
CREATE DATABASE tiendasapp;
```

---

### 4. Ejecutar migraciones centrales

Esto crea solo las tablas `tenants` y `domains` en el schema `public`:

```bash
php artisan migrate
```

Verificar que quedaron solo 2 tablas centrales:

```bash
php artisan migrate:status
```

Debe mostrar únicamente:
- `2019_09_15_000010_create_tenants_table`
- `2019_09_15_000020_create_domains_table`

---

### 5. Configurar hosts locales (solo desarrollo)

Agregar en `/etc/hosts` (Linux/Mac) o `C:\Windows\System32\drivers\etc\hosts` (Windows):

```
127.0.0.1  plataforma.test
127.0.0.1  demo.test
```

Un registro por cada tenant que se cree localmente.

---

### 6. Crear el primer tenant

```bash
php artisan tenant:create {slug} "{Nombre}" "{email}" "{dominio}" --seed
```

**Ejemplo:**

```bash
php artisan tenant:create demo "Tienda Demo" "demo@test.com" "demo.test" --seed
```

Esto automáticamente:
1. Crea el registro en `public.tenants`
2. Crea el schema `tenant_demo` en PostgreSQL
3. Ejecuta todas las migraciones de `database/migrations/tenant/`
4. Con `--seed`: crea el usuario admin, configuraciones base y monedas

**Más ejemplos:**

```bash
php artisan tenant:create arcor "Arcor SA" "it@arcor.com" "arcor.tiendasapp.com" --seed
php artisan tenant:create perfumes "Casa Perfumes" "admin@perfumes.com" "perfumes.test" --seed
```

---

### 7. Verificar

Verificar que se crearon los schemas en PostgreSQL:

```bash
psql -U postgres -d tiendasapp -c "\dn"
```

Debe mostrar:
```
   Name        |  Owner
---------------+----------
 public        | postgres
 tenant_demo   | postgres
 tenant_arcor  | postgres
```

Acceder en el navegador:
- `http://plataforma.test` → panel central (lista de tenants)
- `http://demo.test` → tienda del tenant "demo"
- `http://demo.test/login` → login admin
- `http://demo.test/admin` → panel de administración

---

## Gestión de tenants

### Crear tenant (CLI)

```bash
php artisan tenant:create {slug} "{Nombre}" "{email}" "{dominio}" [--seed] [--plan=free]
```

| Argumento | Descripción |
|-----------|-------------|
| `slug` | Identificador único. Se usa como nombre del schema (`tenant_{slug}`). Solo letras, números y guiones. |
| `nombre` | Nombre visible de la tienda |
| `email` | Email del tenant (debe ser único) |
| `dominio` | Dominio completo (ej: `mitienda.com`) |
| `--seed` | Crea usuario admin + configuraciones + monedas |
| `--plan` | `free` (default), `basic`, `pro` |

### Crear tenant (panel web)

Acceder a `http://plataforma.test/tenants/create`.

### Listar tenants

```bash
php artisan tenants:list
```

### Migrar todos los tenants

Cuando se agregan nuevas migraciones a `database/migrations/tenant/`:

```bash
php artisan tenants:migrate
```

Para un tenant específico:

```bash
php artisan tenants:migrate --tenants=demo
```

### Ejecutar seed en un tenant

```bash
php artisan tenants:seed --tenants=demo
```

### Eliminar tenant

Desde el panel web en `http://plataforma.test/tenants`, o via Tinker:

```bash
php artisan tinker
>>> App\Models\Tenant::find('demo')->delete();
```

Esto elimina el registro y **borra el schema completo** de PostgreSQL.

---

## Importar productos desde Hercules

El comando de importación es tenant-aware. Siempre especificar el tenant:

```bash
php artisan import:hercules --tenant=demo
php artisan import:hercules --tenant=demo --fabricante=Atlas
php artisan import:hercules --tenant=demo --dry-run
php artisan import:hercules --tenant=demo --refresh
```

---

## Storage de imágenes

Cada tenant tiene su propio directorio de storage:

```
storage/app/public/tenant_demo/     → imágenes del tenant "demo"
storage/app/public/tenant_arcor/    → imágenes del tenant "arcor"
```

Crear el symlink de storage (solo una vez por servidor):

```bash
php artisan storage:link
```

---

## Estructura de archivos relevantes

```
app/
├── Console/Commands/
│   ├── CreateTenant.php          ← crea tenant + schema + dominio
│   └── ImportHercules.php        ← importa productos (tenant-aware)
├── Http/Controllers/
│   ├── Central/
│   │   └── TenantController.php  ← CRUD de tenants (dominio central)
│   ├── Admin/                    ← panel admin de cada tienda
│   └── TiendaController.php      ← tienda pública
├── Models/
│   ├── Tenant.php                ← modelo multi-tenant
│   └── Configuracion.php         ← config con cache tenant-aware
└── Providers/
    └── TenancyServiceProvider.php

config/
└── tenancy.php                   ← configuración del paquete

database/
├── migrations/                   ← solo tenants + domains (centrales)
└── migrations/tenant/            ← todas las tablas de la app

routes/
├── web.php                       ← rutas del panel central
└── tenant.php                    ← rutas de la tienda (por tenant)
```

---

## Producción (CentOS + PHP 7.4)

### Instalar extensión pgsql

```bash
# CentOS/RHEL con Remi
dnf install php74-php-pgsql

# Verificar
php -m | grep pgsql
```

### Permisos de storage

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### Nginx — Virtual hosts

```nginx
# Dominio central
server {
    listen 80;
    server_name plataforma.com;
    root /var/www/tiendasapp/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php7.4-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}

# Tenants con dominio propio (un bloque por dominio)
server {
    listen 80;
    server_name mitienda.com;
    root /var/www/tiendasapp/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php7.4-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}

# Alternativa: wildcard para subdominios (*.tiendasapp.com)
server {
    listen 80;
    server_name *.tiendasapp.com;
    root /var/www/tiendasapp/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php7.4-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

### Caché en producción

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Credenciales por defecto (tras --seed)

| Campo | Valor |
|-------|-------|
| Email | `admin@tienda.com` |
| Password | `password` |

**Cambiar la password inmediatamente tras el primer login.**
