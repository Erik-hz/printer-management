<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('nit', 30)->nullable();
            $table->string('contacto', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 254)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'CREATE UNIQUE INDEX ix_proveedores_nombre_unico ON proveedores (nombre)
             WHERE deleted_at IS NULL'
        );

        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('usuario_id')->constrained('users');
            $table->string('numero_documento', 100)->nullable();
            $table->date('fecha_orden');
            $table->string('estado', 25)->default('ordenada');
            $table->char('moneda', 3)->default('BOB');
            $table->boolean('incluye_iva');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('usuario_id', 'ix_compras_usuario');
            $table->index(['estado', 'fecha_orden'], 'ix_compras_estado_fecha');
        });

        DB::statement(
            "ALTER TABLE compras ADD CONSTRAINT ck_compras_estado
             CHECK (estado IN ('ordenada', 'parcialmente_recibida', 'recibida', 'cancelada'))"
        );
        DB::statement(
            'CREATE UNIQUE INDEX ix_compras_documento_proveedor
             ON compras (proveedor_id, numero_documento)
             WHERE numero_documento IS NOT NULL'
        );

        Schema::create('detalle_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras');
            $table->foreignId('consumible_id')->constrained('consumibles');
            $table->integer('cantidad');
            $table->integer('cantidad_recibida')->default(0);
            $table->decimal('precio_unitario', 12, 2);
            $table->timestamps();

            $table->unique(['compra_id', 'consumible_id'], 'uq_detalle_compra_consumible');
            $table->index('consumible_id', 'ix_detalle_compra_consumible');
        });

        DB::statement(
            'ALTER TABLE detalle_compra ADD CONSTRAINT ck_detalle_compra_cantidad
             CHECK (cantidad > 0)'
        );
        DB::statement(
            'ALTER TABLE detalle_compra ADD CONSTRAINT ck_detalle_compra_recibida
             CHECK (cantidad_recibida >= 0 AND cantidad_recibida <= cantidad)'
        );
        DB::statement(
            'ALTER TABLE detalle_compra ADD CONSTRAINT ck_detalle_compra_precio
             CHECK (precio_unitario >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_compra');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('proveedores');
    }
};
