<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso para importar productos desde Excel, para las tiendas que ya existen (las
 * nuevas lo reciben de PermisoSeeder). Superadministrador y Administrador; el
 * Operador no: una planilla puede cambiar miles de productos de una vez.
 */
return new class extends Migration
{
    private $nombre = 'productos.importar';

    public function up(): void
    {
        $ahora = now();
        $id = DB::table('permisos')->where('nombre', $this->nombre)->value('id')
            ?: DB::table('permisos')->insertGetId([
                'nombre'      => $this->nombre,
                'descripcion' => 'Importar y exportar productos con Excel',
                'grupo'       => 'Productos',
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ]);

        foreach (['Superadministrador', 'Administrador'] as $perfil) {
            $perfilId = DB::table('perfiles')->where('nombre', $perfil)->value('id');
            if ($perfilId) {
                DB::table('perfil_permiso')->updateOrInsert(['perfil_id' => $perfilId, 'permiso_id' => $id], []);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('permisos')->where('nombre', $this->nombre)->value('id');
        DB::table('perfil_permiso')->where('permiso_id', $id)->delete();
        DB::table('permisos')->where('id', $id)->delete();
    }
};
