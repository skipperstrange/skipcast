<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChannelController;

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

// Test route to verify API is working
Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
});

// Authentication Routes
Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::get('/auth/{provider}', 'redirectToProvider');
    Route::get('/auth/{provider}/callback', 'handleProviderCallback');
});

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::post('/logout', 'logout');
    });

    // Channel Routes
    Route::controller(ChannelController::class)->group(function () {
        Route::get('/channels', 'index');
        Route::post('/channels', 'store');
        Route::get('/channels/{channel}', 'show');
        Route::put('/channels/{channel}', 'update');
        Route::delete('/channels/{channel}', 'destroy');
        Route::put('/channels/{channel}/state', 'updateState');
    });
});

// Debugging route
Route::get('/ping', function () {
    return 'pong';
});
