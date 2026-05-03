<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $fillable = ['nombre', 'descripcion', 'grupo'];

    // Catálogo completo de permisos agrupados.
    // Es la fuente de verdad para el seeder y la UI.
    public static function catalogo()
    {
        return [
            'Dashboard'       => ['dashboard.ver'],
            'Productos'       => ['productos.ver', 'productos.crear', 'productos.editar', 'productos.eliminar'],
            'Proveedores'     => ['proveedores.ver', 'proveedores.crear', 'proveedores.editar', 'proveedores.eliminar'],
            'Pedidos'         => ['pedidos.ver', 'pedidos.gestionar'],
            'Etiquetas'       => ['etiquetas.ver', 'etiquetas.crear', 'etiquetas.editar', 'etiquetas.eliminar'],
            'Menú Tienda'     => ['menus.ver', 'menus.gestionar'],
            'Configuraciones' => ['configuraciones.ver', 'configuraciones.editar'],
            'Usuarios'        => ['usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar'],
            'Perfiles'        => ['perfiles.ver', 'perfiles.gestionar'],
        ];
    }

    public static function descripcionDe($nombre)
    {
        $descripciones = [
            'dashboard.ver'           => 'Ver el dashboard',
            'productos.ver'           => 'Ver listado de productos',
            'productos.crear'         => 'Crear productos',
            'productos.editar'        => 'Editar productos',
            'productos.eliminar'      => 'Eliminar productos',
            'proveedores.ver'         => 'Ver listado de proveedores',
            'proveedores.crear'       => 'Crear proveedores',
            'proveedores.editar'      => 'Editar proveedores',
            'proveedores.eliminar'    => 'Eliminar proveedores',
            'pedidos.ver'             => 'Ver listado de pedidos',
            'pedidos.gestionar'       => 'Confirmar, cancelar y editar pedidos',
            'etiquetas.ver'           => 'Ver listado de etiquetas',
            'etiquetas.crear'         => 'Crear etiquetas',
            'etiquetas.editar'        => 'Editar etiquetas',
            'etiquetas.eliminar'      => 'Eliminar etiquetas',
            'menus.ver'               => 'Ver menú de la tienda',
            'menus.gestionar'         => 'Crear, editar y eliminar ítems del menú',
            'configuraciones.ver'     => 'Ver configuraciones',
            'configuraciones.editar'  => 'Editar configuraciones',
            'usuarios.ver'            => 'Ver listado de usuarios',
            'usuarios.crear'          => 'Crear usuarios',
            'usuarios.editar'         => 'Editar usuarios',
            'usuarios.eliminar'       => 'Eliminar usuarios',
            'perfiles.ver'            => 'Ver listado de perfiles',
            'perfiles.gestionar'      => 'Crear, editar y eliminar perfiles',
        ];

        return $descripciones[$nombre] ?? $nombre;
    }

    public function perfiles()
    {
        return $this->belongsToMany(Perfil::class, 'perfil_permiso');
    }
}
