<?php

use Illuminate\Support\Facades\Route;
use JobMetric\Flow\Http\Controllers\FlowController;

/*
|--------------------------------------------------------------------------
| Laravel Flow Routes
|--------------------------------------------------------------------------
|
| All Route in Laravel Flow package
|
*/

Route::apiResource('flow', FlowController::class);
Route::prefix('flow')->group(function () {
    Route::get('{flow}/restore', [FlowController::class, 'restore'])->name('flow.restore');
    Route::delete('{flow}/force_delete', [FlowController::class, 'forceDelete'])->name('flow.force_delete');
});
