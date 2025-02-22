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


    // TODO átalakítani ezt egy olyan függvényre, ami nem csak kezdő játékosokat generál
    public function getStatsForPosition($position)
    {
        switch ($position) {
            case PlayerPosition::GOALKEEPER:
                return [
                  'attacking' => rand(10, 25),
                  'defending' => rand(45, 65),
                  'stamina' => rand(50, 65),
                  'technical_skills' => rand(25, 35),
                  'speed' => rand(25, 30),
                  'tactical_sense' => rand(30, 40),
                ];

            case PlayerPosition::CENTRE_BACK:
                return [
                    'attacking' => rand(15, 25),
                    'defending' => rand(45, 65),
                    'stamina' => rand(50, 65),
                    'technical_skills' => rand(50, 65),
                    'speed' => rand(40, 55),
                    'tactical_sense' => rand(50, 65),
                ];

            case PlayerPosition::FULLBACK:
                return [
                    'attacking' => rand(20, 35),
                    'defending' => rand(45, 55),
                    'stamina' => rand(50, 65),
                    'technical_skills' => rand(50, 65),
                    'speed' => rand(40, 60),
                    'tactical_sense' => rand(50, 65),
                ];

            case PlayerPosition::MIDFIELDER:
                return [
                    'attacking' => rand(30, 40),
                    'defending' => rand(30, 40),
                    'stamina' => rand(50, 65),
                    'technical_skills' => rand(50, 65),
                    'speed' => rand(35, 45),
                    'tactical_sense' => rand(50, 65),
                ];

            case PlayerPosition::WINGER:
                return [
                    'attacking' => rand(45, 55),
                    'defending' => rand(20, 35),
                    'stamina' => rand(50, 55),
                    'technical_skills' => rand(55, 65),
                    'speed' => rand(55, 70),
                    'tactical_sense' => rand(40, 50),
                ];

            case PlayerPosition::STRIKER:
                return [
                    'attacking' => rand(45, 55),
                    'defending' => rand(20, 35),
                    'stamina' => rand(50, 55),
                    'technical_skills' => rand(55, 65),
                    'speed' => rand(40, 50),
                    'tactical_sense' => rand(40, 50),
                ];

            default:
                return [
                    'attacking' => 50,
                    'defending' => 50,
                    'stamina' => 50,
                    'technical_skills' => 50,
                    'speed' => 50,
                    'tactical_sense' => 50,
                ];
        }
    }
}
