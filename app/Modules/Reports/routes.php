<?php

use App\Modules\Reports\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->get('/inicio', [DashboardController::class, 'index'])->name('inicio');
