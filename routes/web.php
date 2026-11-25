<?php

use Illuminate\Support\Facades\Route;

use App\Modules\Monitoring\Http\Controllers\SnapshotController;

Route::get('/', fn () => redirect()->route(config('app.snapshot_only') ? 'snapshot.index' : 'inicio'));
Route::get('/visor', [SnapshotController::class, 'index'])->name('snapshot.index');
