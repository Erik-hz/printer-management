<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('lugares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zona_id')->constrained('zonas');
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['zona_id', 'nombre']);
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lugar_id')->constrained('lugares');
            $table->string('nombre', 100);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['lugar_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
        Schema::dropIfExists('lugares');
        Schema::dropIfExists('zonas');
    }
};