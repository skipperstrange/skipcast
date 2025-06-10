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

Route::get('/ping', function () {
    return 'pong';
});

// Authentication Routes
Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::get('/auth/{provider}', 'redirectToProvider');
    Route::get('/auth/{provider}/callback', 'handleProviderCallback');
});

// Public Channel Routes
Route::get('/channels', [ChannelController::class, 'index'])
    ->name('channels.index');

Route::get('/channels/{channel}', [ChannelController::class, 'show'])
    ->name('channels.show');

Route::get('/channels/{channel:slug}', [ChannelController::class, 'showWithMediaAndUser'])
    ->name('channels.showWithMediaAndUser');

// Public Routes (accessible to all users)
Route::get('/channels/{channel}/media', [ChannelController::class, 'listMedia'])
    ->name('channels.listMedia');
Route::get('/media/{media}', [MediaController::class, 'show'])
    ->name('media.show');
Route::get('/channels/{channel}/stream/status', [ChannelController::class, 'getStreamStatus'])
    ->name('channels.streamStatus');
Route::get('/channels/{channel}/stream-url', [ChannelController::class, 'getStreamUrls'])
    ->name('channels.streamUrl');

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth Routes
    Route::post('/logout', [AuthController::class, 'logout']);

    // Protected Channel Routes
    Route::get('/channels/trash/view', [ChannelController::class, 'trashed'])
        ->name('channels.trash');
    Route::get('/channels/trash/view/{channel}', [ChannelController::class, 'viewTrashed'])
        ->name('channels.viewTrashed');
    Route::post('/channel/trash/restore', [ChannelController::class, 'restore'])
        ->name('channels.restore');
    
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
        Route::post('/channels/{channel}/stream/start', 'startStream')
            ->name('channels.startStream');
        Route::post('/channels/{channel}/stream/stop', 'stopStream')
            ->name('channels.stopStream');
        Route::post('/channels/{channel}/genres', 'attachGenres')
            ->name('channels.attachGenres');
        Route::delete('/channels/{channel}/genres', 'detachGenres')
            ->name('channels.detachGenres');
        Route::post('/channels/{channel}/media', 'attachMedia')
            ->name('channels.attachMedia');
        Route::delete('/channels/{channel}/media', 'detachMedia')
            ->name('channels.detachMedia');
    });

    // Protected Media Routes
    Route::controller(MediaController::class)->group(function () {
        Route::post('/media/upload', 'upload')->name('media.upload');
        Route::put('/media/{media}', 'update')->name('media.update');
        Route::delete('/media/{media}', 'destroy')
            ->middleware('can:delete,media')
            ->name('media.destroy');
        Route::post('/media/{media}/genres', 'attachGenres')
            ->name('media.attachGenres');
        Route::delete('/media/{media}/genres', 'detachGenres')
            ->name('media.detachGenres');
        Route::post('/media/{media}/channels', 'attachChannels')
            ->name('media.attachChannels');
        Route::delete('/media/{media}/channels', 'detachChannels')
            ->name('media.detachChannels');
    });

    // Media Trash Routes
    Route::get('/media/trash/view', [MediaController::class, 'trashed'])
        ->name('media.trash');
    Route::get('/media/trash/view/{media}', [MediaController::class, 'viewTrashed'])
        ->name('media.viewTrashed');
    Route::post('/media/trash/restore', [MediaController::class, 'restore'])
        ->name('media.restore');
});