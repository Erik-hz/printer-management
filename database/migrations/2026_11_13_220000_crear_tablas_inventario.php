<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('existencias', function (Blueprint $table) {
            $table->foreignId('consumible_id')->primary()->constrained('consumibles');
            $table->integer('cantidad')->default(0);
            $table->dateTime('updated_at')->useCurrent();
        });

        DB::statement(
            'ALTER TABLE existencias ADD CONSTRAINT ck_existencias_cantidad
             CHECK (cantidad >= 0)'
        );
        DB::statement(
            'INSERT INTO existencias (consumible_id, cantidad, updated_at)
             SELECT id, 0, CURRENT_TIMESTAMP FROM consumibles'
        );

        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumible_id')->constrained('consumibles');
            $table->string('tipo', 10);
            $table->integer('cantidad');
            $table->integer('saldo_resultante');
            $table->foreignId('detalle_compra_id')->nullable()->constrained('detalle_compra');
            $table->foreignId('impresora_id')->nullable()->constrained('impresoras');
            $table->string('ticket', 100)->nullable();
            $table->string('motivo', 500)->nullable();
            $table->foreignId('usuario_id')->constrained('users');
            $table->foreignId('importacion_id')->nullable()->constrained('importaciones');
            $table->dateTime('fecha_movimiento');
            $table->dateTime('created_at')->useCurrent();

            $table->index(['consumible_id', 'fecha_movimiento'], 'ix_movimientos_consumible_fecha');
            $table->index(['impresora_id', 'fecha_movimiento'], 'ix_movimientos_impresora_fecha');
            $table->index('detalle_compra_id', 'ix_movimientos_detalle_compra');
            $table->index('usuario_id', 'ix_movimientos_usuario');
            $table->index('importacion_id', 'ix_movimientos_importacion');
        });

        DB::statement(
            "ALTER TABLE movimientos_inventario ADD CONSTRAINT ck_movimientos_tipo_cantidad
             CHECK (
                (tipo = 'entrada' AND cantidad > 0)
                OR (tipo = 'salida' AND cantidad < 0)
                OR (tipo = 'ajuste' AND cantidad <> 0)
             )"
        );
        DB::statement(
            'ALTER TABLE movimientos_inventario ADD CONSTRAINT ck_movimientos_saldo
             CHECK (saldo_resultante >= 0)'
        );
        DB::statement(
            "ALTER TABLE movimientos_inventario ADD CONSTRAINT ck_movimientos_motivo_ajuste
             CHECK (
                tipo <> 'ajuste'
                OR (
                    motivo IS NOT NULL
                    AND ".$this->longitudMotivoSql().' > 0
                )
             )'
        );
        DB::statement(
            "ALTER TABLE movimientos_inventario ADD CONSTRAINT ck_movimientos_detalle_entrada
             CHECK (detalle_compra_id IS NULL OR tipo = 'entrada')"
        );
        DB::statement(
            "ALTER TABLE movimientos_inventario ADD CONSTRAINT ck_movimientos_impresora_salida
             CHECK (impresora_id IS NULL OR tipo = 'salida')"
        );

        if (DB::connection()->getDriverName() === 'sqlsrv') {
            DB::unprepared(
                "CREATE TRIGGER dbo.tr_movimientos_inventario_inmutable
                 ON dbo.movimientos_inventario
                 AFTER UPDATE, DELETE
                 AS
                 BEGIN
                     SET NOCOUNT ON;
                     THROW 51000, 'Los movimientos de inventario son inmutables.', 1;
                 END"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            DB::unprepared(
                "IF OBJECT_ID(N'dbo.tr_movimientos_inventario_inmutable', N'TR') IS NOT NULL
                 DROP TRIGGER dbo.tr_movimientos_inventario_inmutable"
            );
        }

        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('existencias');
    }

    private function longitudMotivoSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlsrv'
            ? 'LEN(LTRIM(RTRIM(motivo)))'
            : 'LENGTH(TRIM(motivo))';
    }
};
