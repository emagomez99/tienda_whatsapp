<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Variantes: lo que el cliente elige al comprar (Talle M, Color Rojo), cada una con
 * su propio stock. Ver App\Models\Variante.
 *
 * - producto_variantes: una por combinación, con su stock, y si se quiere su código
 *   y su precio (vacío = el del producto).
 * - producto_variante_valores: qué valor de cada característica es la variante
 *   (Talle = M, Color = Rojo). Apunta a etiqueta_valores, como las características.
 * - stock_movimientos y pedido_productos dicen de qué variante fue: sin eso no se
 *   sabe qué talle devolver al cancelar un pedido.
 *
 * El stock del producto sigue existiendo: es la suma de sus variantes, y así todo lo
 * que ya lo mira (listados, "sin stock", el sitemap) sigue andando igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('codigo', 100)->nullable();
            $table->decimal('precio', 14, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->boolean('activa')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['producto_id', 'orden']);
        });
        DB::statement("CREATE UNIQUE INDEX producto_variantes_codigo_unico ON producto_variantes (lower(trim(codigo))) WHERE codigo IS NOT NULL AND trim(codigo) <> ''");

        Schema::create('producto_variante_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('producto_variantes')->cascadeOnDelete();
            $table->foreignId('etiqueta_id')->constrained('etiquetas')->cascadeOnDelete();
            $table->foreignId('etiqueta_valor_id')->constrained('etiqueta_valores')->restrictOnDelete();

            $table->unique(['variante_id', 'etiqueta_id']);
            $table->index('etiqueta_valor_id');
        });

        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
        });

        Schema::table('pedido_productos', function (Blueprint $table) {
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pedido_productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variante_id');
        });
        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variante_id');
        });
        Schema::dropIfExists('producto_variante_valores');
        Schema::dropIfExists('producto_variantes');
    }
};
