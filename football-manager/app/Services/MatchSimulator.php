<?php

namespace App\Services;

use App\Models\MatchModel;
use App\Models\PlayerPerformance;
use App\Models\Team;

class MatchSimulator
{
    public function simulate(MatchModel $match)
    {
        $homeTeam = Team::with('players')->findOrFail($match->home_team_id);
        $awayTeam = Team::with('players')->findOrFail($match->away_team_id);

        $homeScore = $this->calculateTeamPerformance($homeTeam);
        $awayScore = $this->calculateTeamPerformance($awayTeam);

        $match->home_team_score = $homeScore;
        $match->away_team_score = $awayScore;
        $match->save();

        $this->recordPlayerPerformances($match, $homeTeam, $awayTeam);
    }

    private function calculateTeamPerformance(Team $team)
    {
        $performance = $team->players->reduce(function ($carry, $player) {
            return $carry + ($player->rating * $player->condition / 100);
        }, 0);

        return random_int(0, ceil($performance / count($team->players)));
    }

    private function recordPlayerPerformances(MatchModel $match, Team $homeTeam, Team $awayTeam)
    {
        foreach ($homeTeam->players as $player) {
            PlayerPerformance::create([
                'player_id' => $player->id,
                'match_id' => $match->id,
                'goals_scored' => random_int(0, 2),
                'assists' => random_int(0, 2),
                'rating' => random_int(50, 100),
                'minutes_played' => random_int(60, 90),
            ]);
        }

        foreach ($awayTeam->players as $player) {
            PlayerPerformance::create([
                'player_id' => $player->id,
                'match_id' => $match->id,
                'goals_scored' => random_int(0, 2),
                'assists' => random_int(0, 2),
                'rating' => random_int(50, 100),
                'minutes_played' => random_int(60, 90),
            ]);
        }
    }
}

