<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Market;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketApiController extends Controller
{
    /**
     * Display a listing of market entries.
     */
    public function index(): JsonResponse
    {
        $markets = Market::with(['player', 'user'])->get();
        return response()->json($markets);
    }

    /**
     * Store a newly created market entry in storage.
     */
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

    /**
     * Display the specified market entry.
     */
    public function show(Market $market): JsonResponse
    {
        return response()->json($market->load(['player', 'user']));
    }

    /**
     * Update the specified market entry in storage.
     */
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

    /**
     * Remove the specified market entry from storage.
     */
    public function destroy(Market $market): JsonResponse
    {
        $market->delete();

        return response()->json(['message' => 'Market entry deleted successfully'], 200);
    }
}
