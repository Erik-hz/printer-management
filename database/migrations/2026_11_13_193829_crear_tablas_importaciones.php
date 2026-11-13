<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_nombre', 255);
            $table->string('hoja', 100)->nullable();
            $table->string('estado', 20)->default('en_proceso');
            $table->unsignedInteger('filas_leidas')->default(0);
            $table->unsignedInteger('filas_importadas')->default(0);
            $table->unsignedInteger('filas_con_error')->default(0);
            $table->foreignId('usuario_id')->constrained('users');
            $table->dateTime('iniciada_en');
            $table->dateTime('finalizada_en')->nullable();
            $table->timestamps();
        });

        DB::statement(
            "ALTER TABLE importaciones ADD CONSTRAINT ck_importaciones_estado
             CHECK (estado IN ('en_proceso', 'completada', 'con_errores', 'fallida'))"
        );

        Schema::create('errores_importacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_id')->constrained('importaciones');
            $table->unsignedInteger('fila');
            $table->string('columna', 100)->nullable();
            $table->string('valor', 500)->nullable();
            $table->string('mensaje', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('errores_importacion');
        Schema::dropIfExists('importaciones');
    }
};