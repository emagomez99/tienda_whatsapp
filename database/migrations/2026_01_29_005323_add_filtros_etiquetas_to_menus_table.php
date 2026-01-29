<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFiltrosEtiquetasToMenusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('menus', function (Blueprint $table) {
            // JSON array con IDs de etiquetas para usar como filtros en cascada
            // Ej: [1, 2, 3] = Fabricante, Aplicación, Modelo
            $table->json('filtros_etiquetas')->nullable()->after('activo');
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
            $table->dropColumn('filtros_etiquetas');
        });
    }
}
