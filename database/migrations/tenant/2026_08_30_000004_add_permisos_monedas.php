<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta los permisos del ABM de monedas en los tenants que ya existen.
 *
 * PermisoSeeder crea el catálogo entero, pero sólo corre al crear un tenant: sin esta
 * migración, las tiendas ya instaladas se quedarían con la pantalla de monedas
 * inaccesible para todo el mundo, incluido el superadministrador (que la ve por su
 * flag es_superadmin, pero no podría delegarla en nadie).
 *
 * Los nombres y descripciones van escritos acá y no se leen de Permiso::catalogo():
 * una migración con fecha describe un cambio puntual, y si mañana el catálogo suma
 * otro permiso esta migración no tiene por qué enterarse.
 *
 * Los perfiles reciben los permisos con el mismo criterio del seeder: Superadministrador
 * y Administrador todo; Operador nada (no administra la configuración de la tienda).
 */
return new class extends Migration
{
    private $permisos = [
        'monedas.ver'      => 'Ver listado de monedas',
        'monedas.crear'    => 'Crear monedas',
        'monedas.editar'   => 'Editar monedas y su cotización',
        'monedas.eliminar' => 'Eliminar monedas',
    ];

    public function up(): void
    {
        $ahora = now();
        $ids   = [];

        foreach ($this->permisos as $nombre => $descripcion) {
            $existente = DB::table('permisos')->where('nombre', $nombre)->value('id');

            if ($existente) {
                $ids[] = $existente;
                continue;
            }

            $ids[] = DB::table('permisos')->insertGetId([
                'nombre'      => $nombre,
                'descripcion' => $descripcion,
                'grupo'       => 'Monedas',
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ]);
        }

        $perfiles = DB::table('perfiles')
            ->whereIn('nombre', ['Superadministrador', 'Administrador'])
            ->pluck('id');

        foreach ($perfiles as $perfilId) {
            foreach ($ids as $permisoId) {
                DB::table('perfil_permiso')->updateOrInsert(
                    ['perfil_id' => $perfilId, 'permiso_id' => $permisoId],
                    []
                );
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
