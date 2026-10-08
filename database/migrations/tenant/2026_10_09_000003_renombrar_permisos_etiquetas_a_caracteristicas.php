<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * En la pantalla de perfiles, los permisos de etiquetas pasan a decir
 * "Características" (D1). Los nombres (etiquetas.ver, ...) no cambian.
 */
return new class extends Migration
{
    private const TEXTOS = [
        'etiquetas.ver'      => ['Ver listado de características', 'Ver listado de etiquetas'],
        'etiquetas.crear'    => ['Crear características', 'Crear etiquetas'],
        'etiquetas.editar'   => ['Editar características', 'Editar etiquetas'],
        'etiquetas.eliminar' => ['Eliminar características', 'Eliminar etiquetas'],
    ];

    public function up()
    {
        $this->aplicar(0, 'Características');
    }

    public function down()
    {
        $this->aplicar(1, 'Etiquetas');
    }

    private function aplicar(int $cual, string $grupo): void
    {
        foreach (self::TEXTOS as $nombre => $textos) {
            DB::table('permisos')->where('nombre', $nombre)->update(['descripcion' => $textos[$cual], 'grupo' => $grupo]);
        }
    }
};
