<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ejecuciones_lecturas_snmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impresora_id')->constrained('impresoras');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 20);
            $table->text('salida')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('iniciada_en')->nullable();
            $table->timestamp('finalizada_en')->nullable();
            $table->timestamps();

            $table->index(['impresora_id', 'estado'], 'ix_ejecuciones_snmp_impresora_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ejecuciones_lecturas_snmp');
    }
};
