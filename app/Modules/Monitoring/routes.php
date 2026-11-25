<?php

use App\Modules\Monitoring\Http\Controllers\LecturaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:ver-monitoreo'])->group(function (): void {
    Route::get('/lecturas', [LecturaController::class, 'index'])->name('lecturas.index');
    Route::post('/lecturas/comprobar-conectividad', [LecturaController::class, 'comprobarConectividad'])->name('lecturas.conectividad');
});

Route::middleware(['auth', 'can:registrar-lecturas'])->group(function (): void {
    Route::get('/lecturas/crear', [LecturaController::class, 'create'])->name('lecturas.create');
    Route::post('/lecturas', [LecturaController::class, 'store'])->name('lecturas.store');
    Route::post('/lecturas/actualizar-snmp', [LecturaController::class, 'actualizar'])->name('lecturas.actualizar');
    Route::post('/lecturas/actualizar-snmp/todas', [LecturaController::class, 'actualizarTodas'])->name('lecturas.actualizar-todas');
});
