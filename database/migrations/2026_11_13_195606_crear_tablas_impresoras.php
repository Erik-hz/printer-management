<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->timestamps();
        });

        Schema::create('modelos_impresora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas');
            $table->string('nombre', 100);
            $table->string('tipo_impresion', 10);
            $table->string('tecnologia', 10);
            $table->boolean('snmp_soportado')->nullable(); // nulo = desconocido (regla 12)
            $table->timestamps();

            $table->unique(['marca_id', 'nombre']);
        });

        DB::statement(
            "ALTER TABLE modelos_impresora ADD CONSTRAINT ck_modelos_tipo_impresion
             CHECK (tipo_impresion IN ('mono', 'color'))"
        );
        DB::statement(
            "ALTER TABLE modelos_impresora ADD CONSTRAINT ck_modelos_tecnologia
             CHECK (tecnologia IN ('laser', 'tinta', 'plotter', 'otra'))"
        );

        Schema::create('estados_impresora', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->boolean('es_operativo')->default(true);
            $table->timestamps();
        });

        Schema::create('impresoras', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->foreignId('modelo_id')->constrained('modelos_impresora');
            $table->foreignId('area_id')->constrained('areas');
            $table->foreignId('estado_id')->constrained('estados_impresora');
            $table->string('tipo_conexion', 5);
            $table->string('direccion_ip', 15)->nullable();
            $table->string('equipo_conectado', 100)->nullable();
            $table->string('numero_serie', 50)->nullable();
            $table->string('observaciones', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Regla 11: la red exige IP y no admite equipo; USB no admite IP.
        // Este CHECK también rechaza cualquier valor de tipo_conexion distinto de red/usb.
        DB::statement(
            "ALTER TABLE impresoras ADD CONSTRAINT ck_impresoras_conexion
             CHECK (
                (tipo_conexion = 'red' AND direccion_ip IS NOT NULL AND equipo_conectado IS NULL)
                OR
                (tipo_conexion = 'usb' AND direccion_ip IS NULL)
             )"
        );

        // Índices únicos filtrados: varias USB sin IP, y reutilizar IP/nombre tras el borrado lógico.
        DB::statement(
            "CREATE UNIQUE INDEX ix_impresoras_ip_unica ON impresoras (direccion_ip)
             WHERE direccion_ip IS NOT NULL AND deleted_at IS NULL"
        );
        DB::statement(
            "CREATE UNIQUE INDEX ix_impresoras_nombre_unico ON impresoras (nombre)
             WHERE deleted_at IS NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('impresoras');
        Schema::dropIfExists('estados_impresora');
        Schema::dropIfExists('modelos_impresora');
        Schema::dropIfExists('marcas');
    }
};