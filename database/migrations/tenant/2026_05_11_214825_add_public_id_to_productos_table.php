<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AddPublicIdToProductosTable extends Migration
{
    public function up()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->uuid('public_id')->nullable()->after('id');
        });

        DB::table('productos')->orderBy('id')->each(function ($producto) {
            DB::table('productos')
                ->where('id', $producto->id)
                ->update(['public_id' => Str::uuid()->toString()]);
        });

        DB::statement('ALTER TABLE productos ALTER COLUMN public_id SET NOT NULL');
        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_public_id_unique UNIQUE (public_id)');
    }

    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });
    }
}
