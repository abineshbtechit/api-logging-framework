<?php

use App\Http\Controllers\ApiLogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Models\ApiLog;
use GuzzleHttp\Middleware;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

//CRUD operation Sample Data
Route::get('/getting', [TestController::class, 'getting'])->middleware('mid');
Route::post('/posting', [TestController::class, 'posting'])->middleware('mid');
Route::put('/putting/{id}', [TestController::class, 'putting'])->middleware('mid');
Route::delete('/deleting/{id}', [TestController::class, 'deleting'])->middleware('mid');
require __DIR__.'/logs.php';
require __DIR__.'/app.php';


//fetch by the method
// Route::get('logs//post',[ApiLogController::class,'postMethod'])->middleware('mid');
// Route::get('logs/get',[ApiLogController::class,'getMethod'])->middleware('mid');
// Route::get('logs/update',[ApiLogController::class,'updateMethod'])->middleware('mid');
// Route::get('logs/delete',[ApiLogController::class,'deleteMethod'])->middleware('mid');




//fetch by id
//Route::get('/logs/{id}',[ApiLogController::class,'fetchById'])->middleware('mid');


//displaying the error
//Route::get('/error', [TestController::class, 'error'])->middleware('mid');


//filtering the logs

// Route::get('/logs', [ApiLogController::class, 'index']);







//For another application you need to change only api.php and your controller logic