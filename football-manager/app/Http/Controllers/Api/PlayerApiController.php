<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Enums\PlayerPosition;
use App\Models\Player;
use App\Models\Statistic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Player::with('team')->paginate(15));
    }

    // TODO ez ide vagy máshova???
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'team_id' => 'nullable|exists:teams,id',
            'position' => 'required|string|max:255',
            'market_value' => 'required|numeric|min:0',
            'condition' => 'required|numeric|min:0|max:100',
            'is_injured' => 'required|boolean',
        ]);


        $player = Player::create($validated);

        $defaultStats = $this->getStatsForPosition($player->position);

        $statistics = Statistic::create(array_merge($defaultStats, [
            'player_id' => $player->getKey(),
        ]));

        return response()->json([
            'message' => 'Player created successfully.',
        ], 201);
    }

    public function show(Player $player): JsonResponse
    {
        return response()->json($player->load('team'));
    }

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

        return response()->json([
            'message' => 'Player updated successfully.',
            'data' => $player,
        ]);
    }

    public function destroy(Player $player): JsonResponse
    {
        try {
            $player->delete();

            return response()->json([
                'message' => 'Player deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete the player.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
