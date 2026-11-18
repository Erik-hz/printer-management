<?php

use App\Modules\Purchasing\Http\Controllers\CompraController;
use App\Modules\Purchasing\Http\Controllers\PrecioConsumibleController;
use App\Modules\Purchasing\Http\Controllers\ProveedorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:gestionar-compras'])->group(function (): void {
    Route::get('/proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
    Route::get('/proveedores/crear', [ProveedorController::class, 'create'])->name('proveedores.create');
    Route::post('/proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
    Route::get('/proveedores/{proveedor}/editar', [ProveedorController::class, 'edit'])->name('proveedores.edit');
    Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])->name('proveedores.update');
    Route::delete('/proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->name('proveedores.destroy');

    Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
    Route::get('/compras/crear', [CompraController::class, 'create'])->name('compras.create');
    Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
    Route::get('/compras/{compra}', [CompraController::class, 'show'])->name('compras.show');
    Route::post('/compras/{compra}/detalles/{detalle}/recepciones', [CompraController::class, 'recibir'])
        ->name('compras.recibir');
    Route::post('/compras/{compra}/cancelar', [CompraController::class, 'cancelar'])->name('compras.cancelar');

    Route::get('/precios-consumibles', [PrecioConsumibleController::class, 'index'])->name('precios-consumibles.index');
    Route::post('/precios-consumibles', [PrecioConsumibleController::class, 'store'])->name('precios-consumibles.store');
    Route::post('/precios-consumibles/{precio}/cerrar', [PrecioConsumibleController::class, 'cerrar'])
        ->name('precios-consumibles.cerrar');
});
