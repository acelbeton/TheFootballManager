<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerApiController extends Controller
{
    /**
     * Fetch all players.
     */
    public function index(): JsonResponse
    {
        return response()->json(Player::with('team')->get());
    }

    /**
     * Store a new player.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'team_id' => 'nullable|exists:team,id',
            'position' => 'required|string|max:255',
            'market_value' => 'required|numeric|min:0',
            'condition' => 'required|numeric|min:0|max:100',
            'is_injured' => 'required|boolean',
        ]);

        $player = Player::create($validated);

        return response()->json($player, 201);
    }

    /**
     * Fetch a single player.
     */
    public function show(Player $player): JsonResponse
    {
        return response()->json($player->load('team'));
    }

    /**
     * Update a player.
     */
    public function update(Request $request, Player $player): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'team_id' => 'nullable|exists:teams,id',
            'position' => 'required|string|max:255',
            'market_value' => 'required|numeric|min:0',
            'condition' => 'required|numeric|min:0|max:100',
            'is_injured' => 'required|boolean',
        ]);

        $player->update($validated);

        return response()->json($player);
    }

    /**
     * Delete a player.
     */
    public function destroy(Player $player): JsonResponse
    {
        $player->delete();

        return response()->json(['message' => 'Player deleted successfully'], 200);
    }
}
