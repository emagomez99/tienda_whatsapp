<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una característica global (la piden todos los productos) puede ser opcional u
 * obligatoria, como en una categoría. Sólo cuenta si global es true.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('etiquetas', function (Blueprint $table) {
            $table->boolean('obligatoria_global')->default(false);
        });
    }

    public function down()
    {
        Schema::table('etiquetas', function (Blueprint $table) {
            $table->dropColumn('obligatoria_global');
        });
    }
};
