<?php

use App\Modules\Monitoring\Http\Controllers\Api\LecturaApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('monitoring.local')->post(
    '/monitoring/lecturas',
    [LecturaApiController::class, 'store']
);
