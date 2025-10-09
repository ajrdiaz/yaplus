<?php

use App\Http\Controllers\External\YoutubeController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/', function () {
        return Inertia::render('Home');
    })->name('inicio');

    // YouTube Routes
    Route::prefix('youtube')->name('youtube.')->group(function () {
        Route::get('/', [YoutubeController::class, 'index'])->name('index');
        Route::post('/import', [YoutubeController::class, 'importComments'])->name('import');
        Route::get('/videos/{video}/comments', [YoutubeController::class, 'getVideoComments'])->name('video.comments');
        Route::put('/videos/{video}/context', [YoutubeController::class, 'updateContext'])->name('video.updateContext');
        Route::delete('/videos/{video}', [YoutubeController::class, 'destroyVideo'])->name('video.destroy');
        Route::delete('/comments/{comment}', [YoutubeController::class, 'destroy'])->name('destroy');
        Route::get('/stats', [YoutubeController::class, 'stats'])->name('stats');
        
        // AI Analysis Routes
        Route::post('/analyze', [YoutubeController::class, 'analyzeComments'])->name('analyze');
        Route::get('/videos/{video}/analysis', [YoutubeController::class, 'getAnalysis'])->name('video.analysis');
        Route::post('/analysis/filter', [YoutubeController::class, 'filterAnalysis'])->name('analysis.filter');
    });
});

Fortify::loginView(function () {
    return Inertia::render('Login/Login_v1', [
        'canResetPassword' => Route::has('password.request'),
        'status' => session('status'),
    ]);
});

Fortify::registerView(function () {
    return Inertia::render('Login/Register');
});

Fortify::authenticateUsing(function (Request $request) {
    $user = User::where('email', $request->email)->first();

    if (
        $user &&
        Hash::check($request->password, $user->password)
    ) {
        return $user;
    }
});