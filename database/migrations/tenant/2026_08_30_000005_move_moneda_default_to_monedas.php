<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mueve "moneda por defecto" de Configuración al ABM de monedas.
 *
 * Antes vivía en `configuraciones.moneda_default`, un id suelto guardado como texto
 * en una tabla clave/valor: nada garantizaba que la moneda existiera todavía, y para
 * saber cuál era la preseleccionada había que ir a otra pantalla. Como marca de la
 * propia moneda se borra sola con ella y se ve al lado de la cotización.
 *
 * Es una marca DISTINTA de `es_base`, aunque en un catálogo chico coincidan:
 *   - `es_base`    -- contra qué se miden las cotizaciones. Estructural: hay una sola
 *                     y vale 1 por definición.
 *   - `es_default` -- qué moneda viene preseleccionada al cargar un producto. Es
 *                     comodidad de carga, y puede no haber ninguna.
 *
 * Separarlas importa cuando la tienda cotiza en una moneda y vende en otra: con el
 * peso de base y 30.900 productos en dólares, la base correcta sigue siendo ARS pero
 * la que conviene preseleccionar es USD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monedas', function (Blueprint $table) {
            $table->boolean('es_default')->default(false)->after('es_base');
        });

        $defaultId = $this->elegirDefault();

        if ($defaultId) {
            DB::table('monedas')->where('id', $defaultId)->update([
                'es_default' => true,
                'activa'     => true,
            ]);
        }

        // La configuración vieja se va: dejarla sería tener el mismo dato en dos
        // lugares que pueden discrepar, y el próximo que edite Ajustes la revive.
        DB::table('configuraciones')->where('clave', 'moneda_default')->delete();
    }

    public function down(): void
    {
        $default = DB::table('monedas')->where('es_default', true)->value('id');

        if ($default) {
            DB::table('configuraciones')->updateOrInsert(
                ['clave' => 'moneda_default'],
                [
                    'valor'       => (string) $default,
                    'descripcion' => 'Moneda por defecto para nuevos productos',
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ]
            );
        }

        Schema::table('monedas', function (Blueprint $table) {
            $table->dropColumn('es_default');
        });
    }

    /**
     * Candidata a preseleccionada, en orden: la que ya estaba configurada, el peso
     * argentino, o la base. Se respeta lo que el usuario haya elegido antes -- la
     * migración traslada un dato, no lo redecide.
     *
     * En un tenant nuevo la tabla está vacía (las monedas las crea el seeder, que
     * corre después) y no hay nada que trasladar: ese caso lo resuelve el seeder.
     */
    private function elegirDefault()
    {
        $configurada = DB::table('configuraciones')->where('clave', 'moneda_default')->value('valor');

        if ($configurada && DB::table('monedas')->where('id', (int) $configurada)->exists()) {
            return (int) $configurada;
        }

        $ars = DB::table('monedas')->where('codigo', 'ARS')->value('id');

        if ($ars) {
            return (int) $ars;
        }

        return DB::table('monedas')->where('es_base', true)->value('id');
    }
};
