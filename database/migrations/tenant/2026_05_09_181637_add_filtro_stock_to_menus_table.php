<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFiltroStockToMenusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('filtro_stock', 30)->default('todos')->after('activo');
        });

        // Migrar solo_con_stock → filtro_stock
        \DB::table('menus')->where('solo_con_stock', true)->update(['filtro_stock' => 'con_stock']);

        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('solo_con_stock');
        });
    }

    public function down()
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->boolean('solo_con_stock')->default(false)->after('activo');
        });

        \DB::table('menus')->where('filtro_stock', 'con_stock')->update(['solo_con_stock' => true]);

        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('filtro_stock');
        });
    }
}
