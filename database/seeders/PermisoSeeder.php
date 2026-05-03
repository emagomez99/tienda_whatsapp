<?php

namespace Database\Seeders;

use App\Models\Perfil;
use App\Models\Permiso;
use Illuminate\Database\Seeder;

class PermisoSeeder extends Seeder
{
    public function run()
    {
        // Crear todos los permisos del catálogo
        foreach (Permiso::catalogo() as $grupo => $slugs) {
            foreach ($slugs as $slug) {
                Permiso::firstOrCreate(
                    ['nombre' => $slug],
                    [
                        'descripcion' => Permiso::descripcionDe($slug),
                        'grupo'       => $grupo,
                    ]
                );
            }
        }

        $todosLosPermisos = Permiso::all();

        // Perfil Superadministrador — acceso total por flag
        $superadmin = Perfil::firstOrCreate(
            ['nombre' => 'Superadministrador'],
            [
                'descripcion'   => 'Acceso total al sistema, sin restricciones',
                'es_superadmin' => true,
                'activo'        => true,
            ]
        );
        $superadmin->permisos()->sync($todosLosPermisos->pluck('id'));

        // Perfil Administrador — todos los permisos excepto gestión de perfiles
        $adminPermisos = $todosLosPermisos
            ->whereNotIn('nombre', ['perfiles.ver', 'perfiles.gestionar'])
            ->pluck('id');

        $admin = Perfil::firstOrCreate(
            ['nombre' => 'Administrador'],
            [
                'descripcion'   => 'Administración completa de la tienda',
                'es_superadmin' => false,
                'activo'        => true,
            ]
        );
        $admin->permisos()->sync($adminPermisos);

        // Perfil Operador — acceso operativo básico
        $operadorSlugs = [
            'dashboard.ver',
            'productos.ver', 'productos.crear', 'productos.editar',
            'proveedores.ver',
            'pedidos.ver', 'pedidos.gestionar',
            'etiquetas.ver',
        ];

        $operadorPermisos = $todosLosPermisos
            ->whereIn('nombre', $operadorSlugs)
            ->pluck('id');

        $operador = Perfil::firstOrCreate(
            ['nombre' => 'Operador'],
            [
                'descripcion'   => 'Gestión operativa: productos, pedidos y proveedores',
                'es_superadmin' => false,
                'activo'        => true,
            ]
        );
        $operador->permisos()->sync($operadorPermisos);
    }
}
