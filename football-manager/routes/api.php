<?php

use App\Http\Controllers\Api\PlayerApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('player')->group(function () {
        Route::get('/{id}', [PlayerApiController::class, 'show']);
    });
    Route::prefix('admin')->group(function () {
          Route::apiResource('players', PlayerApiController::class)
              ->except(['create', 'edit']);
    });
});

Route::fallback(function () {
    return response()->json(['message' => 'API route not found'], 404);
});
