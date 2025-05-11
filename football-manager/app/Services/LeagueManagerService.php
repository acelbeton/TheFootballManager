<?php

namespace App\Services;

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

        if (!$season) {
            $season = Season::create([
                'league_id' => $league->getKey(),
                'start_date' => now(),
                'end_date' => now()->addWeeks(self::SEASON_WEEKS),
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

    private function updatePointsPerWeekAverages(Season $season): void
    {
        $standings = Standing::where('season_id', $season->getKey())->get();

        foreach ($standings as $standing) {
            $seasonStart = Carbon::parse($season->start_date);
            $currentDate = now();
            $weeksPassed = $seasonStart->diffInWeeks($currentDate) + 1;
            $weeksPassed = min(self::SEASON_WEEKS, max(1, $weeksPassed));

            $divisor = max($standing->matches_played, $weeksPassed);
            if ($divisor > 0) {
                $standing->points_per_week_avg = $standing->points / $divisor;
                $standing->save();
            }
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

        $seasonEnd = Carbon::parse($season->end_date);
        $lastWeekStartDate = (clone $seasonEnd)->subDays(7);

        if (now()->gte($lastWeekStartDate)) {
            return [
                'status' => 'last_week_forbidden',
                'message' => "You cannot join a league in its final week. Please wait for the next season.",
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
            $this->integrateTeamMidSeason($team, $season);

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

    private function integrateTeamMidSeason(Team $newTeam, Season $season): void
    {
        DB::transaction(function() use ($newTeam, $season) {
            $teamCount = Team::where('season_id', $season->getKey())->count();
            if ($teamCount % 2 !== 0) {
                $this->aiTeamGenerator->generateTeamsForSeason($season, $teamCount + 1);
            }

            $seasonStart = Carbon::parse($season->start_date);
            $currentDate = now();
            $currentWeek = $seasonStart->diffInWeeks($currentDate) + 1;
            $currentWeek = min(4, max(1, $currentWeek));

            $teams = Team::where('season_id', $season->getKey())
                ->where('id', '!=', $newTeam->getKey())
                ->get();

            $remainingWeeks = 4 - $currentWeek + 1;
            $startDate = now();
            $endDate = Carbon::parse($season->end_date);

            $teamsToPlay = min($teams->count(), $remainingWeeks * 2); // Assume max 2 matches per week
            $selectedTeams = $teams->random($teamsToPlay);

            $weekDates = [];
            for ($week = 0; $week < $remainingWeeks; $week++) {
                $weekStart = (clone $startDate)->addWeeks($week);
                $weekEnd = ($week == $remainingWeeks - 1) ? $endDate : (clone $weekStart)->addDays(6);

                $matchesThisWeek = min(2, $selectedTeams->count() - ($week * 2));
                if ($matchesThisWeek > 0) {
                    $weekDates[$week] = $this->calculateMatchDatesForWeek($weekStart, $weekEnd, $matchesThisWeek);
                }
            }

            $matchIndex = 0;
            foreach ($selectedTeams as $opponent) {
                $week = intdiv($matchIndex, 2);
                $matchInWeek = $matchIndex % 2;

                if (isset($weekDates[$week][$matchInWeek])) {
                    $matchDate = $weekDates[$week][$matchInWeek];
                    $isHome = (bool)rand(0, 1);

                    if ($isHome) {
                        MatchModel::create([
                            'home_team_id' => $newTeam->getKey(),
                            'away_team_id' => $opponent->getKey(),
                            'home_team_score' => 0,
                            'away_team_score' => 0,
                            'match_date' => $matchDate,
                        ]);
                    } else {
                        MatchModel::create([
                            'home_team_id' => $opponent->getKey(),
                            'away_team_id' => $newTeam->getKey(),
                            'home_team_score' => 0,
                            'away_team_score' => 0,
                            'match_date' => $matchDate,
                        ]);
                    }

                    $matchIndex++;
                }
            }
        });
    }

    private function calculateMatchDatesForWeek(Carbon $weekStart, Carbon $weekEnd, int $matchCount): array
    {
        $matchDates = [];

        if ($matchCount == 1) {
            $weekend = (clone $weekStart)->addDays(rand(5, 6));
            $matchHour = rand(12, 20);
            $matchDates[] = $weekend->setHour($matchHour)->setMinute(0)->setSecond(0);
            return $matchDates;
        }

        $daysBetweenMatches = max(1, intval(7 / $matchCount));

        $currentDate = clone $weekStart;
        for ($i = 0; $i < $matchCount; $i++) {
            $matchHour = rand(12, 20);
            $matchDate = (clone $currentDate)->setHour($matchHour)->setMinute(0)->setSecond(0);
            $matchDates[] = $matchDate;

            $currentDate = $currentDate->addDays($daysBetweenMatches);

            if ($currentDate->gt($weekEnd)) {
                $currentDate = clone $weekEnd;
            }
        }

        return $matchDates;
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
