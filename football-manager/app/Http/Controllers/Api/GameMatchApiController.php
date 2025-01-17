<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameMatchApiController extends Controller
{
    /**
     * Display a listing of matches.
     */
    public function index(): JsonResponse
    {
        $matches = MatchModel::with(['homeTeam', 'awayTeam'])->get();
        return response()->json($matches);
    }

    /**
     * Store a newly created match in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'home_team_id' => 'required|exists:teams,id',
            'away_team_id' => 'required|exists:teams,id',
            'home_team_score' => 'nullable|integer|min:0',
            'away_team_score' => 'nullable|integer|min:0',
            'match_date' => 'required|date',
        ]);

        $match = MatchModel::create($validated);

        return response()->json($match, 201);
    }

    /**
     * Display the specified match.
     */
    public function show(MatchModel $match): JsonResponse
    {
        return response()->json($match->load(['homeTeam', 'awayTeam']));
    }

    /**
     * Update the specified match in storage.
     */
    public function update(Request $request, MatchModel $match): JsonResponse
    {
        $validated = $request->validate([
            'home_team_id' => 'required|exists:teams,id',
            'away_team_id' => 'required|exists:teams,id',
            'home_team_score' => 'nullable|integer|min:0',
            'away_team_score' => 'nullable|integer|min:0',
            'match_date' => 'required|date',
        ]);

        $match->update($validated);

        return response()->json($match);
    }

    /**
     * Remove the specified match from storage.
     */
    public function destroy(MatchModel $match): JsonResponse
    {
        $match->delete();

        return response()->json(['message' => 'Match deleted successfully'], 200);
    }
}
