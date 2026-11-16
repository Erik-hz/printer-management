<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precios_consumible', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumible_id')->constrained('consumibles');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores');
            $table->decimal('precio', 12, 2);
            $table->char('moneda', 3)->default('BOB');
            $table->boolean('incluye_iva');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->index(['consumible_id', 'vigente_desde'], 'ix_precios_consumible_vigencia');
            $table->index('proveedor_id', 'ix_precios_proveedor');
        });

        DB::statement(
            'ALTER TABLE precios_consumible ADD CONSTRAINT ck_precios_precio
             CHECK (precio >= 0)'
        );
        DB::statement(
            'ALTER TABLE precios_consumible ADD CONSTRAINT ck_precios_vigencia
             CHECK (vigente_hasta IS NULL OR vigente_hasta >= vigente_desde)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_consumible');
    }
};