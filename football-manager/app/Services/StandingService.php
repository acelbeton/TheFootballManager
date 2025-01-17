<?php

namespace App\Services;

use App\Models\Standing;

class StandingService
{
    public function updateAfterMatch($match)
    {
        $this->updateTeamStanding($match->home_team_id, $match->home_team_score, $match->away_team_score);
        $this->updateTeamStanding($match->away_team_id, $match->away_team_score, $match->home_team_score);
    }

    private function updateTeamStanding($teamId, $goalsFor, $goalsAgainst)
    {
        $standing = Standing::firstOrCreate(['team_id' => $teamId]);

        $standing->goals_scored += $goalsFor;
        $standing->goals_conceded += $goalsAgainst;
        $standing->matches_played++;
        $standing->points += ($goalsFor > $goalsAgainst ? 3 : ($goalsFor == $goalsAgainst ? 1 : 0));

        $standing->save();
    }
}
