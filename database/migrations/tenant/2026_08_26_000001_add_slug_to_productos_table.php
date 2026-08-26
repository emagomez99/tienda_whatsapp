<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Agrega el slug decorativo que acompaña al id en la URL pública del producto
 * (/producto/{slug}-{id}, ver Producto::getRouteKey()).
 *
 * A propósito NO lleva índice único ni NOT NULL: el slug no identifica al producto,
 * sólo lo describe. Quien resuelve la ruta es el id del final. Eso permite que dos
 * productos compartan texto (en el catálogo de oleomc hay descripciones repetidas
 * hasta 185 veces) sin sufijos -2/-185 ni loops de desambiguación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('descripcion');
        });

        // Backfill directo: sin chequear colisiones, porque no importan.
        // chunkById para no traer catálogos grandes enteros a memoria.
        DB::table('productos')
            ->select('id', 'descripcion')
            ->orderBy('id')
            ->chunkById(1000, function ($productos) {
                foreach ($productos as $producto) {
                    // Replica Producto::generarSlug(); va inline a propósito, para que la
                    // migración no dependa de un modelo que puede cambiar más adelante.
                    // rtrim: Str::limit puede cortar sobre un guión y dejarlo colgando.
                    $slug = rtrim(Str::limit(Str::slug($producto->descripcion), 100, ''), '-');

                    if ($slug === '') {
                        $slug = null;
                    } elseif (ctype_digit($slug)) {
                        // Un slug de puros dígitos chocaría con la ruta corta /producto/{id}.
                        $slug = 'producto-' . $slug;
                    }

                    DB::table('productos')->where('id', $producto->id)->update(['slug' => $slug]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
