<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ejecuciones_lecturas_snmp', function (Blueprint $table) {
            $table->uuid('lote_id')->nullable();
            $table->index(['lote_id', 'estado'], 'ix_ejecuciones_snmp_lote_estado');
        });
    }

    public function down(): void
    {
        Schema::table('ejecuciones_lecturas_snmp', function (Blueprint $table) {
            $table->dropIndex('ix_ejecuciones_snmp_lote_estado');
            $table->dropColumn('lote_id');
        });
    }
};
