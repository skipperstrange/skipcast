<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChannelController;

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

Route::get('/channels', [ChannelController::class, 'index'])->name('channel.index');
Route::post('/channels', [ChannelController::class, 'store'])->name('channel.create');
Route::get('/channels/{id}', [ChannelController::class, 'show'])->name('channel.view');
Route::put('/channels/{id}', [ChannelController::class, 'update'])->name('channel.update');
Route::delete('/channels/{id}', [ChannelController::class, 'destroy'])->name('channel.delete');

Route::fallback(function () {
    return response()->json(['error' => 'Method Not Allowed'], 405);
});
