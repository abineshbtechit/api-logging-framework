<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiLogController;

Route::get('/logs', [ApiLogController::class, 'index']);