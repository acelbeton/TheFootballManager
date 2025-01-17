<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Market;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketApiController extends Controller
{
    public function index(): JsonResponse
    {
        $markets = Market::with(['player', 'user'])->get();
        return response()->json($markets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_id' => 'required|exists:players,id',
            'current_bid_amount' => 'required|numeric|min:0',
            'user_id' => 'nullable|exists:users,id', // Nullable ha nincs meg licitje
        ]);

        $market = Market::create($validated);

        return response()->json($market, 201);
    }

    public function show(Market $market): JsonResponse
    {
        return response()->json($market->load(['player', 'user']));
    }

    public function update(Request $request, Market $market): JsonResponse
    {
        $validated = $request->validate([
            'player_id' => 'required|exists:players,id',
            'current_bid_amount' => 'required|numeric|min:0',
            'user_id' => 'nullable|exists:users,id', // Nullable ha nincs meg licitje
        ]);

        $market->update($validated);

        return response()->json($market);
    }

    public function destroy(Market $market): JsonResponse
    {
        $market->delete();

        return response()->json(['message' => 'Market entry deleted successfully'], 200);
    }
}
