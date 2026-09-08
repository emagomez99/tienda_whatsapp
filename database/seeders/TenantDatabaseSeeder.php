<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\Moneda;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantDatabaseSeeder extends Seeder
{
    public function run()
    {
        // Crear permisos y perfiles base
        $this->call(PermisoSeeder::class);

        $perfilSuperadmin = Perfil::where('nombre', 'Superadministrador')->first();

        // Usuario administrador del tenant
        User::create([
            'name'      => 'Administrador',
            'email'     => 'tredevs@gmail.com',
            'password'  => Hash::make('Pepe@1234'),
            'is_admin'  => true,
            'activo'    => true,
            'perfil_id' => $perfilSuperadmin ? $perfilSuperadmin->id : null,
        ]);

        // Configuraciones base
        Configuracion::establecer('mostrar_precios', 'true', 'Mostrar precios en la tienda');
        Configuracion::establecer('mostrar_productos_sin_stock', 'true', 'Mostrar productos sin stock');
        Configuracion::establecer('whatsapp_admin', '', 'Número de WhatsApp del administrador');
        Configuracion::establecer('nombre_tienda', 'Mi Tienda', 'Nombre de la tienda');
        Configuracion::establecer('mostrar_nombre_tienda', 'true', 'Mostrar nombre en el header');
        Configuracion::establecer('paleta', 'azul', 'Paleta de colores');
        Configuracion::establecer('posicion_menu', 'superior', 'Posición del menú de navegación');
        Configuracion::establecer('modo_imagen_producto', 'solo_url', 'Modo de carga de imágenes de productos');

        // Monedas. El peso es la base: su cotización es 1 y las demás se miden contra
        // ella. Las otras arrancan también en 1 porque no hay ninguna cotización que
        // inventar que sea menos falsa que otra -- las carga el administrador desde
        // el ABM de monedas, y hasta entonces no interviene en ningún precio.
        Moneda::create([
            'nombre'     => 'Peso Argentino',
            'codigo'     => 'ARS',
            'simbolo'    => '$',
            'activa'     => true,
            'es_base'    => true,
            'es_default' => true,
        ]);
        Moneda::create([
            'nombre'  => 'Dólar Estadounidense',
            'codigo'  => 'USD',
            'simbolo' => 'U$S',
            'activa'  => true,
        ]);
        Moneda::create([
            'nombre'  => 'Euro',
            'codigo'  => 'EUR',
            'simbolo' => '€',
            'activa'  => true,
        ]);
    }
}
