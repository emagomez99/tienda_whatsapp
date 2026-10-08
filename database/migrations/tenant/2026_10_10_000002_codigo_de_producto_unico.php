<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El código del producto (id_proveedor) es único en cada tienda cuando está cargado
 * (decisión D10 del plan de Categorías): es la clave con la que una planilla de
 * Excel dice qué producto actualizar. Sin mayúsculas ni espacios de más: "CTC-1" y
 * " ctc-1 " son el mismo código.
 *
 * Si una tienda ya tiene códigos repetidos, la migración se detiene y los nombra:
 * hay que corregirlos a mano antes (al 2026-10-10 ninguna tienda tenía).
 */
return new class extends Migration
{
    public function up(): void
    {
        $repetidos = DB::select("
            select lower(trim(id_proveedor)) as codigo, count(*) as cantidad
            from productos
            where id_proveedor is not null and trim(id_proveedor) <> ''
            group by 1 having count(*) > 1
            order by 2 desc limit 10
        ");

        if ($repetidos) {
            $lista = implode(', ', array_map(function ($r) { return "«{$r->codigo}» ({$r->cantidad})"; }, $repetidos));
            throw new RuntimeException("Hay códigos de producto repetidos: {$lista}. Corregilos antes de migrar.");
        }

        DB::statement("
            create unique index productos_codigo_unico on productos (lower(trim(id_proveedor)))
            where id_proveedor is not null and trim(id_proveedor) <> ''
        ");
    }

    public function down(): void
    {
        DB::statement('drop index if exists productos_codigo_unico');
    }
};
