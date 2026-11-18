<?php

use App\Modules\Imports\Http\Controllers\ImportacionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:gestionar-importaciones'])->group(function (): void {
    Route::get('/importaciones', [ImportacionController::class, 'index'])->name('importaciones.index');
    Route::get('/importaciones/crear', [ImportacionController::class, 'create'])->name('importaciones.create');
    Route::post('/importaciones/analizar', [ImportacionController::class, 'analizar'])->name('importaciones.analizar');
    Route::get('/importaciones/vista-previa', [ImportacionController::class, 'vistaPrevia'])->name('importaciones.vista-previa');
    Route::post('/importaciones/ejecutar', [ImportacionController::class, 'ejecutar'])->name('importaciones.ejecutar');
    Route::delete('/importaciones/vista-previa', [ImportacionController::class, 'cancelar'])->name('importaciones.cancelar');
    Route::get('/importaciones/{importacion}', [ImportacionController::class, 'show'])->name('importaciones.show');
});
