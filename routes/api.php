<?php

use App\Modules\Monitoring\Http\Controllers\Api\ImpresoraApiController;
use App\Modules\Monitoring\Http\Controllers\Api\LecturaApiController;
use Illuminate\Support\Facades\Route;

// Orden: primero la red local, luego el límite de peticiones, al final el token.
Route::middleware(['monitoring.local', 'throttle:monitoreo', 'monitoring.token'])->group(function (): void {
    Route::get('/monitoring/impresoras', [ImpresoraApiController::class, 'index']);
    Route::post('/monitoring/lecturas', [LecturaApiController::class, 'store']);
});
