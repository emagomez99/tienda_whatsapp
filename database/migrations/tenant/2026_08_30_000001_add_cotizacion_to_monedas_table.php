<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cotización de cada moneda contra una moneda base.
 *
 * `cotizacion` es cuántas unidades de la moneda BASE vale 1 unidad de esta moneda, y
 * la base vale 1 por definición. Ver App\Models\Moneda.
 *
 * El default 1 es deliberado: deja a todas las monedas existentes valiendo lo mismo
 * que la base hasta que alguien cargue la cotización real. Ningún precio ya cargado
 * se toca -- los productos quedan en modo manual (ver la migración de productos), así
 * que la cotización todavía no interviene en ningún cálculo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monedas', function (Blueprint $table) {
            $table->decimal('cotizacion', 20, 6)->default(1)->after('simbolo');
            $table->boolean('es_base')->default(false)->after('cotizacion');
        });

        $baseId = $this->elegirBase();

        if ($baseId) {
            DB::table('monedas')->where('id', $baseId)->update([
                'es_base'    => true,
                'cotizacion' => 1,
                'activa'     => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('monedas', function (Blueprint $table) {
            $table->dropColumn(['cotizacion', 'es_base']);
        });
    }

    /**
     * Candidata a base, en orden de preferencia: la moneda por defecto de la tienda,
     * el peso argentino, o la primera que haya. Se consulta la tabla directo y no
     * Configuracion::monedaDefaultId() para que la migración no dependa de un modelo
     * que puede cambiar después de escrita.
     *
     * En un tenant nuevo la tabla está vacía (las monedas las crea el seeder, que
     * corre después) y no hay nada que elegir: ese caso lo resuelve el seeder.
     */
    private function elegirBase()
    {
        $configurada = DB::table('configuraciones')->where('clave', 'moneda_default')->value('valor');

        if ($configurada && DB::table('monedas')->where('id', (int) $configurada)->exists()) {
            return (int) $configurada;
        }

        $ars = DB::table('monedas')->where('codigo', 'ARS')->value('id');

        if ($ars) {
            return (int) $ars;
        }

        return DB::table('monedas')->orderBy('id')->value('id');
    }
};
