<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\MediaController;

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

// Public Routes
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

// Public Channel Routes with optional parameters
Route::get('/channels', [ChannelController::class, 'index'])
    ->name('channels.index');

Route::get('/channels/{channel}', [ChannelController::class, 'show'])
    ->name('channels.show');

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth Routes
    Route::post('/logout', [AuthController::class, 'logout']);

    // Protected Channel Routes
    Route::controller(ChannelController::class)->group(function () {
        Route::post('/channels', 'store')->name('channels.store');
        Route::put('/channels/{channel}', 'update')
            ->middleware('can:update,channel')
            ->name('channels.update');
        Route::delete('/channels/{channel}', 'destroy')
            ->middleware('can:delete,channel')
            ->name('channels.destroy');
        Route::put('/channels/{channel}/state', 'updateState')
            ->middleware('can:manage-state,channel')
            ->name('channels.update-state');
    });

    Route::post('/media/upload', [MediaController::class, 'upload'])->name('media.upload');
    Route::put('/media/{media}', [MediaController::class, 'update'])->name('media.update');
});

// Debugging route
Route::get('/ping', function () {
    return 'pong';
});
