<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_consumible', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->timestamps();
        });

        Schema::create('consumibles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_consumible_id')->constrained('tipos_consumible');
            $table->string('codigo', 50);
            $table->string('descripcion', 255)->nullable();
            $table->string('color', 10);
            $table->integer('rendimiento_paginas')->nullable();
            $table->integer('stock_minimo')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            "ALTER TABLE consumibles ADD CONSTRAINT ck_consumibles_color
             CHECK (color IN ('negro', 'cian', 'magenta', 'amarillo', 'tricolor', 'no_aplica'))"
        );
        DB::statement(
            "ALTER TABLE consumibles ADD CONSTRAINT ck_consumibles_stock_minimo
             CHECK (stock_minimo >= 0)"
        );
        DB::statement(
            "ALTER TABLE consumibles ADD CONSTRAINT ck_consumibles_rendimiento
             CHECK (rendimiento_paginas IS NULL OR rendimiento_paginas > 0)"
        );

        // Código único entre los consumibles no borrados (la collation ya ignora mayúsculas).
        DB::statement(
            "CREATE UNIQUE INDEX ix_consumibles_codigo_unico ON consumibles (codigo)
             WHERE deleted_at IS NULL"
        );

        Schema::create('modelo_consumible', function (Blueprint $table) {
            $table->foreignId('modelo_id')->constrained('modelos_impresora');
            $table->foreignId('consumible_id')->constrained('consumibles');

            $table->primary(['modelo_id', 'consumible_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modelo_consumible');
        Schema::dropIfExists('consumibles');
        Schema::dropIfExists('tipos_consumible');
    }
};