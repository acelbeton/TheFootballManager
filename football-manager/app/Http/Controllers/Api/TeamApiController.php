<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json(Team::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): JsonResponse
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'user_id' => 'required|exists:user,id',
            'current_tactic' => 'required|string|max:255', // Ez egy ENUM TODO
            'team_budget' => 'nullable|int|min:1'
        ]);

        $team = Team::create($validated);

        return response()->json($team, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Team $team): JsonResponse
    {
        return response()->json($team);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'user_id' => 'required|exists:user,id',
            'current_tactic' => 'required|string|max:255', // Ez egy ENUM TODO
            'team_budget' => 'required|int|min:1'
        ]);

        $team->update($validated);

        return response()->json($team, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team): JsonResponse
    {
        $team->delete();

        return response()->json(['message' => 'Team deleted.'], 204);
    }
}
