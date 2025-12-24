<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Laravel Flow Routes
|--------------------------------------------------------------------------
|
| All Route in Laravel Flow package
|
*/

Route::prefix('workflow')->name('workflow.')->namespace('JobMetric\Flow\Http\Controllers')->group(function () {
    Route::middleware([
        SubstituteBindings::class,
    ])->group(function () {
        // Setup routes
    });
});
