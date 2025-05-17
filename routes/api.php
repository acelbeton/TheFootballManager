<?php

use App\Http\Controllers\Api\LeagueApiController;
use App\Http\Controllers\Api\PlayerApiController;
use App\Http\Controllers\MarketController;

Route::post('/market/bid', [MarketController::class, 'placeBid']);
Route::post('/market/finalize', [MarketController::class, 'finalizeTransfer']);

use Illuminate\Support\Facades\Route;

// Auth route
Route::middleware('auth:sanctum')->group(function () {
});

// Fallback
Route::fallback(function () {
    return response()->json(['message' => 'API route not found'], 404);
});

