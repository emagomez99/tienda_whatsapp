<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ChangeUrlImagenToTextInProductosTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE productos ALTER COLUMN url_imagen TYPE text');
    }

    public function down()
    {
        DB::statement('ALTER TABLE productos ALTER COLUMN url_imagen TYPE varchar(255)');
    }
}
