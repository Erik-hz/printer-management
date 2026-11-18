<?php

use App\Modules\Inventory\Http\Controllers\ConsumibleController;
use App\Modules\Inventory\Http\Controllers\MovimientoController;
use Illuminate\Support\Facades\Route;

// Consulta: cualquier rol activo.
Route::middleware(['auth', 'can:ver-inventario'])->group(function () {
    Route::get('/consumibles', [ConsumibleController::class, 'index'])->name('consumibles.index');
    Route::get('/movimientos', [MovimientoController::class, 'index'])->name('movimientos.index');
});

// Gestión de consumibles: administrador y responsable de inventario.
Route::middleware(['auth', 'can:gestionar-consumibles'])->group(function () {
    Route::get('/consumibles/crear', [ConsumibleController::class, 'create'])->name('consumibles.create');
    Route::post('/consumibles', [ConsumibleController::class, 'store'])->name('consumibles.store');
    Route::get('/consumibles/{consumible}/editar', [ConsumibleController::class, 'edit'])->name('consumibles.edit');
    Route::put('/consumibles/{consumible}', [ConsumibleController::class, 'update'])->name('consumibles.update');
    Route::delete('/consumibles/{consumible}', [ConsumibleController::class, 'destroy'])->name('consumibles.destroy');
});

// Registro de movimientos: administrador y responsable de inventario.
// No hay rutas para editar ni borrar movimientos: el libro es inmutable.
Route::middleware(['auth', 'can:registrar-movimientos'])->group(function () {
    Route::get('/movimientos/crear', [MovimientoController::class, 'create'])->name('movimientos.create');
    Route::post('/movimientos', [MovimientoController::class, 'store'])->name('movimientos.store');
});