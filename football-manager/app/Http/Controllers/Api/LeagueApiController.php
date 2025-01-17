<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\League;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeagueApiController extends Controller
{
    public function index(): JsonResponse
    {
        $leagues = League::all();
        return response()->json($leagues);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'season' => 'required|string|max:255',
            'prize_money_first' => 'required|numeric|min:0',
            'prize_money_second' => 'required|numeric|min:0',
            'prize_money_third' => 'required|numeric|min:0',
            'prize_money_other' => 'required|numeric|min:0',
        ]);

        $league = League::create($validated);

        return response()->json($league, 201);
    }

    public function show(League $league): JsonResponse
    {
        return response()->json($league);
    }

    public function update(Request $request, League $league): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'season' => 'required|string|max:255',
            'prize_money_first' => 'required|numeric|min:0',
            'prize_money_second' => 'required|numeric|min:0',
            'prize_money_third' => 'required|numeric|min:0',
            'prize_money_other' => 'required|numeric|min:0',
        ]);

        $league->update($validated);

        return response()->json($league);
    }

    public function destroy(League $league): JsonResponse
    {
        $league->delete();

        return response()->json(['message' => 'League deleted successfully'], 200);
    }
}
