<?php

namespace App\Services;

use App\Models\League;
use App\Models\MatchModel;
use App\Models\Season;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class WeeklyScheduleService
{
    protected $leagueManager;
    protected $matchSimulator;

    public function __construct(
        LeagueManagerService $leagueManager,
        MatchSimulator $matchSimulator
    ) {
        $this->leagueManager = $leagueManager;
        $this->matchSimulator = $matchSimulator;
    }

    public function processAllLeagues(): void
    {
        $leagues = League::all();

        foreach ($leagues as $league) {
            try {
                $this->processLeague($league);
            } catch (Exception $e) {
                Log::error("Error processing league {$league->name}: " . $e->getMessage());
            }
        }
    }

    public function processLeague(League $league): void
    {
        $season = Season::where('league_id', $league->getKey())
            ->where('open', true)
            ->first();

        if (!$season) {
            try {
                $season = $this->leagueManager->checkAndCreateNewSeason($league);
                if ($season) {
                    Log::info("Created new season for league {$league->name}");
                }
            } catch (Exception $e) {
                Log::error("Error creating new season for league {$league->name}: " . $e->getMessage());
            } catch (Throwable $e) {
                Log::error("Error throwable creating new season for league {$league->name}: " . $e->getMessage());
            }
            return;
        }

        $this->simulatePendingMatches($season);
        $this->checkSeasonCompletion($season);
        $this->leagueManager->processLeague($league);
    }

    private function simulatePendingMatches(Season $season): void
    {
        $pendingMatches = MatchModel::where('match_date', '<', now())
            ->where(function($query) {
                $query->where('home_team_score', 0)
                    ->where('away_team_score', 0);
            })
            ->whereHas('homeTeam', function($query) use ($season) {
                $query->where('season_id', $season->getKey());
            })
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        foreach ($pendingMatches as $match) {
            $isAIMatch = $match->homeTeam->user_id === null && $match->awayTeam->user_id === null;

            if ($isAIMatch) {
                try {
                    $this->matchSimulator->simulateMatch($match);
                    Log::info("Simulated AI match #{$match->getKey()} between {$match->homeTeam->name} and {$match->awayTeam->name}");
                } catch (Exception $e) {
                    Log::error("Error simulating AI match #{$match->getKey()}: " . $e->getMessage());
                } catch (Throwable $e) {
                    Log::error("Error throwable simulating AI match #{$match->getKey()}: " . $e->getMessage());
                }
            } else {
                $matchDate = Carbon::parse($match->match_date);
                $daysOverdue = now()->diffInDays($matchDate);

                if ($daysOverdue > 3) {
                    try {
                        $this->matchSimulator->simulateMatch($match);
                        Log::info("Auto-simulated overdue user match #{$match->getKey()} between {$match->homeTeam->name} and {$match->awayTeam->name}");
                    } catch (Exception $e) {
                        Log::error("Error auto-simulating overdue match #{$match->getKey()}: " . $e->getMessage());
                    } catch (Throwable $e) {
                        Log::error("Error throwable auto-simulating overdue match #{$match->getKey()}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    private function checkSeasonCompletion(Season $season): void
    {
        if (now()->gte(Carbon::parse($season->end_date))) {
            $totalMatches = MatchModel::whereHas('homeTeam', function($query) use ($season) {
                $query->where('season_id', $season->getKey());
            })->count();

            $playedMatches = MatchModel::whereHas('homeTeam', function($query) use ($season) {
                $query->where('season_id', $season->getKey());
            })
                ->where(function($query) {
                    $query->where('home_team_score', '>', 0)
                        ->orWhere('away_team_score', '>', 0);
                })
                ->count();

            if ($playedMatches < $totalMatches) {
                $pendingMatches = MatchModel::whereHas('homeTeam', function($query) use ($season) {
                    $query->where('season_id', $season->getKey());
                })
                    ->where(function($query) {
                        $query->where('home_team_score', 0)
                            ->where('away_team_score', 0);
                    })
                    ->get();

                foreach ($pendingMatches as $match) {
                    try {
                        $this->matchSimulator->simulateMatch($match);
                    } catch (Exception $e) {
                        Log::error("Error simulating match at season end #{$match->getKey()}: " . $e->getMessage());
                    } catch (Throwable $e) {
                        Log::error("Error throwable simulating match at season end #{$match->getKey()}: " . $e->getMessage());
                    }
                }
            }

            $season->update(['open' => false]);

            try {
                $league = $season->league;
                $newSeason = $this->leagueManager->checkAndCreateNewSeason($league);

                if ($newSeason) {
                    Log::info("Created new season for league {$league->name} after previous season ended");
                }
            } catch (Exception $e) {
                Log::error("Error creating new season after previous ended: " . $e->getMessage());
            } catch (Throwable $e) {
                Log::error("Error throwable creating new season after previous ended: " . $e->getMessage());
            }
        }
    }
}
