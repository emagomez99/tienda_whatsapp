<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de compra del producto y modo de armado del precio de venta.
 *
 * Todo lo nuevo es nullable y `modo_precio_venta` arranca en 'manual', así que los
 * productos ya cargados quedan exactamente como estaban: su `precio` y su `moneda_id`
 * (que es la moneda de VENTA) no se tocan, y sin datos de compra el recálculo masivo
 * ni los mira. Cargar la compra es opcional también hacia adelante: se puede seguir
 * poniendo el precio de venta a mano y listo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('moneda_compra_id')->nullable()->after('moneda_id')
                  ->constrained('monedas')->onDelete('set null');
            $table->decimal('precio_compra', 14, 2)->nullable()->after('moneda_compra_id');
            $table->decimal('margen_ganancia', 7, 2)->nullable()->after('precio_compra');
            $table->string('modo_precio_venta', 10)->default('manual')->after('margen_ganancia');

            $table->index('moneda_compra_id');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['moneda_compra_id']);
            $table->dropForeign(['moneda_compra_id']);
            $table->dropColumn(['moneda_compra_id', 'precio_compra', 'margen_ganancia', 'modo_precio_venta']);
        });
    }
};
