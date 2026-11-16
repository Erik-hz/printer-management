<?php

use App\Modules\Access\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [LoginController::class, 'mostrar'])->name('login');
    Route::post('/ingresar', [LoginController::class, 'ingresar'])->name('login.enviar');
});

Route::middleware('auth')->group(function () {
    Route::get('/inicio', fn () => view('inicio'))->name('inicio');
    Route::post('/salir', [LoginController::class, 'salir'])->name('logout');
});