<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use JobMetric\Flow\Http\Controllers\FlowController;
use JobMetric\Flow\Http\Controllers\FlowStateController;
use JobMetric\Flow\Http\Controllers\FlowTaskController;
use JobMetric\Flow\Http\Controllers\FlowTransitionController;

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
    Route::prefix('flow')->group(function(){
        Route::prefix('{flow}')->group(function () {
            Route::get('restore', [FlowController::class, 'restore'])->name('flow.restore');
            Route::delete('force_delete', [FlowController::class, 'forceDelete'])->name('flow.force_delete');

            // flow state
            Route::apiResource('flow-state', FlowStateController::class);

            // flow transition
            Route::apiResource('flow-transition', FlowTransitionController::class);

            // flow transition task
            Route::prefix('flow-transition/{flow_transition}')->group(function (){
                Route::apiResource('flow-task', FlowTaskController::class);
            });

            // flow task
            Route::get('/flow-task/drivers', [FlowTaskController::class, 'drivers']);
        });
    });

    // flow task details
    Route::get('/flow-task/{flow_driver}/{task_driver}', [FlowTaskController::class, 'details']);
});
