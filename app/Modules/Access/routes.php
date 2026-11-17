<?php

use App\Modules\Access\Http\Controllers\LoginController;
use App\Modules\Access\Http\Controllers\PerfilController;
use App\Modules\Access\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [LoginController::class, 'mostrar'])->name('login');
    Route::post('/ingresar', [LoginController::class, 'ingresar'])->name('login.enviar');
});

Route::middleware('auth')->group(function () {
    Route::get('/inicio', fn () => view('inicio'))->name('inicio');
    Route::post('/salir', [LoginController::class, 'salir'])->name('logout');

    // Cualquier rol activo cambia su propia contraseña.
    Route::get('/perfil/clave', [PerfilController::class, 'edit'])->name('perfil.clave');
    Route::put('/perfil/clave', [PerfilController::class, 'update'])->name('perfil.clave.actualizar');
});

// Administración de usuarios: solo administrador.
Route::middleware(['auth', 'can:gestionar-usuarios'])->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
    Route::put('/usuarios/{usuario}/clave', [UsuarioController::class, 'restablecerClave'])->name('usuarios.clave');
});