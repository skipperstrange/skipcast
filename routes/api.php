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

Route::get('/channels/{channel:slug}', [ChannelController::class, 'showWithMediaAndUser'])->name('channels.showWithMediaAndUser');

// Public Routes (accessible to all users)
Route::get('/channels/{channel}/media', [ChannelController::class, 'listMedia']);
Route::get('/media/{media}', [MediaController::class, 'show']);
Route::post('/media/{media}/channels', [MediaController::class, 'attachChannels'])->name('media.attachChannels');

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
        Route::post('/channels/{channel}/stream/start', 'startStream')->name('channels.startStream');
        Route::post('/channels/{channel}/stream/stop', 'stopStream')->name('channels.stopStream');
        Route::get('/channels/{channel}/stream-url', 'getStreamUrls')->name('channels.streamUrl');
        Route::post('/channels/{channel}/genres', 'attachGenres')->name('channels.attachGenres');
        Route::delete('/channels/{channel}/genres', 'detachGenres')->name('channels.detachGenres');
        Route::post('/channels/{channel}/media', [ChannelController::class, 'attachMedia']);
        Route::delete('/channels/{channel}/media', [ChannelController::class, 'detachMedia']);
    });

    // Protected Media Routes
    Route::post('/media/upload', [MediaController::class, 'upload'])->name('media.upload');
    Route::put('/media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])
        ->middleware('can:delete,media')
        ->name('media.destroy');
    Route::post('/media/{media}/genres', [MediaController::class, 'attachGenres'])->name('media.attachGenres');
    Route::delete('/media/{media}/genres', [MediaController::class, 'detachGenres'])->name('media.detachGenres');

    // Media Trash Routes
    Route::get('/media/trash/view', [MediaController::class, 'trashed'])->name('media.trash');
    Route::get('/media/trash/view/{media}', [MediaController::class, 'viewTrashed'])->name('media.viewTrashed');
    Route::post('/media/trash/restore', [MediaController::class, 'restore'])->name('media.restore');

    // Attach genres to a channel
    Route::post('/channels/{channel}/genres', [ChannelController::class, 'attachGenres'])->name('channels.attachGenres');

    // Channel media management
    Route::post('channels/{channel}/media', [MediaController::class, 'attachToChannel']);

    // Media channel management
    Route::post('media/{media}/channels', [MediaController::class, 'attachChannels']);
    Route::delete('media/{media}/channels', [MediaController::class, 'detachChannels']);
});

// Debugging route
Route::get('/ping', function () {
    return 'pong';
});
