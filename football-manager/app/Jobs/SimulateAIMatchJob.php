<?php

namespace App\Jobs;

use App\Models\MatchModel;
use App\Models\Player;
use App\Models\PlayerPerformance;
use App\Models\Standing;
use App\Models\Team;
use App\Services\MatchSimulator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Log;
use Throwable;

class SimulateAIMatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $matchId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $matchId)
    {
        $this->matchId = $matchId;
    }

    /**
     * Execute the job.
     * @throws Throwable
     */
    public function handle(): void
    {
        $match = MatchModel::findOrFail($this->matchId);

        if ($match->home_team_score > 0 || $match->away_team_score > 0) {
            return;
        }

        $homeTeam = Team::with(['players.statistics'])->findOrFail($match->home_team_id);
        $awayTeam = Team::with(['players.statistics'])->findOrFail($match->away_team_id);

        $matchSimulator = app(MatchSimulator::class);
        list($homeScore, $awayScore, $events) = $matchSimulator->calculateMatchResult($homeTeam, $awayTeam);

        DB::transaction(function() use ($matchSimulator, $match, $homeTeam, $awayTeam, $homeScore, $awayScore, $events) {
            $match->update([
                'home_team_score' => $homeScore,
                'away_team_score' => $awayScore,
            ]);

            $matchSimulator->recordPlayerPerformances($match, $homeTeam, $awayTeam, $events);
            $matchSimulator->updateStandings($match);
            $matchSimulator->updatePlayerConditions($homeTeam, $awayTeam);
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Match simulation failed', [
            'match_id' => $this->matchId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
