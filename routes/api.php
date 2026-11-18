<?php

use App\Modules\Monitoring\Http\Controllers\Api\LecturaApiController;
use Illuminate\Support\Facades\Route;

// Orden: primero la red local, luego el límite de peticiones, al final el token.
Route::middleware(['monitoring.local', 'throttle:monitoreo', 'monitoring.token'])->post(
    '/monitoring/lecturas',
    [LecturaApiController::class, 'store']
);
