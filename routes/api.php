<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| POS API — v1
|--------------------------------------------------------------------------
| Versioned from the very first endpoint (episode 2). Adding a version later
| is itself a breaking change, which defeats the point of having one.
*/

Route::prefix('v1')->group(function () {

    // ── Public ──────────────────────────────────────────────────────────────
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
        Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:10,1,refresh');
    });

    // ── Authenticated ───────────────────────────────────────────────────────
    Route::middleware(['jwt.auth', 'throttle:120,1,api'])->group(function () {

        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        // Reads are open to any signed-in staff member.
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{category}', [CategoryController::class, 'show']);

        // Writes to the catalogue are a manager's job. Episode 14.
        Route::middleware('role:manager')->group(function () {
            Route::post('/products', [ProductController::class, 'store']);
            Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update']);
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);

            Route::post('/categories', [CategoryController::class, 'store']);
            Route::match(['put', 'patch'], '/categories/{category}', [CategoryController::class, 'update']);
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        });

        // Sales. Note the ordering: a literal segment must be declared before a
        // wildcard, or `/sales/report` gets captured as a sale uuid.
        Route::get('/sales', [SaleController::class, 'index']);
        Route::get('/sales/{sale}', [SaleController::class, 'show']);

        // The checkout is the one endpoint that must never run twice. Episode 18.
        Route::post('/sales', [SaleController::class, 'store'])->middleware('idempotent');

        Route::post('/sales/{sale}/refund', [SaleController::class, 'refund'])
            ->middleware('role:manager');
    });
});
