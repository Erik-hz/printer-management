<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE importaciones ADD CONSTRAINT ck_importaciones_filas
             CHECK (filas_leidas >= 0 AND filas_importadas >= 0 AND filas_con_error >= 0)'
        );
        DB::statement(
            'ALTER TABLE errores_importacion ADD CONSTRAINT ck_errores_importacion_fila
             CHECK (fila >= 1)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE errores_importacion DROP CONSTRAINT ck_errores_importacion_fila');
        DB::statement('ALTER TABLE importaciones DROP CONSTRAINT ck_importaciones_filas');
    }
};