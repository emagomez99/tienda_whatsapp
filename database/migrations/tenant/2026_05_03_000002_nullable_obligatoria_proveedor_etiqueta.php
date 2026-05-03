<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE proveedor_etiqueta ALTER COLUMN obligatoria DROP DEFAULT');
        DB::statement('ALTER TABLE proveedor_etiqueta ALTER COLUMN obligatoria DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE proveedor_etiqueta SET obligatoria = false WHERE obligatoria IS NULL');
        DB::statement('ALTER TABLE proveedor_etiqueta ALTER COLUMN obligatoria SET NOT NULL');
        DB::statement('ALTER TABLE proveedor_etiqueta ALTER COLUMN obligatoria SET DEFAULT true');
    }
};
