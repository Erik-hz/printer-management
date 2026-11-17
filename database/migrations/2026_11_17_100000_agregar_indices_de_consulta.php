<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impresoras', function (Blueprint $table) {
            $table->index('area_id', 'ix_impresoras_area');
            $table->index('modelo_id', 'ix_impresoras_modelo');
            $table->index('estado_id', 'ix_impresoras_estado');
        });

        Schema::table('modelo_consumible', function (Blueprint $table) {
            $table->index('consumible_id', 'ix_modelo_consumible_consumible');
        });
    }

    public function down(): void
    {
        Schema::table('modelo_consumible', function (Blueprint $table) {
            $table->dropIndex('ix_modelo_consumible_consumible');
        });

        Schema::table('impresoras', function (Blueprint $table) {
            $table->dropIndex('ix_impresoras_estado');
            $table->dropIndex('ix_impresoras_modelo');
            $table->dropIndex('ix_impresoras_area');
        });
    }
};