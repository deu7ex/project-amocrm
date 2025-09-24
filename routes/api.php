<?php

use App\Http\Controllers\AmoCrmAuthController;
use App\Http\Controllers\LeadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AmoTestController;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'time' => now()->toDateTimeString(),
    ]);
});

Route::post('/amocrm/sheet/save', [LeadController::class, 'store']);
Route::post('/amocrm/sheet/google', [LeadController::class, 'google']);
Route::post('/amocrm/vk', [LeadController::class, 'vk']);

Route::get('/amocrm/callback', [AmoCrmAuthController::class, 'callback']);
