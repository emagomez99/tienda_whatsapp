<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menús de tipo "Los de una categoría" (enlace_id = categorias.id).
 * La columna es un enum de Laravel: en PostgreSQL, un CHECK con la lista de valores.
 */
return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE menus DROP CONSTRAINT IF EXISTS menus_tipo_enlace_check');
        DB::statement("ALTER TABLE menus ADD CONSTRAINT menus_tipo_enlace_check CHECK (tipo_enlace IN ('ninguno', 'proveedor', 'etiqueta', 'especificacion', 'categoria'))");
    }

    public function down()
    {
        DB::table('menus')->where('tipo_enlace', 'categoria')->update(['tipo_enlace' => 'ninguno', 'enlace_id' => null]);
        DB::statement('ALTER TABLE menus DROP CONSTRAINT IF EXISTS menus_tipo_enlace_check');
        DB::statement("ALTER TABLE menus ADD CONSTRAINT menus_tipo_enlace_check CHECK (tipo_enlace IN ('ninguno', 'proveedor', 'etiqueta', 'especificacion'))");
    }
};
