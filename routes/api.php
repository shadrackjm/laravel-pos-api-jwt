<?php

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
});
