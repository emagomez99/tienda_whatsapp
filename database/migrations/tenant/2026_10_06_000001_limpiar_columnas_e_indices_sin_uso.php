<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Limpieza de restos que ya no usa nadie, medidos en oleomc (30.917 productos).
 *
 * - menus.mostrar_todos: lo reemplazó filtros_config (un "Todos" por filtro); no lo
 *   lee ni lo escribe ningún código.
 * - productos_disponible_index: redundante, (disponible, stock) y
 *   (disponible, por_encargue) ya empiezan por disponible.
 * - etiquetas_visible_usuarios_index: la tabla tiene un puñado de filas; Postgres la
 *   lee entera igual.
 * - producto_etiqueta_etiqueta_id_valor_index: los filtros por valor pasaron a
 *   etiqueta_valor_id (Producto::scopeConEtiqueta) y el autocompletado a la tabla
 *   etiqueta_valores; lo único que mira el texto es el buscador, con ilike '%..%',
 *   que un btree no puede usar.
 * - producto_especificaciones (producto_id, valor), 18 MB en oleomc: el prefijo
 *   producto_id ya lo cubre el unique (producto_id, clave), y el valor sólo se busca
 *   con ilike '%..%'. En su lugar va (clave, valor), que es lo que consulta el
 *   autocompletado de valores de una especificación: de 45 ms a ~2 ms.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE menus DROP COLUMN IF EXISTS mostrar_todos');

        DB::statement('DROP INDEX IF EXISTS productos_disponible_index');
        DB::statement('DROP INDEX IF EXISTS etiquetas_visible_usuarios_index');
        DB::statement('DROP INDEX IF EXISTS producto_etiqueta_etiqueta_id_valor_index');
        DB::statement('DROP INDEX IF EXISTS producto_especificaciones_producto_id_valor_index');

        DB::statement('CREATE INDEX IF NOT EXISTS producto_especificaciones_clave_valor_index ON producto_especificaciones (clave, valor)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS producto_especificaciones_clave_valor_index');

        DB::statement('CREATE INDEX IF NOT EXISTS producto_especificaciones_producto_id_valor_index ON producto_especificaciones (producto_id, valor)');
        DB::statement('CREATE INDEX IF NOT EXISTS producto_etiqueta_etiqueta_id_valor_index ON producto_etiqueta (etiqueta_id, valor)');
        DB::statement('CREATE INDEX IF NOT EXISTS etiquetas_visible_usuarios_index ON etiquetas (visible_usuarios)');
        DB::statement('CREATE INDEX IF NOT EXISTS productos_disponible_index ON productos (disponible)');

        DB::statement('ALTER TABLE menus ADD COLUMN IF NOT EXISTS mostrar_todos BOOLEAN NOT NULL DEFAULT TRUE');
    }
};
