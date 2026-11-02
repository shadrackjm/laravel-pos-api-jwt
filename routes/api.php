<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| POS API — v1
|--------------------------------------------------------------------------
| Versioned from the very first endpoint (episode 2). Adding a version later
| is itself a breaking change, which defeats the point of having one.
*/

Route::prefix('v1')->group(function () {
    Route::get('/ping', function () {
        return response()->json([
            'success' => true,
            'message' => 'pong',
            'data' => ['version' => 'v1'],
        ]);
    });

    Route::prefix('auth')->group(function () {
        // A strict limit here specifically: this is where passwords get guessed.
        // The third argument names the bucket. Without it every throttle keys on
        // the IP alone, so ordinary API calls would use up the login limit.
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1,register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1,login');
    });

    Route::apiResource('products', ProductController::class);
    Route::apiResource('categories', CategoryController::class);
});
