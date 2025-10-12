<?php

use App\Http\Controllers\External\GoogleFormsController;
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
        Route::delete('/videos/{video}', [YoutubeController::class, 'destroyVideo'])->name('video.destroy');
        Route::delete('/comments/{comment}', [YoutubeController::class, 'destroy'])->name('destroy');
        Route::get('/stats', [YoutubeController::class, 'stats'])->name('stats');

        // AI Analysis Routes
        Route::post('/analyze', [YoutubeController::class, 'analyzeComments'])->name('analyze');
        Route::get('/videos/{video}/analysis', [YoutubeController::class, 'getAnalysis'])->name('video.analysis');
        Route::post('/analysis/filter', [YoutubeController::class, 'filterAnalysis'])->name('analysis.filter');
        Route::post('/videos/{video}/buyer-personas', [YoutubeController::class, 'generateBuyerPersonas'])->name('video.buyerPersonas');
    });

    // Google Forms Routes
    Route::prefix('google-forms')->name('forms.')->group(function () {
        Route::get('/', [GoogleFormsController::class, 'index'])->name('index');
        Route::post('/import', [GoogleFormsController::class, 'importResponses'])->name('import');
        Route::get('/surveys/{survey}/responses', [GoogleFormsController::class, 'getSurveyResponses'])->name('survey.responses');
        Route::delete('/surveys/{survey}', [GoogleFormsController::class, 'destroy'])->name('survey.destroy');

        // AI Analysis Routes
        Route::post('/analyze', [GoogleFormsController::class, 'analyzeResponses'])->name('analyze');
        Route::get('/surveys/{survey}/analysis', [GoogleFormsController::class, 'getAnalysis'])->name('survey.analysis');

        // Buyer Personas
        Route::post('/surveys/{survey}/buyer-personas', [GoogleFormsController::class, 'generateBuyerPersonas'])->name('survey.buyerPersonas');
    });

    // Copy Generator Routes
    Route::prefix('copy-generator')->name('copy.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CopyGeneratorController::class, 'index'])->name('index');
        Route::post('/generate', [\App\Http\Controllers\CopyGeneratorController::class, 'generate'])->name('generate');
        Route::get('/{copy}', [\App\Http\Controllers\CopyGeneratorController::class, 'show'])->name('show');
        Route::delete('/{copy}', [\App\Http\Controllers\CopyGeneratorController::class, 'destroy'])->name('destroy');
        Route::get('/history', [\App\Http\Controllers\CopyGeneratorController::class, 'history'])->name('history');
    });

    // Products Routes
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ProductController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\ProductController::class, 'store'])->name('store');
        Route::put('/{product}', [\App\Http\Controllers\ProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [\App\Http\Controllers\ProductController::class, 'destroy'])->name('destroy');

        // Consolidation Routes
        Route::post('/{product}/consolidate', [\App\Http\Controllers\ProductController::class, 'consolidate'])->name('consolidate');
        Route::get('/{product}/consolidation', [\App\Http\Controllers\ProductController::class, 'showConsolidation'])->name('consolidation.show');
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
