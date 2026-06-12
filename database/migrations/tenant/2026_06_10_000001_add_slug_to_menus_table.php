<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('nombre');
        });

        // Generar slugs para menus existentes
        $menus = DB::table('menus')->get();
        foreach ($menus as $menu) {
            $base = Str::slug($menu->nombre) ?: 'menu';
            $slug = $base;
            $i = 2;
            while (DB::table('menus')->where('slug', $slug)->where('id', '!=', $menu->id)->exists()) {
                $slug = $base . '-' . $i++;
            }
            DB::table('menus')->where('id', $menu->id)->update(['slug' => $slug]);
        }

        Schema::table('menus', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
