<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pedidos ALTER COLUMN direccion DROP NOT NULL');
        DB::statement('ALTER TABLE pedidos ALTER COLUMN localidad DROP NOT NULL');
        DB::statement('ALTER TABLE pedidos ALTER COLUMN provincia DROP NOT NULL');
        DB::statement('ALTER TABLE pedidos ALTER COLUMN cp DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pedidos ALTER COLUMN direccion SET NOT NULL');
        DB::statement('ALTER TABLE pedidos ALTER COLUMN localidad SET NOT NULL');
        DB::statement('ALTER TABLE pedidos ALTER COLUMN provincia SET NOT NULL');
        DB::statement('ALTER TABLE pedidos ALTER COLUMN cp SET NOT NULL');
    }
};
