<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Tabla productos
        Schema::table('productos', function (Blueprint $table) {
            // Filtro principal: disponible = true (usado en todas las queries)
            $table->index('disponible');

            // Filtro compuesto: disponible + stock (filtro de stock)
            $table->index(['disponible', 'stock']);

            // Filtro compuesto: disponible + por_encargue (productos sin stock pero por encargue)
            $table->index(['disponible', 'por_encargue']);

            // Búsqueda por descripción (LIKE '%...%' no usa index, pero sí 'valor%')
            $table->index('descripcion');

            // Búsqueda por código de proveedor
            $table->index('id_proveedor');
        });

        // Tabla producto_etiqueta
        Schema::table('producto_etiqueta', function (Blueprint $table) {
            // Filtro en cascada: etiqueta + valor (query más frecuente)
            $table->index(['etiqueta_id', 'valor']);
        });

        // Tabla producto_especificaciones
        Schema::table('producto_especificaciones', function (Blueprint $table) {
            // Filtro por especificación con valor
            $table->index(['producto_id', 'valor']);
        });

        // Tabla etiquetas
        Schema::table('etiquetas', function (Blueprint $table) {
            // Filtro de etiquetas visibles para usuarios
            $table->index('visible_usuarios');
        });

        // Tabla menus
        Schema::table('menus', function (Blueprint $table) {
            // Consulta de menús activos ordenados
            $table->index(['activo', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['disponible']);
            $table->dropIndex(['disponible', 'stock']);
            $table->dropIndex(['disponible', 'por_encargue']);
            $table->dropIndex(['descripcion']);
            $table->dropIndex(['id_proveedor']);
        });

        Schema::table('producto_etiqueta', function (Blueprint $table) {
            $table->dropIndex(['etiqueta_id', 'valor']);
        });

        Schema::table('producto_especificaciones', function (Blueprint $table) {
            $table->dropIndex(['producto_id', 'valor']);
        });

        Schema::table('etiquetas', function (Blueprint $table) {
            $table->dropIndex(['visible_usuarios']);
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->dropIndex(['activo', 'orden']);
        });
    }
}
