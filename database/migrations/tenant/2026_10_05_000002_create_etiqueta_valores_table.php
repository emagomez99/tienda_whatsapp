<?php

use App\Models\EtiquetaValor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lista de valores por etiqueta, y cada fila de producto_etiqueta apuntando a uno.
 *
 * Hasta ahora el valor era sólo el texto tipeado en cada producto, sin ningún lugar
 * donde ver o corregir los valores de una etiqueta. Con esta tabla cada valor existe
 * una vez y los productos lo referencian (ver EtiquetaValor y ProductoEtiqueta).
 *
 * La columna de texto producto_etiqueta.valor queda, igual al valor resuelto, para
 * que lo que hoy la lee no cambie.
 *
 * El relleno se hace en PHP y no en SQL para normalizar exactamente igual que
 * EtiquetaValor::normalizar (lower() de Postgres depende del locale). Cuando dos
 * textos son el mismo valor se queda la forma más usada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etiqueta_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etiqueta_id')->constrained('etiquetas')->onDelete('cascade');
            $table->string('valor');
            $table->string('normalizado')->comment('Clave de comparación: minúsculas y espacios simples');
            $table->timestamps();

            $table->unique(['etiqueta_id', 'normalizado']);
        });

        Schema::table('producto_etiqueta', function (Blueprint $table) {
            $table->foreignId('etiqueta_valor_id')->nullable()->constrained('etiqueta_valores')->onDelete('cascade');
        });

        $this->rellenar();

        DB::statement('ALTER TABLE producto_etiqueta ALTER COLUMN etiqueta_valor_id SET NOT NULL');

        // Los filtros pasan a buscar por etiqueta_valor_id (Producto::scopeConEtiqueta),
        // así que el índice por lower(valor) de la migración anterior queda sin uso.
        Schema::table('producto_etiqueta', function (Blueprint $table) {
            $table->index('etiqueta_valor_id');
        });
        DB::statement('DROP INDEX IF EXISTS producto_etiqueta_etiqueta_id_lower_valor_index');
    }

    private function rellenar(): void
    {
        $usados = DB::table('producto_etiqueta')
            ->select('etiqueta_id', 'valor', DB::raw('count(*) as usos'))
            ->groupBy('etiqueta_id', 'valor')
            ->orderByDesc('usos')
            ->get();

        // Primero la forma más usada de cada valor: es la que queda como nombre.
        $ids = [];
        foreach ($usados as $fila) {
            $clave = $fila->etiqueta_id . '|' . EtiquetaValor::normalizar($fila->valor);

            if (!isset($ids[$clave])) {
                $ids[$clave] = DB::table('etiqueta_valores')->insertGetId([
                    'etiqueta_id' => $fila->etiqueta_id,
                    'valor'       => EtiquetaValor::limpiar($fila->valor),
                    'normalizado' => EtiquetaValor::normalizar($fila->valor),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            DB::table('producto_etiqueta')
                ->where('etiqueta_id', $fila->etiqueta_id)
                ->where('valor', $fila->valor)
                ->update(['etiqueta_valor_id' => $ids[$clave]]);
        }

        // El texto de cada fila pasa a ser el del valor que le tocó.
        DB::statement('
            UPDATE producto_etiqueta pe
               SET valor = ev.valor
              FROM etiqueta_valores ev
             WHERE ev.id = pe.etiqueta_valor_id
               AND pe.valor <> ev.valor
        ');
    }

    public function down(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS producto_etiqueta_etiqueta_id_lower_valor_index ON producto_etiqueta (etiqueta_id, lower(valor))');

        Schema::table('producto_etiqueta', function (Blueprint $table) {
            $table->dropIndex(['etiqueta_valor_id']);
            $table->dropConstrainedForeignId('etiqueta_valor_id');
        });

        Schema::dropIfExists('etiqueta_valores');
    }
};
