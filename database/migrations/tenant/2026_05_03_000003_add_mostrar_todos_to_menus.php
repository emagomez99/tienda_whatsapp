<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddMostrarTodosToMenus extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE menus ADD COLUMN mostrar_todos BOOLEAN NOT NULL DEFAULT TRUE');
    }

    public function down()
    {
        DB::statement('ALTER TABLE menus DROP COLUMN mostrar_todos');
    }
}
