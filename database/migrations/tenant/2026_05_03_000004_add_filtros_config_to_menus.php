<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddFiltrosConfigToMenus extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE menus ADD COLUMN filtros_config JSON');
    }

    public function down()
    {
        DB::statement('ALTER TABLE menus DROP COLUMN filtros_config');
    }
}
