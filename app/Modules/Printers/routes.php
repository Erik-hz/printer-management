<?php

use App\Modules\Printers\Http\Controllers\AreaController;
use App\Modules\Printers\Http\Controllers\ImpresoraController;
use App\Modules\Printers\Http\Controllers\LugarController;
use App\Modules\Printers\Http\Controllers\MarcaController;
use App\Modules\Printers\Http\Controllers\ModeloImpresoraController;
use App\Modules\Printers\Http\Controllers\ZonaController;
use Illuminate\Support\Facades\Route;

// Consulta: cualquier rol activo.
Route::middleware(['auth', 'can:ver-impresoras'])->group(function () {
    Route::get('/impresoras', [ImpresoraController::class, 'index'])->name('impresoras.index');

    Route::get('/zonas', [ZonaController::class, 'index'])->name('zonas.index');
    Route::get('/zonas/{zona}/lugares', [LugarController::class, 'index'])->name('lugares.index');
    Route::get('/lugares/{lugar}/areas', [AreaController::class, 'index'])->name('areas.index');
    Route::get('/marcas', [MarcaController::class, 'index'])->name('marcas.index');
    Route::get('/modelos', [ModeloImpresoraController::class, 'index'])->name('modelos.index');
});

// Gestión de impresoras: solo administrador.
Route::middleware(['auth', 'can:gestionar-impresoras'])->group(function () {
    Route::get('/impresoras/crear', [ImpresoraController::class, 'create'])->name('impresoras.create');
    Route::post('/impresoras', [ImpresoraController::class, 'store'])->name('impresoras.store');
    Route::get('/impresoras/{impresora}/editar', [ImpresoraController::class, 'edit'])->name('impresoras.edit');
    Route::put('/impresoras/{impresora}', [ImpresoraController::class, 'update'])->name('impresoras.update');
    Route::post('/impresoras/{impresora}/retirar', [ImpresoraController::class, 'retirar'])->name('impresoras.retirar');
    Route::delete('/impresoras/{impresora}', [ImpresoraController::class, 'destroy'])->name('impresoras.destroy');
});

// Gestión de catálogos: solo administrador.
Route::middleware(['auth', 'can:gestionar-catalogos'])->group(function () {
    Route::post('/zonas', [ZonaController::class, 'store'])->name('zonas.store');
    Route::put('/zonas/{zona}', [ZonaController::class, 'update'])->name('zonas.update');
    Route::delete('/zonas/{zona}', [ZonaController::class, 'destroy'])->name('zonas.destroy');

    Route::post('/zonas/{zona}/lugares', [LugarController::class, 'store'])->name('lugares.store');
    Route::put('/lugares/{lugar}', [LugarController::class, 'update'])->name('lugares.update');
    Route::delete('/lugares/{lugar}', [LugarController::class, 'destroy'])->name('lugares.destroy');

    Route::post('/lugares/{lugar}/areas', [AreaController::class, 'store'])->name('areas.store');
    Route::put('/areas/{area}', [AreaController::class, 'update'])->name('areas.update');
    Route::delete('/areas/{area}', [AreaController::class, 'destroy'])->name('areas.destroy');

    Route::post('/marcas', [MarcaController::class, 'store'])->name('marcas.store');
    Route::put('/marcas/{marca}', [MarcaController::class, 'update'])->name('marcas.update');
    Route::delete('/marcas/{marca}', [MarcaController::class, 'destroy'])->name('marcas.destroy');

    Route::get('/modelos/crear', [ModeloImpresoraController::class, 'create'])->name('modelos.create');
    Route::post('/modelos', [ModeloImpresoraController::class, 'store'])->name('modelos.store');
    Route::get('/modelos/{modelo}/editar', [ModeloImpresoraController::class, 'edit'])->name('modelos.edit');
    Route::put('/modelos/{modelo}', [ModeloImpresoraController::class, 'update'])->name('modelos.update');
    Route::delete('/modelos/{modelo}', [ModeloImpresoraController::class, 'destroy'])->name('modelos.destroy');
});