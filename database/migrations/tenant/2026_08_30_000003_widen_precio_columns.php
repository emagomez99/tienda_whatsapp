<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía las columnas de dinero para que entre un precio en moneda fuerte convertido.
 *
 * numeric(10,2) topa en 99.999.999,99. Con la conversión por cotización eso se agota
 * antes de lo que parece: un producto de U$S 70.000 a 1500 son $105.000.000 y el
 * INSERT falla con "numeric field overflow" -- un error de base en la cara del
 * usuario, no una validación.
 *
 * Las columnas se amplían en cadena porque el precio viaja: producto -> ítem del
 * pedido -> total del pedido. Ampliar sólo la primera cambiaría el punto donde
 * revienta, no el problema. Cada eslabón multiplica, así que cada uno necesita más
 * lugar que el anterior (el total suma todos los ítems del pedido).
 *
 * En Postgres agrandar la precisión de un numeric es un cambio de metadatos: no
 * reescribe la tabla ni puede perder datos. La vuelta atrás sí puede fallar si para
 * entonces hay valores que no entran en el tipo viejo, y está bien que falle.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE productos ALTER COLUMN precio TYPE numeric(14,2)');
        DB::statement('ALTER TABLE pedido_productos ALTER COLUMN precio_unitario TYPE numeric(14,2)');
        DB::statement('ALTER TABLE pedido_productos ALTER COLUMN subtotal TYPE numeric(16,2)');
        DB::statement('ALTER TABLE pedido_totales ALTER COLUMN total TYPE numeric(18,2)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pedido_totales ALTER COLUMN total TYPE numeric(12,2)');
        DB::statement('ALTER TABLE pedido_productos ALTER COLUMN subtotal TYPE numeric(10,2)');
        DB::statement('ALTER TABLE pedido_productos ALTER COLUMN precio_unitario TYPE numeric(10,2)');
        DB::statement('ALTER TABLE productos ALTER COLUMN precio TYPE numeric(10,2)');
    }
};
