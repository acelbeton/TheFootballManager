<?php

namespace App\Console\Commands;

use App\Models\MatchModel;
use App\Services\RealtimeMatchSimulationService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as CommandAlias;

class StartScheduledMatches extends Command
{
    protected $signature = 'matches:start-scheduled';
    protected $description = 'Start simulations for matches that are scheduled to begin now';

    public function handle(RealtimeMatchSimulationService $simulationService)
    {
        $this->info('Checking for matches that need to be started...');

        $matches = MatchModel::where('match_date', '<=', now())
            ->where('match_date', '>=', now()->subMinutes(1))
            ->where('home_team_score', 0)
            ->where('away_team_score', 0)
            ->get();

        $this->info("Found {$matches->count()} matches to start");

        foreach ($matches as $match) {
            try {
                $this->info("Starting match #{$match->getKey()}: {$match->home_team_id} vs {$match->away_team_id}");
                $result = $simulationService->startMatch($match);
                $this->info("Result: " . json_encode($result));
                Log::info("Auto-started match #{$match->getKey()}", $result);
            } catch (Exception $e) {
                $this->error("Error starting match #{$match->getKey()}: {$e->getMessage()}");
                Log::error("Failed to auto-start match #{$match->getKey()}", [
                    'match' => $match->getKey(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return CommandAlias::SUCCESS;
    }
}
