<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFiltrosRequeridosToMenusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('menus', function (Blueprint $table) {
            // true = filtros compuestos (todos requeridos para búsqueda)
            // false = filtros individuales (cada uno actúa por separado)
            $table->boolean('filtros_requeridos')->default(false)->after('filtros_etiquetas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('filtros_requeridos');
        });
    }
}
