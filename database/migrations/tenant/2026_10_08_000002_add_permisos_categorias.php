<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos de Categorías para las tiendas que ya existen (las nuevas los reciben
 * de PermisoSeeder). Mismo criterio que add_permisos_monedas: Superadministrador y
 * Administrador todo; Operador sólo ver, porque carga productos y el formulario
 * necesita listar las categorías.
 */
return new class extends Migration
{
    private $permisos = [
        'categorias.ver'       => 'Ver categorías',
        'categorias.gestionar' => 'Crear, editar, ordenar y eliminar categorías',
    ];

    public function up(): void
    {
        $ahora = now();
        $ids   = [];

        foreach ($this->permisos as $nombre => $descripcion) {
            $ids[$nombre] = DB::table('permisos')->where('nombre', $nombre)->value('id')
                ?: DB::table('permisos')->insertGetId([
                    'nombre'      => $nombre,
                    'descripcion' => $descripcion,
                    'grupo'       => 'Categorías',
                    'created_at'  => $ahora,
                    'updated_at'  => $ahora,
                ]);
        }

        $asignar = [
            'Superadministrador' => array_values($ids),
            'Administrador'      => array_values($ids),
            'Operador'           => [$ids['categorias.ver']],
        ];

        foreach ($asignar as $perfil => $permisos) {
            $perfilId = DB::table('perfiles')->where('nombre', $perfil)->value('id');
            if (!$perfilId) {
                continue;
            }
            foreach ($permisos as $permisoId) {
                DB::table('perfil_permiso')->updateOrInsert(['perfil_id' => $perfilId, 'permiso_id' => $permisoId], []);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permisos')->whereIn('nombre', array_keys($this->permisos))->pluck('id');

        DB::table('perfil_permiso')->whereIn('permiso_id', $ids)->delete();
        DB::table('permisos')->whereIn('id', $ids)->delete();
    }
};
