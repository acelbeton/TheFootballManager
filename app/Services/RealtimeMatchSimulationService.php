<?php

namespace App\Services;

use App\Jobs\SimulateMatchJob;
use App\Models\MatchModel;
use App\Models\MatchSimulationStatus;
use App\Models\Team;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Log;

class RealtimeMatchSimulationService
{
    /**
     * @throws Exception
     */
    public function startMatch(MatchModel $match): array
    {
        if ($match->home_team_score > 0 || $match->away_team_score > 0) {
            throw new Exception("This match has already been played.");
        }

        $existingStatus = MatchSimulationStatus::where('match_id', $match->getKey())
            ->whereIn('status', ['QUEUED', 'IN_PROGRESS'])
            ->first();

        if ($existingStatus) {
            return [
                'match_id' => $match->getKey(),
                'status' => $existingStatus->status,
                'message' => 'Match simulation is already ' .
                    ($existingStatus->status === 'QUEUED' ? 'queued' : 'in progress') . '.'
            ];
        }

        $jobId = (string) Str::uuid();

        $match->events()->delete();

        MatchSimulationStatus::create([
            'match_id' => $match->getKey(),
            'status' => 'QUEUED',
            'job_id' => $jobId,
            'current_minute' => 0
        ]);

        SimulateMatchJob::dispatch($match->getKey())
            ->onQueue('match-simulation')
            ->delay(now()->addSeconds(2));

        return [
            'match_id' => $match->getKey(),
            'status' => 'started',
            'message' => 'Match simulation has been queued and will begin shortly.'
        ];
    }

    public function getMatchState(MatchModel $match): array
    {
        $match->refresh();

        $homeTeam = Team::findOrFail($match->home_team_id);
        $awayTeam = Team::findOrFail($match->away_team_id);

        $simulationStatus = MatchSimulationStatus::where('match_id', $match->getKey())
            ->orderBy('created_at', 'desc')
            ->first();

        $currentMinute = $simulationStatus ? $simulationStatus->current_minute : 0;
        $status = $simulationStatus ? $simulationStatus->status : $this->getMatchStatus($match);

        $events = $match->events()->orderBy('minute', 'asc')->get();

        $homeShots = 0;
        $awayShots = 0;
        $homeShotsOnTarget = 0;
        $awayShotsOnTarget = 0;

        foreach ($events as $event) {
            if ($event->type === 'SHOT') {
                if ($event->team === 'home') {
                    $homeShots++;
                    if (rand(1, 100) <= 50) {
                        $homeShotsOnTarget++;
                    }
                } else {
                    $awayShots++;
                    if (rand(1, 100) <= 50) {
                        $awayShotsOnTarget++;
                    }
                }
            } else if ($event->type === 'GOAL') {
                if ($event->team === 'home') {
                    $homeShots++;
                    $homeShotsOnTarget++;
                } else {
                    $awayShots++;
                    $awayShotsOnTarget++;
                }
            } else if ($event->type === 'SAVE') {
                if ($event->team === 'home') {
                    $awayShots++;
                    $awayShotsOnTarget++;
                } else {
                    $homeShots++;
                    $homeShotsOnTarget++;
                }
            }
        }

        return [
            'match_id' => $match->getKey(),
            'home_team' => [
                'id' => $homeTeam->getKey(),
                'name' => $homeTeam->name,
                'score' => $match->home_team_score,
                'shots' => $homeShots,
                'shots_on_target' => $homeShotsOnTarget,
                'possession' => 50,
            ],
            'away_team' => [
                'id' => $awayTeam->getKey(),
                'name' => $awayTeam->name,
                'score' => $match->away_team_score,
                'shots' => $awayShots,
                'shots_on_target' => $awayShotsOnTarget,
                'possession' => 50,
            ],
            'status' => $status,
            'current_minute' => $currentMinute,
            'events' => $events,
        ];
    }

    public function getMatchStatus(MatchModel $match): string
    {
        if ($match->home_team_score > 0 || $match->away_team_score > 0) {
            return 'COMPLETED';
        }

        $simulationStatus = MatchSimulationStatus::where('match_id', $match->getKey())
            ->orderBy('created_at', 'desc')
            ->first();

        if ($simulationStatus) {
            return $simulationStatus->status;
        }

        $now = now();
        if ($match->match_date->gt($now)) {
            return 'scheduled';
        }

        return 'pending';
    }

    private function getCurrentMatchMinute(MatchModel $match): int
    {
        $simulationStatus = MatchSimulationStatus::where('match_id', $match->getKey())
            ->where('status', 'IN_PROGRESS')
            ->first();

        return $simulationStatus ? $simulationStatus->current_minute : 0;
    }

    public function updateMatchStatus(MatchModel $match, string $status, int $currentMinute = 0): void
    {
        $simulationStatus = MatchSimulationStatus::where('match_id', $match->getKey())
            ->orderBy('created_at', 'desc')
            ->first();

        if ($simulationStatus) {
            $simulationStatus->update([
                'status' => $status,
                'current_minute' => $currentMinute
            ]);
        } else {
            MatchSimulationStatus::create([
                'match_id' => $match->getKey(),
                'status' => $status,
                'current_minute' => $currentMinute,
                'job_id' => (string) Str::uuid(),
            ]);
        }

        Log::info("Match {$match->getKey()} status updated to {$status} at minute {$currentMinute}");
    }

    public function updateMatchScore(MatchModel $match, int $homeScore, int $awayScore): void
    {
        if ($match->home_team_score !== $homeScore || $match->away_team_score !== $awayScore) {
            $match->update([
                'home_team_score' => $homeScore,
                'away_team_score' => $awayScore,
            ]);

            $match->refresh();

            Log::info("Match {$match->getKey()} scores updated: {$homeScore} - {$awayScore}");
        }
    }
}
