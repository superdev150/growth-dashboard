<?php

use App\Http\Controllers\GrowthController;
use Illuminate\Support\Facades\Route;

Route::get('/growth', GrowthController::class)->middleware('throttle:growth');
