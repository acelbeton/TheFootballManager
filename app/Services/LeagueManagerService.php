<?php

namespace App\Services;

use App\Http\Enums\PrizeMoney;
use App\Models\League;
use App\Models\MatchModel;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Team;
use App\Models\TeamPerformance;
use Carbon\Carbon;
use DB;
use Exception;
use Throwable;

class LeagueManagerService
{
    protected $aiTeamGenerator;
    protected $matchScheduler;
    protected $matchSimulationService;
    protected $realtimeMatchSimulationService;

    const SEASON_WEEKS = 4;
    const MIN_TEAMS_PER_LEAGUE = 4;

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
    public function setupLeague(League $league, int $minTeams = self::MIN_TEAMS_PER_LEAGUE): Season
    {
        $season = Season::where('league_id', $league->getKey())
            ->where('open', true)
            ->first();

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
            ->orderBy('points_per_week_avg', 'desc')
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
    public function checkAndCreateNewSeason(League $league): ?Season
    {
        $currentSeason = Season::where('league_id', $league->getKey())
            ->where('open', true)
            ->first();

        if (!$currentSeason || !$currentSeason->open) {
            $newSeason = Season::create([
                'league_id' => $league->getKey(),
                'start_date' => now(),
                'end_date' => now()->addWeeks(self::SEASON_WEEKS),
                'open' => true,
                'prize_money_first' => PrizeMoney::PRIZE_MONEY_FIRST,
                'prize_money_second' => PrizeMoney::PRIZE_MONEY_SECOND,
                'prize_money_third' => PrizeMoney::PRIZE_MONEY_THIRD,
                'prize_money_other' => PrizeMoney::PRIZE_MONEY_OTHER,
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
