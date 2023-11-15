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

Route::apiResource('workflow', FlowController::class);
