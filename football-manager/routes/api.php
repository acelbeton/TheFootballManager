<?php

use App\Http\Controllers\Api\LeagueApiController;
use App\Http\Controllers\Api\PlayerApiController;
use App\Http\Controllers\MarketController;

Route::post('/market/bid', [MarketController::class, 'placeBid']);
Route::post('/market/finalize', [MarketController::class, 'finalizeTransfer']);

use Illuminate\Support\Facades\Route;

// Auth route
Route::middleware('auth:sanctum')->group(function () {

    // Player route-ok
    Route::prefix('player')->group(function () {
        Route::get('/{id}', [PlayerApiController::class, 'show']); // Egy játékos fetchelese
    });

    // Admin player management
    Route::prefix('admin')->group(function () {
        Route::apiResource('players', PlayerApiController::class)
            ->except(['create', 'edit']); // Ezek nem kellenek
        Route::post('/players/delete', [PlayerApiController::class, 'destroy']);
    });

    Route::post('/market/bid', [MarketController::class, 'placeBid']);
    Route::post('/market/finalize', [MarketController::class, 'finalizeTransfer']);

    // League route-ok
    Route::apiResource('leagues', LeagueApiController::class); // Full CRUD
});

// Fallback
Route::fallback(function () {
    return response()->json(['message' => 'API route not found'], 404);
});

