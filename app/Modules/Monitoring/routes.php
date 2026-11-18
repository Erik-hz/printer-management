<?php

use App\Modules\Monitoring\Http\Controllers\LecturaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:ver-monitoreo'])->group(function (): void {
    Route::get('/lecturas', [LecturaController::class, 'index'])->name('lecturas.index');
});

Route::middleware(['auth', 'can:registrar-lecturas'])->group(function (): void {
    Route::get('/lecturas/crear', [LecturaController::class, 'create'])->name('lecturas.create');
    Route::post('/lecturas', [LecturaController::class, 'store'])->name('lecturas.store');
});
