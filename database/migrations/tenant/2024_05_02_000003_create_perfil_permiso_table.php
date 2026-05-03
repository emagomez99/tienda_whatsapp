<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePerfilPermisoTable extends Migration
{
    public function up()
    {
        Schema::create('perfil_permiso', function (Blueprint $table) {
            $table->unsignedBigInteger('perfil_id');
            $table->unsignedBigInteger('permiso_id');

            $table->foreign('perfil_id')->references('id')->on('perfiles')->onDelete('cascade');
            $table->foreign('permiso_id')->references('id')->on('permisos')->onDelete('cascade');

            $table->primary(['perfil_id', 'permiso_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('perfil_permiso');
    }
}
