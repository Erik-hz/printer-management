<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivos_sin_lectura', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->timestamps();
        });

        Schema::create('lecturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impresora_id')->constrained('impresoras');
            $table->dateTime('fecha_lectura', 7);
            $table->string('origen', 15);
            $table->bigInteger('contador_paginas')->nullable();
            $table->foreignId('motivo_sin_lectura_id')->nullable()->constrained('motivos_sin_lectura');
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users');
            $table->foreignId('importacion_id')->nullable()->constrained('importaciones');
            $table->dateTime('created_at')->useCurrent();

            $table->unique(
                ['impresora_id', 'fecha_lectura', 'origen'],
                'uq_lecturas_impresora_fecha_origen'
            );
            $table->index('motivo_sin_lectura_id', 'ix_lecturas_motivo');
            $table->index('usuario_id', 'ix_lecturas_usuario');
            $table->index('importacion_id', 'ix_lecturas_importacion');
        });

        DB::statement(
            "ALTER TABLE lecturas ADD CONSTRAINT ck_lecturas_origen
             CHECK (origen IN ('manual', 'snmp', 'powershell', 'importacion'))"
        );
        DB::statement(
            'ALTER TABLE lecturas ADD CONSTRAINT ck_lecturas_contador_motivo
             CHECK (
                (
                    contador_paginas IS NOT NULL
                    AND contador_paginas >= 0
                    AND motivo_sin_lectura_id IS NULL
                )
                OR (
                    contador_paginas IS NULL
                    AND motivo_sin_lectura_id IS NOT NULL
                )
             )'
        );
        DB::statement(
            "ALTER TABLE lecturas ADD CONSTRAINT ck_lecturas_origen_importacion
             CHECK (
                (origen = 'importacion' AND importacion_id IS NOT NULL)
                OR (origen <> 'importacion' AND importacion_id IS NULL)
             )"
        );
        DB::statement(
            "ALTER TABLE lecturas ADD CONSTRAINT ck_lecturas_usuario_manual
             CHECK (origen <> 'manual' OR usuario_id IS NOT NULL)"
        );

        Schema::create('lecturas_nivel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lectura_id')->constrained('lecturas');
            $table->foreignId('tipo_consumible_id')->constrained('tipos_consumible');
            $table->string('color', 10);
            $table->decimal('nivel', 5, 4)->nullable();

            $table->unique(
                ['lectura_id', 'tipo_consumible_id', 'color'],
                'uq_lecturas_nivel_tipo_color'
            );
            $table->index('tipo_consumible_id', 'ix_lecturas_nivel_tipo');
        });

        DB::statement(
            "ALTER TABLE lecturas_nivel ADD CONSTRAINT ck_lecturas_nivel_color
             CHECK (color IN ('negro', 'cian', 'magenta', 'amarillo', 'tricolor', 'no_aplica'))"
        );
        DB::statement(
            'ALTER TABLE lecturas_nivel ADD CONSTRAINT ck_lecturas_nivel_rango
             CHECK (nivel IS NULL OR (nivel >= 0 AND nivel <= 1))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturas_nivel');
        Schema::dropIfExists('lecturas');
        Schema::dropIfExists('motivos_sin_lectura');
    }
};
