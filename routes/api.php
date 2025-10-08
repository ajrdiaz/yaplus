<?php

use App\Http\Controllers\External\YoutubeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// YouTube API Routes
Route::prefix('youtube')->group(function () {
    Route::get('/comments', [YoutubeController::class, 'getComments']);
    Route::get('/video-info', [YoutubeController::class, 'getVideoInfo']);
    Route::get('/search', [YoutubeController::class, 'searchVideos']);
});
