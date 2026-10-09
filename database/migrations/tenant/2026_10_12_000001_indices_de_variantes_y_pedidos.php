<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índices que faltaban, medidos con EXPLAIN ANALYZE en oleomc (30.917 productos,
 * 30.920 movimientos de stock).
 *
 * Postgres no indexa solo las claves foráneas: al borrar una variante, el
 * "ON DELETE SET NULL" de stock_movimientos y pedido_productos recorría las tablas
 * enteras (stock_movimientos: ~5 ms por variante borrada, y crece con cada venta).
 *
 * - stock_movimientos (variante_id), pedido_productos (variante_id): parciales, sólo
 *   las filas con variante, que son las únicas que se buscan por ella.
 * - pedido_productos (pedido_id) y (producto_id): los ítems de un pedido se leen por
 *   pedido_id en cada pantalla de pedidos, y borrar un producto pone en null sus
 *   ítems. Hasta ahora no tenían índice.
 * - producto_variante_valores (etiqueta_id): el unique (variante_id, etiqueta_id) no
 *   sirve para buscar por la característica (no es la primera columna).
 * - productos (created_at, id): "Los más nuevos primero" (OrdenDeProductos). Sin
 *   índice ordenaba la categoría entera: de ~30 ms a ~0,2 ms en oleomc.
 *
 * Además, producto_variante_valores.etiqueta_id pasa de CASCADE a RESTRICT: borrar
 * una característica que usan variantes las dejaba sin valores (una variante sin
 * nombre, con stock, que nadie puede elegir). EtiquetaController::destroy avisa
 * antes con un mensaje; esto es la red por si algo la borra por otro lado.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS stock_movimientos_variante_id_index ON stock_movimientos (variante_id) WHERE variante_id IS NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS pedido_productos_variante_id_index ON pedido_productos (variante_id) WHERE variante_id IS NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS pedido_productos_pedido_id_index ON pedido_productos (pedido_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS pedido_productos_producto_id_index ON pedido_productos (producto_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS producto_variante_valores_etiqueta_id_index ON producto_variante_valores (etiqueta_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS productos_created_at_id_index ON productos (created_at, id)');

        DB::statement('ALTER TABLE producto_variante_valores DROP CONSTRAINT IF EXISTS producto_variante_valores_etiqueta_id_foreign');
        DB::statement('ALTER TABLE producto_variante_valores ADD CONSTRAINT producto_variante_valores_etiqueta_id_foreign
                       FOREIGN KEY (etiqueta_id) REFERENCES etiquetas (id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE producto_variante_valores DROP CONSTRAINT IF EXISTS producto_variante_valores_etiqueta_id_foreign');
        DB::statement('ALTER TABLE producto_variante_valores ADD CONSTRAINT producto_variante_valores_etiqueta_id_foreign
                       FOREIGN KEY (etiqueta_id) REFERENCES etiquetas (id) ON DELETE CASCADE');

        DB::statement('DROP INDEX IF EXISTS productos_created_at_id_index');
        DB::statement('DROP INDEX IF EXISTS producto_variante_valores_etiqueta_id_index');
        DB::statement('DROP INDEX IF EXISTS pedido_productos_producto_id_index');
        DB::statement('DROP INDEX IF EXISTS pedido_productos_pedido_id_index');
        DB::statement('DROP INDEX IF EXISTS pedido_productos_variante_id_index');
        DB::statement('DROP INDEX IF EXISTS stock_movimientos_variante_id_index');
    }
};
