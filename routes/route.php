<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use JobMetric\Flow\Http\Controllers\FlowController;
use JobMetric\Flow\Http\Controllers\FlowStateController;

/*
|--------------------------------------------------------------------------
| Laravel Flow Routes
|--------------------------------------------------------------------------
|
| All Route in Laravel Flow package
|
*/

// flow
Route::middleware([
    SubstituteBindings::class
])->group(function () {
    Route::apiResource('flow', FlowController::class);
    Route::prefix('flow/{flow}')->group(function () {
        Route::get('restore', [FlowController::class, 'restore'])->name('flow.restore');
        Route::delete('force_delete', [FlowController::class, 'forceDelete'])->name('flow.force_delete');

        // flow state
        Route::apiResource('flow-state', FlowStateController::class);
    });
});
