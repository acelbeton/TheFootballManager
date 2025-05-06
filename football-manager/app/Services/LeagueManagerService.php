<?php

namespace App\Services;

use App\Models\League;
use App\Models\Season;
use App\Models\Team;
use Carbon\Carbon;
use Exception;
use Throwable;

class LeagueManagerService
{
    protected $aiTeamGenerator;
    protected $matchScheduler;
    protected $matchSimulationService;
    protected $realtimeMatchSimulationService;

    public function __construct(
        AITeamGeneratorService $aiTeamGenerator,
        MatchSchedulerService $matchScheduler,
        MatchSimulator $matchSimulationService,
        RealtimeMatchSimulationService $realtimeMatchSimulationService
    ) {
        $this->aiTeamGenerator = $aiTeamGenerator;
        $this->matchScheduler = $matchScheduler;
        $this->matchSimulationService = $matchSimulationService;
        $this->realtimeMatchSimulationService = $realtimeMatchSimulationService;
    }

    /**
     * @throws Throwable
     */
    public function setupLeague(League $league, int $minTeams = 8): Season
    {
        $season = Season::where('league_id', $league->getKey())
            ->where('open', true)
            ->first();

        if (!$season) {
            $season = Season::create([
                'league_id' => $league->getKey(),
                'start_date' => now(),
                'end_date' => now()->addWeeks(8),
                'open' => true,
                'prize_money_first' => 10000,
                'prize_money_second' => 5000,
                'prize_money_third' => 2500,
                'prize_money_other' => 1000,
            ]);
        }

        $this->aiTeamGenerator->generateTeamsForSeason($season, $minTeams);

        $teamCount = Team::where('season_id', $season->getKey())->count();

        if ($teamCount < $minTeams) {
            throw new Exception("Not enough teams to set up league. Minimum required: $minTeams");
        }

        if ($teamCount % 2 !== 0) {
            $this->aiTeamGenerator->generateTeamsForSeason($season, $teamCount + 1);
        }

        $this->matchScheduler->generateFixturesForSeason($season);

        return $season;
    }

    /**
     * @throws Exception
     */
    public function processLeague(League $league): array
    {
        $season = Season::where('league_id', $league->getKey())
            ->where('open', true)
            ->first();

        if (!$season) {
            throw new Exception("No active season found for the league.");
        }

        $pendingMatches = $season->matches()
            ->where(function($query) {
                $query->where('home_team_score', 0)
                    ->where('away_team_score', 0);
            })
            ->where('match_date', '<', now())
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        $simulatedMatches = [];
        $queuedMatches = [];

        foreach ($pendingMatches as $match) {
            $isAIMatch = $match->homeTeam->user_id === null && $match->awayTeam->user_id === null;

            if ($isAIMatch) {
                $this->matchSimulationService->queueMatch($match);
                $queuedMatches[] = $match;
            } else {
                $simulatedMatches[] = $match;
            }
        }

        $this->checkSeasonCompletion($season);

        return [
            'season' => $season,
            'simulatedMatches' => $simulatedMatches,
            'queuedMatches' => $queuedMatches
        ];
    }

    private function checkSeasonCompletion(Season $season): void
    {
        $totalMatches = $season->matches()->count();
        $playedMatches = $season->matches()
            ->where(function($query) {
                $query->where('home_team_score', '>', 0)
                    ->orWhere('away_team_score', '>', 0);
            })
            ->count();

        if ($playedMatches >= $totalMatches || now()->gt($season->end_date)) {
            $season->update(['open' => false]);

            $this->distributePrizeMoney($season);
        }
    }

    private function distributePrizeMoney(Season $season): void
    {
        $standings = $season->standings()
            ->orderBy('points', 'desc')
            ->orderBy('goals_scored', 'desc')
            ->orderBy('goals_conceded')
            ->get();

        if ($standings->isEmpty()) {
            return;
        }

        $first = $standings->first()->team;
        $second = $standings->count() > 1 ? $standings[1]->team : null;
        $third = $standings->count() > 2 ? $standings[2]->team : null;

        if ($first) {
            $first->team_budget += $season->prize_money_first;
            $first->save();
        }

        if ($second) {
            $second->team_budget += $season->prize_money_second;
            $second->save();
        }

        if ($third) {
            $third->team_budget += $season->prize_money_third;
            $third->save();
        }

        foreach ($standings as $index => $standing) {
            if ($index >= 3) {
                $team = $standing->team;
                $team->team_budget += $season->prize_money_other;
                $team->save();
            }
        }
    }

    /**
     * @throws Throwable
     */
    public function addTeamToLeague(Team $team, League $league): array
    {
        $season = Season::where('league_id', $league->getKey())
            ->where('open', true)
            ->first();

        if (!$season) {
            $season = $this->setupLeague($league);
            return [
                'status' => 'new_season_created',
                'message' => "A new season has been created and your team has joined!",
                'season' => $season,
            ];
        }

        $team->update(['season_id' => $season->getKey()]);

        $hasStartedMatches = $season->matches()
            ->where(function($query) {
                $query->where('home_team_score', '>', 0)
                    ->orWhere('away_team_score', '>', 0);
            })
            ->exists();

        if ($hasStartedMatches) {
            return [
                'status' => 'waiting_for_next_season',
                'message' => "The current season is already in progress. Your team will join in the next season.",
                'season' => $season,
            ];
        } else {
            $teamCount = Team::where('season_id', $season->getKey())->count();

            if ($teamCount % 2 !== 0) {
                $this->aiTeamGenerator->generateTeamsForSeason($season, $teamCount + 1);
            }

            $season->matches()->delete();

            $fixtures = $this->matchScheduler->generateFixturesForSeason($season);

            return [
                'status' => 'joined_current_season',
                'message' => "Your team has joined the current season!",
                'season' => $season,
                'fixtures' => $fixtures,
            ];
        }
    }

    /**
     * @throws Throwable
     */
    public function checkAndCreateNewSeason(League $league): ?Season
    {
        $currentSeason = Season::where('league_id', $league->getKey())
            ->where('open', true)
            ->first();

        if (!$currentSeason || !$currentSeason->open) {
            $newSeason = Season::create([
                'league_id' => $league->getKey(),
                'start_date' => now(),
                'end_date' => now()->addWeeks(8),
                'open' => true,
                'prize_money_first' => 10000,
                'prize_money_second' => 5000,
                'prize_money_third' => 2500,
                'prize_money_other' => 1000,
            ]);

            $teams = Team::where('season_id', $currentSeason ? $currentSeason->getKey() : null)
                ->where('user_id', '!=', null)
                ->get();

            foreach ($teams as $team) {
                $team->update(['season_id' => $newSeason->getKey()]);
            }

            $this->aiTeamGenerator->generateTeamsForSeason($newSeason);
            $this->matchScheduler->generateFixturesForSeason($newSeason);

            return $newSeason;
        }

        return null;
    }
}
