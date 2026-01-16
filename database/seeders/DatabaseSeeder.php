<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\Etiqueta;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\ProductoEspecificacion;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Crear usuario administrador
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@tienda.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'activo' => true,
        ]);

        // Configuraciones iniciales
        Configuracion::establecer('mostrar_precios', 'true', 'Mostrar precios en la tienda');
        Configuracion::establecer('mostrar_productos_sin_stock', 'true', 'Mostrar productos sin stock');
        Configuracion::establecer('whatsapp_admin', '5491112345678', 'Número de WhatsApp del administrador');
        Configuracion::establecer('nombre_tienda', 'Tienda MC', 'Nombre de la tienda');

        // Crear monedas
        $monedaUSD = Moneda::create([
            'nombre' => 'Dólar Estadounidense',
            'codigo' => 'USD',
            'simbolo' => '$',
            'activa' => true,
        ]);

        $monedaARS = Moneda::create([
            'nombre' => 'Peso Argentino',
            'codigo' => 'ARS',
            'simbolo' => '$',
            'activa' => true,
        ]);

        $monedaEUR = Moneda::create([
            'nombre' => 'Euro',
            'codigo' => 'EUR',
            'simbolo' => '€',
            'activa' => true,
        ]);

        // Crear proveedores de ejemplo
        $proveedor1 = Proveedor::create([
            'nombre' => 'Proveedor Principal',
            'contacto' => 'Juan Pérez',
            'telefono' => '011-4444-5555',
            'email' => 'juan@proveedor1.com',
            'activo' => true,
        ]);

        $proveedor2 = Proveedor::create([
            'nombre' => 'Distribuidora XYZ',
            'contacto' => 'María García',
            'telefono' => '011-6666-7777',
            'email' => 'maria@xyz.com',
            'activo' => true,
        ]);

        // Crear etiquetas (definiciones)
        $etiquetaCategoria = Etiqueta::create(['nombre' => 'Categoria', 'visible_usuarios' => true]);
        $etiquetaModelo = Etiqueta::create(['nombre' => 'Modelo', 'visible_usuarios' => true]);

        // Crear productos de ejemplo
        $producto1 = Producto::create([
            'proveedor_id' => $proveedor1->id,
            'id_proveedor' => 'PRV-001',
            'descripcion' => 'Producto de ejemplo 1',
            'precio' => 1500.00,
            'moneda_id' => $monedaUSD->id,
            'disponible' => true,
            'stock' => 10,
            'por_encargue' => false,
            'url_imagen' => null,
        ]);

        $producto2 = Producto::create([
            'proveedor_id' => $proveedor1->id,
            'id_proveedor' => 'PRV-002',
            'descripcion' => 'Producto de ejemplo 2',
            'precio' => 2500.00,
            'moneda_id' => $monedaARS->id,
            'disponible' => true,
            'stock' => 5,
            'por_encargue' => true,
            'url_imagen' => null,
        ]);

        $producto3 = Producto::create([
            'proveedor_id' => $proveedor2->id,
            'id_proveedor' => 'XYZ-100',
            'descripcion' => 'Producto de ejemplo 3',
            'precio' => 800.00,
            'moneda_id' => $monedaEUR->id,
            'disponible' => true,
            'stock' => 0,
            'por_encargue' => true,
            'url_imagen' => null,
        ]);

        // Asignar etiquetas a productos con valores
        $producto1->etiquetas()->attach([
            $etiquetaCategoria->id => ['valor' => 'Filtro'],
            $etiquetaModelo->id => ['valor' => 'Auto'],
        ]);
        $producto2->etiquetas()->attach([
            $etiquetaCategoria->id => ['valor' => 'Electronica'],
            $etiquetaModelo->id => ['valor' => '720X'],
        ]);
        $producto3->etiquetas()->attach([
            $etiquetaCategoria->id => ['valor' => 'Accesorios'],
        ]);

        // Crear especificaciones para productos
        ProductoEspecificacion::create([
            'producto_id' => $producto1->id,
            'clave' => 'Peso',
            'valor' => '1.75',
        ]);

        ProductoEspecificacion::create([
            'producto_id' => $producto1->id,
            'clave' => 'Color',
            'valor' => 'Negro',
        ]);

        ProductoEspecificacion::create([
            'producto_id' => $producto2->id,
            'clave' => 'Peso',
            'valor' => '250g',
        ]);
    }
}
