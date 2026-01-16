<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMonedasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('monedas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('codigo', 3)->unique();
            $table->string('simbolo', 3);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // Insertar monedas por defecto
        DB::table('monedas')->insert([
            [
                'nombre' => 'Peso Argentino',
                'codigo' => 'ARS',
                'simbolo' => '$',
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'Dólar Estadounidense',
                'codigo' => 'USD',
                'simbolo' => 'USD',
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('monedas');
    }
}
